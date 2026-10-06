<?php

namespace Tests\Feature\User;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    private Category $nifty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->admin()->create();
        $this->nifty = Category::factory()->create(['name' => 'Nifty 50', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'NSE', 'sort_order' => 1]);
        Category::factory()->inactive()->create(['name' => 'Hidden Cat', 'sort_order' => 0]);

        // 10 entries, one per day: day 0 is newest.
        for ($i = 0; $i < 10; $i++) {
            Entry::factory()->create([
                'category_id' => $this->nifty->id, 'admin_id' => $admin->id,
                'entry_date' => today()->subDays($i)->toDateString(), 'entry_time' => '10:00:00',
            ]);
        }
    }

    private function trader(bool $active): User
    {
        $user = User::factory()->create();
        $active
            ? Subscription::factory()->create(['user_id' => $user->id])
            : Subscription::factory()->expired()->create(['user_id' => $user->id]);

        return $user;
    }

    private function dates(int $count): array
    {
        return array_map(fn ($i) => today()->subDays($i)->format('d M Y'), range(0, $count - 1));
    }

    public function test_tabs_are_active_categories_in_sort_order(): void
    {
        $this->signIn($this->trader(true))->get('/user')
            ->assertOk()
            ->assertSeeInOrder(['NSE', 'Nifty 50'])
            ->assertDontSee('Hidden Cat');
    }

    public function test_active_subscription_sees_latest_seven_newest_first_without_show_more(): void
    {
        $res = $this->signIn($this->trader(true))->get('/user?category='.$this->nifty->id)->assertOk();

        $res->assertSeeInOrder($this->dates(7));
        $res->assertDontSee(today()->subDays(7)->format('d M Y'));
        $res->assertDontSee('Show More');
    }

    public function test_expired_subscription_sees_latest_three_and_show_more_with_contacts(): void
    {
        $res = $this->signIn($this->trader(false))->get('/user?category='.$this->nifty->id)->assertOk();

        $res->assertSeeInOrder($this->dates(3));
        $res->assertDontSee(today()->subDays(3)->format('d M Y'));
        $res->assertSee('Show More')
            ->assertSee(config('astrogann.settings_defaults.contact_email'))
            ->assertSee('wa.me/919000000000', false);
    }

    public function test_user_without_any_subscription_is_restricted(): void
    {
        $res = $this->signIn(User::factory()->create())->get('/user?category='.$this->nifty->id);
        $res->assertSee('Show More')->assertDontSee(today()->subDays(3)->format('d M Y'));
    }

    public function test_limits_come_from_settings(): void
    {
        app(SettingsService::class)->update(['entries_limit_active' => '2', 'entries_limit_expired' => '1', 'show_more_text' => 'Pay to unlock']);

        $this->signIn($this->trader(true))->get('/user?category='.$this->nifty->id)
            ->assertSee(today()->subDays(1)->format('d M Y'))
            ->assertDontSee(today()->subDays(2)->format('d M Y'));

        $this->flushSession();
        $this->signIn($this->trader(false))->get('/user?category='.$this->nifty->id)
            ->assertSee('Pay to unlock')
            ->assertDontSee(today()->subDays(1)->format('d M Y'));
    }

    public function test_renaming_and_reordering_a_category_changes_tabs_without_code(): void
    {
        $user = $this->trader(true);
        $this->nifty->update(['name' => 'Nifty Fifty', 'sort_order' => 0]);

        $this->signIn($user)->get('/user')->assertSeeInOrder(['Nifty Fifty', 'NSE']);
    }

    public function test_renewal_restores_full_access(): void
    {
        $user = $this->trader(false);
        $sa = User::factory()->superAdmin()->create();
        app(\App\Services\SubscriptionService::class)->activate($user, today(), today()->addDays(30), $sa);

        $this->signIn($user)->get('/user?category='.$this->nifty->id)
            ->assertSee(today()->subDays(6)->format('d M Y'))
            ->assertDontSee('Show More');
    }

    public function test_subscription_page_shows_status_and_expiry(): void
    {
        $user = $this->trader(true);
        $end = Carbon::parse($user->subscriptions()->first()->end_date)->format('d M Y');

        $this->signIn($user)->get(route('user.subscription'))->assertOk()->assertSee('Active')->assertSee($end);
    }

    public function test_notifications_list_unread_count_and_mark_read(): void
    {
        $user = $this->trader(true);
        UserNotification::factory()->count(2)->create(['user_id' => $user->id, 'category_id' => $this->nifty->id, 'message' => 'New entry added in Nifty 50']);
        UserNotification::factory()->create(['message' => 'someone else']);

        $this->signIn($user);
        $this->get(route('user.notifications.index'))->assertOk()
            ->assertSee('New entry added in Nifty 50')
            ->assertDontSee('someone else')
            ->assertSee('Mark all as read (2)');

        $this->post(route('user.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->appNotifications()->unread()->count());
    }

    public function test_user_cannot_mark_someone_elses_notification(): void
    {
        $other = UserNotification::factory()->create();
        $this->signIn($this->trader(true))->post(route('user.notifications.read', $other->id))->assertNotFound();
        $this->assertFalse($other->fresh()->is_read);
    }

    public function test_notice_text_is_shown_when_set(): void
    {
        app(SettingsService::class)->update(['notice_text' => 'Market closed on Friday']);
        $this->signIn($this->trader(true))->get('/user')->assertSee('Market closed on Friday');
    }
}
