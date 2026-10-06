<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperAdminModuleTest extends TestCase
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

    public function test_every_management_screen_renders(): void
    {
        $trader = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Entry::factory()->create(['category_id' => $category->id, 'admin_id' => $admin->id]);

        foreach ([
            route('superadmin.dashboard'),
            route('superadmin.admins.index'), route('superadmin.admins.create'), route('superadmin.admins.edit', $admin),
            route('superadmin.users.index'), route('superadmin.users.create'), route('superadmin.users.edit', $trader),
            route('superadmin.subscriptions.index'), route('superadmin.subscriptions.show', $trader),
            route('superadmin.categories.index'), route('superadmin.categories.create'), route('superadmin.categories.edit', $category),
            route('superadmin.entries.index'), route('superadmin.logs.index'), route('superadmin.settings.edit'),
            route('superadmin.roles.index'), route('superadmin.roles.create'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_create_admin_and_user(): void
    {
        $this->post(route('superadmin.admins.store'), [
            'name' => 'New Admin', 'email' => 'NEW.admin@astrogann.test', 'password' => 'password1', 'password_confirmation' => 'password1',
        ])->assertRedirect(route('superadmin.admins.index'));
        $admin = User::where('email', 'new.admin@astrogann.test')->sole();
        $this->assertTrue($admin->isStaff());
        $this->assertSame(Role::ADMIN, $admin->role->slug);

        $this->post(route('superadmin.users.store'), [
            'name' => 'Trader', 'email' => 'trader@astrogann.test', 'phone' => '999', 'password' => 'password1', 'password_confirmation' => 'password1',
        ])->assertRedirect();
        $this->assertTrue(User::where('email', 'trader@astrogann.test')->sole()->isTrader());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created']);
    }

    public function test_deactivating_a_user_ends_their_sessions(): void
    {
        $trader = User::factory()->create();
        $session = $trader->userSessions()->create(['device_type' => 'mobile', 'is_active' => true]);
        $trader->createToken('phone');

        $this->patch(route('superadmin.users.status', $trader))->assertRedirect();

        $this->assertFalse($trader->fresh()->isActive());
        $this->assertFalse($session->fresh()->is_active);
        $this->assertSame(0, $trader->tokens()->count());
    }

    public function test_super_admin_can_delete_accounts_with_history(): void
    {
        $trader = User::factory()->create();
        Subscription::factory()->create(['user_id' => $trader->id]);
        $this->delete(route('superadmin.users.destroy', $trader))->assertRedirect(route('superadmin.users.index'));
        $this->assertNull($trader->fresh());
        $this->assertSame(0, Subscription::where('user_id', $trader->id)->count());

        // Staff with entries: entries move to the Super Admin.
        $admin = User::factory()->admin()->create();
        $entry = Entry::factory()->create(['admin_id' => $admin->id]);
        $this->delete(route('superadmin.admins.destroy', $admin))->assertRedirect(route('superadmin.admins.index'));
        $this->assertNull($admin->fresh());
        $this->assertSame($this->sa->id, $entry->fresh()->admin_id);
    }

    public function test_staff_without_force_delete_cannot_delete_accounts_with_history(): void
    {
        $manager = User::factory()->staffWith('users.manage')->create();
        $trader = User::factory()->create();
        Subscription::factory()->create(['user_id' => $trader->id]);
        $empty = User::factory()->create();

        $this->flushSession();
        $this->signIn($manager);
        $this->delete(route('superadmin.users.destroy', $trader))->assertSessionHas('error');
        $this->assertNotNull($trader->fresh());
        $this->delete(route('superadmin.users.destroy', $empty))->assertRedirect(route('superadmin.users.index'));
        $this->assertNull($empty->fresh());
    }

    public function test_users_screen_cannot_edit_a_staff_account(): void
    {
        $admin = User::factory()->admin()->create();
        $this->get(route('superadmin.users.edit', $admin))->assertNotFound();
    }

    public function test_categories_crud_and_order(): void
    {
        $this->post(route('superadmin.categories.store'), ['name' => 'Bank Nifty', 'code' => 'bank-nifty', 'status' => 'active', 'sort_order' => 3])
            ->assertRedirect(route('superadmin.categories.index'));
        $cat = Category::where('code', 'BANK-NIFTY')->sole();

        $this->put(route('superadmin.categories.update', $cat), ['name' => 'Bank Nifty 2', 'code' => 'BANK-NIFTY', 'status' => 'inactive', 'sort_order' => 1])
            ->assertRedirect();
        $this->assertSame('Bank Nifty 2', $cat->fresh()->name);
        $this->assertFalse($cat->fresh()->isActive());

        $this->delete(route('superadmin.categories.destroy', $cat))->assertRedirect();
        $this->assertNull($cat->fresh());
    }

    public function test_super_admin_can_delete_category_with_entries_but_others_cannot(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('entries/a.png', 'x');
        $entry = Entry::factory()->create(['image_path' => 'uploads/entries/a.png']);
        $category = $entry->category;

        $manager = User::factory()->staffWith('categories.manage')->create();
        $this->flushSession();
        $this->signIn($manager)->delete(route('superadmin.categories.destroy', $category))->assertSessionHas('error');
        $this->assertNotNull($category->fresh());

        $this->flushSession();
        $this->signIn($this->sa)->delete(route('superadmin.categories.destroy', $category))->assertRedirect();
        $this->assertNull($category->fresh());
        $this->assertNull($entry->fresh());
        Storage::disk('uploads')->assertMissing('entries/a.png');
    }

    public function test_subscription_activate_extend_expire_with_payment(): void
    {
        $trader = User::factory()->create();

        $this->post(route('superadmin.subscriptions.action', $trader), [
            'action' => 'activate', 'start_date' => today()->toDateString(), 'end_date' => today()->addDays(30)->toDateString(),
            'payment' => ['amount' => '1999', 'payment_date' => today()->toDateString(), 'transaction_ref' => 'UPI-1'],
        ])->assertRedirect(route('superadmin.subscriptions.show', $trader));
        $sub = $trader->subscriptions()->sole();
        $this->assertTrue($sub->isCurrentlyActive());
        $this->assertDatabaseHas('subscription_payments', ['user_id' => $trader->id, 'transaction_ref' => 'UPI-1', 'subscription_id' => $sub->id]);

        $sub->update(['reminder_sent_at' => now()]);
        $this->post(route('superadmin.subscriptions.action', $trader), ['action' => 'extend', 'end_date' => today()->addDays(60)->toDateString()])
            ->assertSessionHas('success');
        $sub->refresh();
        $this->assertSame(today()->addDays(60)->toDateString(), $sub->end_date->toDateString());
        $this->assertNull($sub->reminder_sent_at);

        $this->post(route('superadmin.subscriptions.action', $trader), ['action' => 'expire'])->assertSessionHas('success');
        $this->assertFalse($sub->fresh()->isCurrentlyActive());
    }

    public function test_extend_rejects_earlier_date(): void
    {
        $trader = User::factory()->create();
        Subscription::factory()->create(['user_id' => $trader->id, 'end_date' => today()->addDays(10)]);

        $this->post(route('superadmin.subscriptions.action', $trader), ['action' => 'extend', 'end_date' => today()->addDays(5)->toDateString()])
            ->assertSessionHas('error');
    }

    public function test_settings_are_saved_and_used(): void
    {
        $this->put(route('superadmin.settings.update'), [
            'app_name' => 'AstroGann Pro', 'entries_limit_active' => 10, 'entries_limit_expired' => 2, 'reminder_days' => 5,
            'show_more_text' => 'Call us', 'contact_phone' => '+91 1', 'contact_whatsapp' => '+911', 'contact_email' => 'a@b.test', 'notice_text' => '',
        ])->assertRedirect(route('superadmin.settings.edit'));

        $settings = app(SettingsService::class);
        $this->assertSame(10, $settings->entriesLimitActive());
        $this->assertSame(2, $settings->entriesLimitExpired());
        $this->assertSame('AstroGann Pro', $settings->get('app_name'));
    }

    public function test_super_admin_can_edit_and_delete_any_entry(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('entries/old.png', 'x');
        $entry = Entry::factory()->create(['image_path' => 'uploads/entries/old.png']);
        $other = Category::factory()->create(['name' => 'Moved Here']);

        $this->get(route('superadmin.entries.index'))->assertOk()
            ->assertSee(route('admin.entries.edit', $entry))->assertSee('Delete');
        $this->get(route('admin.entries.edit', $entry))->assertOk();

        $this->put(route('admin.entries.update', $entry), [
            'category_id' => $other->id, 'entry_date' => today()->toDateString(), 'entry_time' => '14:10',
            'image' => \Illuminate\Http\UploadedFile::fake()->image('new.png'),
        ])->assertRedirect(route('superadmin.entries.index'));
        $entry->refresh();
        $this->assertSame($other->id, $entry->category_id);
        $this->assertSame('14:10', $entry->timeLabel());
        Storage::disk('uploads')->assertMissing('entries/old.png');
        Storage::disk('uploads')->assertExists(substr($entry->image_path, strlen('uploads/')));
        $this->assertDatabaseHas('activity_logs', ['action' => 'entry.updated']);

        $this->delete(route('admin.entries.destroy', $entry))->assertRedirect(route('superadmin.entries.index'));
        $this->assertNull($entry->fresh());
        Storage::disk('uploads')->assertMissing(substr($entry->image_path, strlen('uploads/')));
        $this->assertDatabaseHas('activity_logs', ['action' => 'entry.deleted']);
    }
}
