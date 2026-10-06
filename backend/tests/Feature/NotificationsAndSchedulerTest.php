<?php

namespace Tests\Feature;

use App\Events\EntryCreated;
use App\Jobs\NotifyUsersOfNewEntry;
use App\Jobs\SendPushToUsers;
use App\Mail\SubscriptionExpiringMail;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Push\FcmPushService;
use App\Services\Push\PushService;
use App\Services\SubscriptionService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    /** Captures pushes instead of sending them. */
    private object $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->push = new class implements PushService
        {
            public array $sent = [];

            public array $invalid = [];

            public function send(array $tokens, string $title, string $body, array $data = []): array
            {
                $this->sent[] = compact('tokens', 'title', 'body', 'data');

                return array_values(array_intersect($tokens, $this->invalid));
            }
        };
        $this->app->instance(PushService::class, $this->push);
    }

    private function traderWithDevice(bool $activeSub, string $token, bool $accountActive = true): User
    {
        $u = User::factory()->create(['status' => $accountActive ? 'active' : 'inactive']);
        $activeSub ? Subscription::factory()->create(['user_id' => $u->id]) : Subscription::factory()->expired()->create(['user_id' => $u->id]);
        $u->userSessions()->create(['device_type' => 'mobile', 'is_active' => true, 'fcm_token' => $token]);

        return $u;
    }

    public function test_adding_an_entry_fires_the_event_and_queues_the_fan_out(): void
    {
        Storage::fake('uploads');
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $cat = Category::factory()->create();

        $this->signIn($admin)->post(route('admin.entries.store'), [
            'category_id' => $cat->id, 'entry_date' => today()->toDateString(), 'entry_time' => '10:00',
            'image' => UploadedFile::fake()->image('x.png'),
        ])->assertRedirect();

        Queue::assertPushed(NotifyUsersOfNewEntry::class, fn ($job) => $job->entryId === Entry::sole()->id);
    }

    public function test_event_listener_is_registered(): void
    {
        Event::fake();
        Event::assertListening(EntryCreated::class, \App\Listeners\QueueEntryNotifications::class);
    }

    public function test_only_active_subscribers_get_notification_and_push(): void
    {
        $active = $this->traderWithDevice(true, 'tok-active');
        $expired = $this->traderWithDevice(false, 'tok-expired');
        $inactive = $this->traderWithDevice(true, 'tok-inactive', accountActive: false);
        $staff = User::factory()->admin()->create();
        $entry = Entry::factory()->create(['admin_id' => $staff->id]);

        (new NotifyUsersOfNewEntry($entry->id))->handle(app(SubscriptionService::class), app(\App\Services\NotificationService::class));
        // run the queued push job synchronously
        (new SendPushToUsers([$active->id], $entry->category->name, 'msg'))->handle($this->push);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $active->id, 'type' => 'new_entry', 'category_id' => $entry->category_id]);
        foreach ([$expired, $inactive, $staff] as $u) {
            $this->assertDatabaseMissing('user_notifications', ['user_id' => $u->id]);
        }
        $this->assertSame(['tok-active'], $this->push->sent[0]['tokens']);
        $this->assertStringContainsString($entry->category->name, \App\Models\UserNotification::first()->message);
    }

    public function test_fan_out_is_chunked_and_continues_in_a_new_job(): void
    {
        config(['astrogann.notification_chunk' => 2]);
        Queue::fake();
        $users = collect(range(1, 5))->map(fn ($i) => $this->traderWithDevice(true, "t{$i}"));
        $entry = Entry::factory()->create();

        (new NotifyUsersOfNewEntry($entry->id))->handle(app(SubscriptionService::class), app(\App\Services\NotificationService::class));

        $this->assertSame(2, \App\Models\UserNotification::count());
        Queue::assertPushed(SendPushToUsers::class, 1);
        Queue::assertPushed(NotifyUsersOfNewEntry::class, fn ($job) => $job->afterUserId === $users[1]->id);
    }

    public function test_whole_flow_through_the_sync_queue(): void
    {
        config(['queue.default' => 'sync']);
        $u = $this->traderWithDevice(true, 'tok-1');
        $entry = Entry::factory()->create();

        EntryCreated::dispatch($entry->load('category'));

        $this->assertSame(1, $u->appNotifications()->count());
        $this->assertSame(['tok-1'], $this->push->sent[0]['tokens']);
        $this->assertSame('new_entry', $this->push->sent[0]['data']['type']);
    }

    public function test_invalid_push_tokens_are_cleared(): void
    {
        $u = $this->traderWithDevice(true, 'dead-token');
        $this->push->invalid = ['dead-token'];

        (new SendPushToUsers([$u->id], 't', 'b'))->handle($this->push);

        $this->assertNull($u->userSessions()->first()->fcm_token);
    }

    public function test_expiry_reminder_is_sent_once_and_again_after_extension(): void
    {
        Mail::fake();
        Queue::fake([SendPushToUsers::class]);
        $u = User::factory()->create();
        $sub = Subscription::factory()->endingIn(3)->create(['user_id' => $u->id]);
        $later = Subscription::factory()->endingIn(10)->create();   // not due
        $expired = Subscription::factory()->expired()->create();     // not due

        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertQueued(SubscriptionExpiringMail::class, fn ($m) => $m->hasTo($u->email) && $m->daysLeft === 3);
        Mail::assertQueuedCount(1);
        $this->assertNotNull($sub->fresh()->reminder_sent_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'type' => 'expiry']);

        // Second run the same day or the next: nothing new.
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertQueuedCount(1);

        // Extension resets the flag; when the new end date comes close, a new reminder goes out.
        app(SubscriptionService::class)->extend($u, today()->addDays(2)->addMonth(), User::factory()->superAdmin()->create());
        $this->assertNull($sub->fresh()->reminder_sent_at);
        $this->travelTo(today()->addMonth());
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertQueuedCount(2);
    }

    public function test_reminder_window_uses_the_setting(): void
    {
        Mail::fake();
        Queue::fake([SendPushToUsers::class]);
        app(\App\Services\SettingsService::class)->update(['reminder_days' => '5']);
        Subscription::factory()->endingIn(5)->create();

        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertQueuedCount(1);
    }

    public function test_mail_renders(): void
    {
        $u = User::factory()->create(['name' => 'Ravi']);
        $sub = Subscription::factory()->endingIn(3)->create(['user_id' => $u->id]);

        $mail = new SubscriptionExpiringMail($u, $sub, 3);
        $mail->assertSeeInHtml('Ravi');
        $mail->assertSeeInHtml('3 days');
        $mail->assertHasSubject('Your AstroGann subscription will expire in 3 days');
    }

    public function test_daily_expire_command(): void
    {
        $overdue = Subscription::factory()->endingIn(-1)->create(['status' => 'active']);
        $today = Subscription::factory()->endingIn(0)->create();

        $this->artisan('astrogann:expire-subscriptions')->assertSuccessful();

        $this->assertSame('expired', $overdue->fresh()->status->value);
        $this->assertSame('active', $today->fresh()->status->value); // end_date is inclusive
    }

    public function test_scheduler_has_queue_drain_and_daily_jobs(): void
    {
        $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

        $this->assertStringContainsString('queue:work --stop-when-empty', $commands);
        $this->assertStringContainsString('astrogann:expire-subscriptions', $commands);
        $this->assertStringContainsString('astrogann:send-expiry-reminders', $commands);
    }

    public function test_fcm_driver_signs_jwt_and_reports_invalid_tokens(): void
    {
        // Throwaway key generated only for this test (not a real Firebase credential).
        $pem = file_get_contents(base_path('tests/Fixtures/fcm-test-private-key.pem'));
        $path = tempnam(sys_get_temp_dir(), 'sa');
        file_put_contents($path, json_encode(['client_email' => 'x@proj.iam.gserviceaccount.com', 'private_key' => $pem]));

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => function ($request) {
                return $request['message']['token'] === 'bad'
                    ? Http::response(['error' => ['status' => 'UNREGISTERED', 'message' => 'gone']], 404)
                    : Http::response(['name' => 'projects/p/messages/1']);
            },
        ]);

        $invalid = (new FcmPushService('proj', $path))->send(['good', 'bad'], 'Title', 'Body', ['category_id' => 1]);

        $this->assertSame(['bad'], $invalid);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com') && str_starts_with($r['assertion'], 'eyJ'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/projects/proj/messages:send')
            && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && $r['message']['data']['category_id'] === '1');
        @unlink($path);
    }
}
