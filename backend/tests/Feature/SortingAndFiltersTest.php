<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D-77: list screens sort and filter accurately, and reject tampered parameters safely.
 */
class SortingAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $sa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->sa = User::factory()->superAdmin()->create();
        $this->signIn($this->sa);
    }

    public function test_entries_sort_by_date_both_ways_and_by_category(): void
    {
        $alpha = Category::factory()->create(['name' => 'Alpha']);
        $zulu = Category::factory()->create(['name' => 'Zulu']);
        Entry::factory()->create(['category_id' => $zulu->id, 'entry_date' => '2026-01-10', 'entry_time' => '09:00:00']);
        Entry::factory()->create(['category_id' => $alpha->id, 'entry_date' => '2026-03-15', 'entry_time' => '09:00:00']);
        Entry::factory()->create(['category_id' => $alpha->id, 'entry_date' => '2026-02-20', 'entry_time' => '11:30:00']);

        $this->get(route('superadmin.entries.index'))->assertSeeInOrder(['15 Mar 2026', '20 Feb 2026', '10 Jan 2026']);
        $this->get(route('superadmin.entries.index', ['sort' => 'entry', 'dir' => 'asc']))->assertSeeInOrder(['10 Jan 2026', '20 Feb 2026', '15 Mar 2026']);
        $this->get(route('superadmin.entries.index', ['sort' => 'category', 'dir' => 'desc']))->assertSeeInOrder(['Zulu', 'Alpha']);
    }

    public function test_entry_filters_by_date_range_category_and_admin(): void
    {
        $cat = Category::factory()->create();
        $other = Category::factory()->create();
        $admin = User::factory()->admin()->create(['name' => 'Ravi Admin']);
        Entry::factory()->create(['category_id' => $cat->id, 'admin_id' => $admin->id, 'entry_date' => '2026-05-01']);
        Entry::factory()->create(['category_id' => $cat->id, 'entry_date' => '2026-05-20']);
        Entry::factory()->create(['category_id' => $other->id, 'entry_date' => '2026-05-10']);

        $this->get(route('superadmin.entries.index', ['from' => '2026-05-05', 'to' => '2026-05-31']))
            ->assertSee('20 May 2026')->assertSee('10 May 2026')->assertDontSee('01 May 2026');
        $this->get(route('superadmin.entries.index', ['category_id' => $other->id]))
            ->assertSee('10 May 2026')->assertDontSee('20 May 2026');
        $this->get(route('superadmin.entries.index', ['admin_id' => $admin->id]))
            ->assertSee('01 May 2026')->assertDontSee('20 May 2026');
        // "to" before "from" is rejected, not silently wrong.
        $this->get(route('superadmin.entries.index', ['from' => '2026-05-10', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
    }

    public function test_tampered_sort_parameters_fall_back_safely(): void
    {
        Entry::factory()->count(2)->create();

        $this->get(route('superadmin.entries.index', ['sort' => 'image_path; DROP TABLE entries', 'dir' => 'sideways']))->assertOk();
        $this->get(route('superadmin.users.index', ['sort' => 'password']))->assertOk();
        $this->assertSame(2, Entry::count());
    }

    public function test_users_sort_and_subscription_filter(): void
    {
        $a = User::factory()->create(['name' => 'Aarav']);
        $z = User::factory()->create(['name' => 'Zoya']);
        $none = User::factory()->create(['name' => 'Neha']);
        Subscription::factory()->create(['user_id' => $a->id]);
        Subscription::factory()->expired()->create(['user_id' => $z->id]);

        $this->get(route('superadmin.users.index', ['sort' => 'name', 'dir' => 'asc']))->assertSeeInOrder(['Aarav', 'Neha', 'Zoya']);
        $this->get(route('superadmin.users.index', ['sort' => 'name', 'dir' => 'desc']))->assertSeeInOrder(['Zoya', 'Neha', 'Aarav']);
        $this->get(route('superadmin.users.index', ['subscription' => 'active']))->assertSee('Aarav')->assertDontSee('Zoya')->assertDontSee('Neha');
        $this->get(route('superadmin.users.index', ['subscription' => 'expired']))->assertSee('Zoya')->assertDontSee('Aarav');
        $this->get(route('superadmin.users.index', ['subscription' => 'none']))->assertSee('Neha')->assertDontSee('Aarav');
    }

    public function test_subscriptions_sorted_by_end_date(): void
    {
        $soon = User::factory()->create(['name' => 'Soon Ending']);
        $later = User::factory()->create(['name' => 'Later Ending']);
        Subscription::factory()->endingIn(2)->create(['user_id' => $soon->id]);
        Subscription::factory()->endingIn(40)->create(['user_id' => $later->id]);

        $this->get(route('superadmin.subscriptions.index'))->assertSeeInOrder(['Soon Ending', 'Later Ending']);
        $this->get(route('superadmin.subscriptions.index', ['sort' => 'end', 'dir' => 'desc']))->assertSeeInOrder(['Later Ending', 'Soon Ending']);
    }

    public function test_logs_date_filter_and_page_size(): void
    {
        ActivityLog::create(['action' => 'test.old', 'description' => 'old one'])->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();
        ActivityLog::create(['action' => 'test.new', 'description' => 'new one'])->forceFill(['created_at' => '2026-06-30 23:59:00'])->save();

        $this->get(route('superadmin.logs.index', ['from' => '2026-06-30', 'to' => '2026-06-30']))
            ->assertSee('new one')->assertDontSee('old one');
        $this->get(route('superadmin.logs.index', ['per_page' => 20]))->assertOk()->assertSee('Per page');
    }
}
