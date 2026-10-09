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
use Illuminate\Support\Facades\Bus;
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

    public function test_adding_an_entry_starts_the_fan_out_right_after_the_response(): void
    {
        Storage::fake('uploads');
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $cat = Category::factory()->create();

        $this->signIn($admin)->post(route('admin.entries.store'), [
            'category_id' => $cat->id, 'entry_date' => today()->toDateString(), 'entry_time' => '10:00',
            'image' => UploadedFile::fake()->image('x.png'),
        ])->assertRedirect();

        Bus::assertDispatchedAfterResponse(NotifyUsersOfNewEntry::class, fn ($job) => $job->entryId === Entry::sole()->id);
    }

    public function test_event_listener_is_registered(): void
    {
        Event::fake();
        Event::assertListening(EntryCreated::class, \App\Listeners\QueueEntryNotifications::class);
    }

    public function test_active_users_get_notification_and_push_expired_users_get_renew_notice_only(): void
    {
        $active = $this->traderWithDevice(true, 'tok-active');
        $expired = $this->traderWithDevice(false, 'tok-expired');
        $never = User::factory()->create(); // never subscribed
        $inactive = $this->traderWithDevice(true, 'tok-inactive', accountActive: false);
        $staff = User::factory()->admin()->create();
        $entry = Entry::factory()->create(['admin_id' => $staff->id]);

        app()->call([new NotifyUsersOfNewEntry($entry->id), 'handle']);

        $this->assertStringContainsString('Check latest update', $active->appNotifications()->sole()->message);
        $this->assertStringContainsString('Renew your subscription', $expired->appNotifications()->sole()->message);
        $this->assertStringContainsString('Renew your subscription', $never->appNotifications()->sole()->message);
        foreach ([$inactive, $staff] as $u) {
            $this->assertDatabaseMissing('user_notifications', ['user_id' => $u->id]);
        }
        // Push to all users (D-89): active with the entry image, expired with the "renew" text; sent immediately.
        $this->assertCount(2, $this->push->sent);
        $this->assertSame(['tok-active'], $this->push->sent[0]['tokens']);
        $this->assertNotEmpty($this->push->sent[0]['data']['image']);
        $this->assertSame(['tok-expired'], $this->push->sent[1]['tokens']);
        $this->assertStringContainsString('Renew', $this->push->sent[1]['body']);
        $this->assertArrayNotHasKey('image', $this->push->sent[1]['data']);

        // Push summary in Reports / Logs.
        $this->assertDatabaseHas('activity_logs', ['action' => 'push.sent']);
    }

    public function test_push_to_expired_users_can_be_switched_off(): void
    {
        app(\App\Services\SettingsService::class)->update(['push_expired_users' => '0']);
        $this->traderWithDevice(true, 'tok-active');
        $this->traderWithDevice(false, 'tok-expired');
        $entry = Entry::factory()->create();

        app()->call([new NotifyUsersOfNewEntry($entry->id), 'handle']);

        $this->assertCount(1, $this->push->sent);
        $this->assertSame(['tok-active'], $this->push->sent[0]['tokens']);
    }

    public function test_expired_users_are_skipped_when_the_setting_is_off(): void
    {
        app(\App\Services\SettingsService::class)->update(['notify_expired_users' => '0']);
        $active = $this->traderWithDevice(true, 'a');
        $expired = $this->traderWithDevice(false, 'e');
        $entry = Entry::factory()->create();

        app()->call([new NotifyUsersOfNewEntry($entry->id), 'handle']);

        $this->assertSame(1, $active->appNotifications()->count());
        $this->assertSame(0, $expired->appNotifications()->count());
    }

    public function test_fan_out_is_idempotent(): void
    {
        $u = $this->traderWithDevice(true, 'tok');
        $entry = Entry::factory()->create();

        app()->call([new NotifyUsersOfNewEntry($entry->id), 'handle']);
        app()->call([new NotifyUsersOfNewEntry($entry->id), 'handle']); // retry / duplicate run

        $this->assertSame(1, $u->appNotifications()->count());
        $this->assertCount(1, $this->push->sent);
    }

    public function test_fan_out_is_chunked_and_continues_in_a_new_job(): void
    {
        config(['astrogann.notification_chunk' => 2]);
        Queue::fake();
        $users = collect(range(1, 5))->map(fn ($i) => $this->traderWithDevice(true, "t{$i}"));
        $entry = Entry::factory()->create();

        app()->call([new NotifyUsersOfNewEntry($entry->id), 'handle']);

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
        $this->app->terminate(); // runs the after-response first chunk

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

        $this->artisan('astrogann:collect-expiry-reminders')->assertSuccessful();
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiringMail::class, fn ($m) => $m->hasTo($u->email) && $m->daysLeft === 3);
        Mail::assertSent(SubscriptionExpiringMail::class, 1);
        $this->assertNotNull($sub->fresh()->reminder_sent_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'type' => 'expiry']);

        // Second run the same day or the next: nothing new.
        $this->artisan('astrogann:collect-expiry-reminders')->assertSuccessful();
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiringMail::class, 1);

        // Extension resets the flag; when the new end date comes close, a new reminder goes out.
        app(SubscriptionService::class)->extend($u, today()->addDays(2)->addMonth(), User::factory()->superAdmin()->create());
        $this->assertNull($sub->fresh()->reminder_sent_at);
        $this->travelTo(today()->addMonth());
        $this->artisan('astrogann:collect-expiry-reminders')->assertSuccessful();
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiringMail::class, 2);
    }

    public function test_expiry_notice_shows_in_notifications_tab_once_even_when_the_email_fails(): void
    {
        Queue::fake([SendPushToUsers::class]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $u = User::factory()->create();
        Subscription::factory()->endingIn(3)->create(['user_id' => $u->id]);

        $this->artisan('astrogann:collect-expiry-reminders')->assertSuccessful();
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful(); // e-mail fails → retried later
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful(); // retry fails again

        // The in-app notice appeared on the first run and was not repeated by the retry (D-95).
        $notice = $u->appNotifications()->where('type', 'expiry')->sole();
        $this->assertStringContainsString('3 days', $notice->message);
        Queue::assertPushed(SendPushToUsers::class, 1);
        $this->assertDatabaseHas('expiry_reminders', ['user_id' => $u->id, 'status' => 'pending', 'attempts' => 2]);

        // Web poll links an expiry notice to the subscription page.
        $this->signIn($u)->getJson(route('user.notifications.poll'))
            ->assertOk()->assertJsonPath('items.0.url', route('user.subscription'));
    }
    public function test_reminder_window_uses_the_setting(): void
    {
        Mail::fake();
        Queue::fake([SendPushToUsers::class]);
        app(\App\Services\SettingsService::class)->update(['reminder_days' => '5']);
        Subscription::factory()->endingIn(5)->create();

        $this->artisan('astrogann:collect-expiry-reminders')->assertSuccessful();
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiringMail::class, 1);
    }

    public function test_mail_renders(): void
    {
        $u = User::factory()->create(['name' => 'Ravi']);
        $sub = Subscription::factory()->endingIn(3)->create(['user_id' => $u->id]);

        $mail = new SubscriptionExpiringMail($u, $sub, 3);
        $mail->assertSeeInHtml('Ravi');
        $mail->assertSeeInHtml('3 days');
        $mail->assertHasSubject('Your ASTRO GANN subscription will expire in 3 days');
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
    public function test_subscription_emails_go_one_by_one_at_a_safe_pace(): void
    {
        Mail::fake();
        Queue::fake([SendPushToUsers::class]);
        config(['astrogann.mail_per_minute' => 2]);
        $users = collect(range(1, 3))->map(fn () => User::factory()->create());
        $users->each(fn ($u) => Subscription::factory()->endingIn(3)->create(['user_id' => $u->id]));

        $this->artisan('astrogann:collect-expiry-reminders')->assertSuccessful();
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();

        // Saved once, then sent one by one: at most MAIL_PER_MINUTE per cron run, the rest on the next run.
        Mail::assertSent(SubscriptionExpiringMail::class, 2);
        $this->assertSame(1, \App\Models\ExpiryReminder::where('status', 'pending')->count());
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiringMail::class, 3);
        foreach ($users as $u) {
            Mail::assertSent(SubscriptionExpiringMail::class, fn ($m) => $m->hasTo($u->email));
        }
        $this->assertSame(3, \App\Models\ExpiryReminder::where('status', 'sent')->whereNotNull('sent_at')->count());
    }

    public function test_reminders_are_collected_at_6pm_and_sent_every_minute(): void
    {
        $find = fn (string $cmd) => collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, $cmd));
        $this->assertSame('0 18 * * *', $find('astrogann:collect-expiry-reminders')->expression);
        $this->assertSame('* * * * *', $find('astrogann:send-expiry-reminders')->expression);
    }

    public function test_users_are_collected_only_once_and_changed_ones_are_skipped(): void
    {
        Mail::fake();
        Queue::fake([SendPushToUsers::class]);
        $keep = User::factory()->create();
        $gone = User::factory()->create();
        Subscription::factory()->endingIn(3)->create(['user_id' => $keep->id]);
        Subscription::factory()->endingIn(2)->create(['user_id' => $gone->id]);

        $this->artisan('astrogann:collect-expiry-reminders')->expectsOutputToContain('Saved 2');
        $this->artisan('astrogann:collect-expiry-reminders')->expectsOutputToContain('Saved 0'); // never twice

        $gone->update(['status' => 'inactive']); // deactivated after the list was saved
        $this->artisan('astrogann:send-expiry-reminders')->assertSuccessful();

        Mail::assertSent(SubscriptionExpiringMail::class, 1);
        Mail::assertSent(SubscriptionExpiringMail::class, fn ($m) => $m->hasTo($keep->email));
        $this->assertSame('failed', \App\Models\ExpiryReminder::where('user_id', $gone->id)->value('status'));
    }

    public function test_super_admin_reminders_screen_and_buttons(): void
    {
        Mail::fake();
        Queue::fake([SendPushToUsers::class]);
        $u = User::factory()->create(['name' => 'Soon Expiring']);
        Subscription::factory()->endingIn(3)->create(['user_id' => $u->id]);
        $this->signIn(User::factory()->superAdmin()->create());

        $this->post(route('superadmin.reminders.collect'))->assertSessionHas('success');
        $this->get(route('superadmin.reminders.index'))->assertOk()->assertSee('Soon Expiring')->assertSee('waiting');
        $this->post(route('superadmin.reminders.send'))->assertSessionHas('success');
        $this->get(route('superadmin.reminders.index', ['status' => 'sent']))->assertOk()->assertSee('Soon Expiring');
        Mail::assertSent(SubscriptionExpiringMail::class, 1);
    }

    public function test_live_system_check_shows_cron_heartbeat_and_sends_a_test_email(): void
    {
        $this->signIn(User::factory()->superAdmin()->create());
        $this->get(route('superadmin.reminders.index'))->assertOk()->assertSee('Cron: NOT running')->assertSee('never');

        // One scheduler run (what cPanel cron does) records the heartbeat.
        $this->artisan('schedule:run')->assertSuccessful();
        $this->get(route('superadmin.reminders.index'))->assertOk()->assertSee('Cron: running ✓');

        Mail::fake();
        $this->post(route('superadmin.reminders.test-mail'), ['email' => 'owner@example.test'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'owner@example.test'));
        $this->post(route('superadmin.reminders.test-mail'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        // A failing mail server shows the exact reason instead of an error page.
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Authentication failed: bad app password'));
        $this->post(route('superadmin.reminders.test-mail'), ['email' => 'owner@example.test'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'bad app password'));
    }
}
