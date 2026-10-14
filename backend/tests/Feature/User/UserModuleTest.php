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
                'created_at' => today()->subDays($i)->setTime(10, 0), // added on its own day
            ]);
        }
    }

    private function trader(bool $active): User
    {
        $user = User::factory()->create();
        $active
            ? Subscription::factory()->create(['user_id' => $user->id])
            : Subscription::factory()->expired()->create(['user_id' => $user->id, 'end_date' => today()->subDays(2)->toDateString()]);

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
        $res = $this->signIn($this->trader(true))->get('/user?tab='.$this->nifty->tabKey())->assertOk();

        $res->assertSeeInOrder($this->dates(7));
        $res->assertDontSee(today()->subDays(7)->format('d M Y'));
        $res->assertDontSee('Show More');
    }

    public function test_expired_subscription_sees_oldest_three_of_latest_seven_and_show_more_with_contacts(): void
    {
        $res = $this->signIn($this->trader(false))->get('/user?tab='.$this->nifty->tabKey())->assertOk();

        // Expired 2 days ago (D-107): the latest 7 on that day were days 2–8 → shows the 5th–7th = days 6, 7, 8.
        $res->assertSeeInOrder(array_map(fn ($d) => today()->subDays($d)->format('d M Y'), [6, 7, 8]));
        // (day 2 is the expiry date itself, shown in the subscription bar)
        foreach ([0, 1, 3, 4, 5, 9] as $day) {
            $res->assertDontSee(today()->subDays($day)->format('d M Y'));
        }
        $res->assertSee('Show More')
            ->assertSee(config('astrogann.settings_defaults.contact_email'))
            ->assertSee('wa.me/917558377920', false)->assertSee('wa.me/918149343315', false)->assertSee('tel:+917558377920', false);
    }

    public function test_user_without_any_subscription_is_restricted(): void
    {
        $res = $this->signIn(User::factory()->create())->get('/user?tab='.$this->nifty->tabKey());
        $res->assertSee('Show More')->assertSee(today()->subDays(4)->format('d M Y'))->assertDontSee(today()->subDays(3)->format('d M Y'));
    }

    public function test_expired_user_list_is_frozen_at_expiry_new_entries_never_show(): void
    {
        $user = $this->trader(false);
        $ids = fn () => app(\App\Services\EntryService::class)->visibleFor($user, $this->nifty)['entries']->pluck('entry_date')
            ->map(fn ($d) => $d->toDateString())->all();
        $before = $ids();
        $this->assertSame(array_map(fn ($d) => today()->subDays($d)->toDateString(), [6, 7, 8]), $before);

        Entry::factory()->create(['category_id' => $this->nifty->id, 'entry_date' => today()->toDateString(), 'entry_time' => '15:00:00']);
        $this->assertSame($before, $ids(), 'A new entry after expiry must not change what the expired user sees (D-107).');

        // Never subscribed: the 5th–7th of today's latest 7, so it moves with new entries.
        $never = User::factory()->create();
        $this->assertCount(3, app(\App\Services\EntryService::class)->visibleFor($never, $this->nifty)['entries']);
    }

    public function test_limits_come_from_settings(): void
    {
        app(SettingsService::class)->update(['entries_limit_active' => '2', 'entries_limit_expired' => '1', 'show_more_text' => 'Pay to unlock']);

        $this->signIn($this->trader(true))->get('/user?tab='.$this->nifty->tabKey())
            ->assertSee(today()->subDays(1)->format('d M Y'))
            ->assertDontSee(today()->subDays(2)->format('d M Y'));

        $this->flushSession();
        // Oldest 1 of the latest 2 on the expiry day (days 2, 3) → day 3 (D-106, D-107).
        $this->signIn($this->trader(false))->get('/user?tab='.$this->nifty->tabKey())
            ->assertSee('Pay to unlock')
            ->assertSee(today()->subDays(3)->format('d M Y'))
            ->assertDontSee(today()->subDays(1)->format('d M Y'))
            ->assertDontSee(today()->subDays(4)->format('d M Y'));
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

        $this->signIn($user)->get('/user?tab='.$this->nifty->tabKey())
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

    public function test_direct_url_access_cannot_open_inactive_or_unknown_categories(): void
    {
        $user = $this->trader(true);
        $hidden = Category::where('name', 'Hidden Cat')->sole();
        Entry::factory()->create(['category_id' => $hidden->id, 'entry_date' => '2020-02-02']);
        $this->signIn($user);

        // Tabs never expose numeric ids.
        $this->get('/user')->assertOk()->assertDontSee('category=', false)->assertSee('?tab='.$this->nifty->tabKey(), false);

        foreach (['/user?tab='.$hidden->tabKey(), '/user?tab=does-not-exist', '/user?category='.$hidden->id, '/user?tab[]=x'] as $url) {
            $this->get($url)->assertRedirect(route('user.dashboard'))->assertSessionHas('status', 'That category is not available.');
        }
        $this->get('/user')->assertDontSee('02 Feb 2020');

        // API enforces the same rule.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->apiSignIn($user)->getJson("/api/categories/{$hidden->id}/entries")->assertNotFound();
    }

    public function test_notice_text_is_shown_when_set(): void
    {
        app(SettingsService::class)->update(['notice_text' => 'Market closed on Friday']);
        $this->signIn($this->trader(true))->get('/user')->assertSee('Market closed on Friday');
    }
    public function test_new_entry_notifies_active_and_inactive_users_at_the_same_time_and_inactive_tap_asks_to_subscribe(): void
    {
        config(['queue.default' => 'sync']);
        $active = $this->trader(true);
        $expired = $this->trader(false);
        $entry = Entry::factory()->create(['category_id' => $this->nifty->id, 'entry_date' => today()->toDateString()]);

        \App\Events\EntryCreated::dispatch($entry->load('category'));
        $this->app->terminate();

        $a = $active->appNotifications()->sole();
        $e = $expired->appNotifications()->sole();
        $this->assertStringContainsString('Check latest update', $a->message);
        $this->assertStringContainsString('Renew your subscription', $e->message);
        $this->assertEquals($a->created_at, $e->created_at); // same moment, same job

        // Active: tap opens the tab normally.
        $this->signIn($active)->post(route('user.notifications.read', $a->id))
            ->assertRedirect(route('user.dashboard', ['tab' => $this->nifty->tabKey()]));

        // Not active: tap opens the tab with the contact-admin panel open and highlighted.
        $this->flushSession();
        $this->signIn($expired)->post(route('user.notifications.read', $e->id))
            ->assertRedirect(route('user.dashboard', ['tab' => $this->nifty->tabKey(), 'subscribe' => 1]))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Subscribe'));
        $this->get(route('user.dashboard', ['tab' => $this->nifty->tabKey(), 'subscribe' => 1]))
            ->assertOk()->assertSee('id="subscribe"', false)->assertSee('ring-yellow-300', false)->assertSee('Show More');
    }
}
