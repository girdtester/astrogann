<?php

namespace Tests\Feature;

use App\Jobs\NotifyUsersOfAnnouncement;
use App\Jobs\SendPushToUsers;
use App\Models\Announcement;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Permissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** D-100: Super Admin announcements delivered like every other notification. */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $active;

    private User $expired;

    private User $never;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->active = User::factory()->create();
        Subscription::factory()->create(['user_id' => $this->active->id]);
        $this->expired = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $this->expired->id]);
        $this->never = User::factory()->create();
        User::factory()->create(['status' => 'inactive']); // deactivated account: never notified
    }

    private function send(string $audience = 'all'): Announcement
    {
        $this->signIn(User::factory()->superAdmin()->create())
            ->post(route('superadmin.announcements.store'), ['title' => 'Market holiday', 'message' => "Markets are closed on Friday.\nSee you Monday.", 'audience' => $audience])
            ->assertRedirect(route('superadmin.announcements.index'))->assertSessionHas('success');
        $this->app->terminate(); // runs the first chunk "after the response"

        return Announcement::sole();
    }

    public function test_announcement_reaches_every_active_user_once_on_web_and_app(): void
    {
        Queue::fake([SendPushToUsers::class]);
        $a = $this->send();

        $this->assertSame(3, $a->fresh()->recipients);
        foreach ([$this->active, $this->expired, $this->never] as $u) {
            $this->assertSame(1, $u->appNotifications()->where('announcement_id', $a->id)->where('type', 'announcement')->count());
        }
        $this->assertSame(3, UserNotification::count());
        Queue::assertPushed(SendPushToUsers::class, fn ($j) => $j->title === 'Market holiday' && count($j->userIds) === 3);

        // Re-running the job (retry) adds nothing.
        (new NotifyUsersOfAnnouncement($a->id))->handle(app(\App\Services\SubscriptionService::class), app(\App\Services\NotificationService::class));
        $this->assertSame(3, UserNotification::count());

        // Website: list, live check (toast) and tap.
        $this->flushSession();
        $this->signIn($this->expired)->get(route('user.notifications.index'))
            ->assertOk()->assertSee('Announcement')->assertSee('Market holiday')->assertSee('Markets are closed on Friday.');
        $this->getJson(route('user.notifications.poll'))->assertOk()
            ->assertJsonPath('items.0.title', 'Market holiday')->assertJsonPath('items.0.url', route('user.notifications.index'));
        $n = $this->expired->appNotifications()->sole();
        $this->post(route('user.notifications.read', $n->id))->assertRedirect(route('user.notifications.index'));
        $this->assertTrue($n->fresh()->is_read);

        // App API.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('sanctum');
        $this->apiSignIn($this->active)->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('data.items.0.type', 'announcement')
            ->assertJsonPath('data.items.0.title', 'Market holiday')
            ->assertJsonPath('data.items.0.message', "Markets are closed on Friday.\nSee you Monday.");
    }

    public function test_audience_active_or_not_active_only(): void
    {
        Queue::fake([SendPushToUsers::class]);
        $a = $this->send('active');
        $this->assertSame([$this->active->id], UserNotification::where('announcement_id', $a->id)->pluck('user_id')->all());

        Announcement::query()->delete();
        $b = $this->send('inactive');
        $this->assertEqualsCanonicalizing([$this->expired->id, $this->never->id], UserNotification::where('announcement_id', $b->id)->pluck('user_id')->all());
    }

    public function test_super_admin_page_and_delete_removes_it_from_users(): void
    {
        Queue::fake([SendPushToUsers::class]);
        $a = $this->send();
        $this->get(route('superadmin.announcements.index'))->assertOk()->assertSee('Market holiday')->assertSee('3 users');

        $this->delete(route('superadmin.announcements.destroy', $a))->assertSessionHas('success');
        $this->assertSame(0, Announcement::count());
        $this->assertSame(0, UserNotification::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'announcement.deleted']);
    }

    public function test_validation_and_permission(): void
    {
        $this->signIn(User::factory()->superAdmin()->create())
            ->post(route('superadmin.announcements.store'), ['title' => '', 'message' => str_repeat('x', 501), 'audience' => 'everyone'])
            ->assertSessionHasErrors(['title', 'message', 'audience']);

        // Staff without the permission: no menu entry, no access.
        $this->flushSession();
        $admin = User::factory()->admin()->create();
        $this->signIn($admin)->get(route('superadmin.announcements.index'))->assertForbidden();
        $this->assertFalse($admin->hasPermission(Permissions::ANNOUNCEMENTS_SEND));

        // Users never.
        $this->flushSession();
        $this->signIn($this->active)->get(route('superadmin.announcements.index'))->assertForbidden();
    }
}
