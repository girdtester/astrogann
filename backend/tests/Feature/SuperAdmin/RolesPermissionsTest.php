<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions as P;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dynamic roles & permissions (DECISIONS D-42).
 */
class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_default_admin_is_data_entry_only(): void
    {
        $admin = User::factory()->admin()->create();
        $this->signIn($admin);

        foreach (['superadmin.users.index', 'superadmin.admins.index', 'superadmin.subscriptions.index', 'superadmin.categories.index',
            'superadmin.entries.index', 'superadmin.logs.index', 'superadmin.settings.edit', 'superadmin.roles.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get('/superadmin')->assertForbidden();
    }

    public function test_super_admin_can_create_a_role_and_staff_with_it_gets_exactly_those_screens(): void
    {
        $sa = User::factory()->superAdmin()->create();
        $this->signIn($sa)->post(route('superadmin.roles.store'), [
            'name' => 'Subscription Desk', 'description' => 'Handles renewals',
            'permissions' => [P::USERS_MANAGE, P::SUBSCRIPTIONS_MANAGE],
        ])->assertRedirect(route('superadmin.roles.index'));

        $role = Role::where('name', 'Subscription Desk')->sole();
        $this->assertSame('staff', $role->kind->value);
        $this->assertFalse($role->is_system);

        $staff = User::factory()->create(['role_id' => $role->id]);
        $this->flushSession();
        $this->signIn($staff);

        $this->get(route('superadmin.users.index'))->assertOk();
        $this->get(route('superadmin.subscriptions.index'))->assertOk();
        $this->get(route('superadmin.categories.index'))->assertForbidden();
        $this->get(route('superadmin.settings.edit'))->assertForbidden();
        $this->get('/admin')->assertOk(); // staff dashboard
        $this->get('/user')->assertForbidden();
    }

    public function test_extra_permission_can_be_given_to_one_admin(): void
    {
        $sa = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->signIn($sa)->put(route('superadmin.admins.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'status' => 'active',
            'role_id' => $admin->role_id, 'permissions' => [P::LOGS_VIEW],
        ])->assertRedirect(route('superadmin.admins.index'));

        $this->assertTrue($admin->fresh()->hasPermission(P::LOGS_VIEW));
        $this->assertTrue($admin->fresh()->hasPermission(P::ENTRIES_CREATE)); // still has role permissions
        $this->assertFalse($other->fresh()->hasPermission(P::LOGS_VIEW));

        $this->flushSession();
        $this->signIn($admin->fresh())->get(route('superadmin.logs.index'))->assertOk();
    }

    public function test_changing_role_permissions_applies_to_all_its_users(): void
    {
        $sa = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $adminRole = $admin->role;

        $this->signIn($sa)->put(route('superadmin.roles.update', $adminRole), [
            'name' => 'Admin', 'permissions' => [...P::adminDefaults(), P::ENTRIES_VIEW_ALL],
        ])->assertRedirect();

        $this->assertTrue($admin->fresh()->hasPermission(P::ENTRIES_VIEW_ALL));
    }

    public function test_staff_cannot_grant_permissions_they_do_not_hold(): void
    {
        $manager = User::factory()->staffWith(P::STAFF_MANAGE, P::ENTRIES_CREATE)->create();
        $admin = User::factory()->admin()->create(); // categories.view + entries.* — manager lacks categories.view & view_own
        $plain = User::factory()->staffWith(P::ENTRIES_CREATE)->create();

        $this->signIn($manager);

        // Cannot give logs.view (manager does not hold it).
        $this->put(route('superadmin.admins.update', $plain), [
            'name' => $plain->name, 'email' => $plain->email, 'permissions' => [P::LOGS_VIEW],
        ])->assertSessionHas('error');
        $this->assertFalse($plain->fresh()->hasPermission(P::LOGS_VIEW));

        // Cannot manage an account that has permissions the manager does not hold.
        $this->patch(route('superadmin.admins.status', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isActive());

        // Cannot create a role with permissions it does not hold, and has no roles.manage anyway.
        $this->post(route('superadmin.roles.store'), ['name' => 'X', 'permissions' => [P::SETTINGS_MANAGE]])->assertForbidden();
    }

    public function test_nobody_can_edit_their_own_account_or_super_admins_here(): void
    {
        $sa = User::factory()->superAdmin()->create();
        $manager = User::factory()->staffWith(P::STAFF_MANAGE)->create();

        $this->signIn($manager);
        $this->patch(route('superadmin.admins.status', $manager))->assertSessionHas('error');
        $this->assertTrue($manager->fresh()->isActive());
        $this->get(route('superadmin.admins.edit', $sa))->assertNotFound(); // super admins are not in the staff list
    }

    public function test_system_roles_cannot_be_deleted_and_roles_in_use_cannot_be_deleted(): void
    {
        $sa = User::factory()->superAdmin()->create();
        $this->signIn($sa);

        $this->delete(route('superadmin.roles.destroy', Role::findBySlug(Role::ADMIN)))->assertSessionHas('error');

        $custom = Role::create(['name' => 'Temp', 'slug' => 'temp', 'kind' => 'staff']);
        User::factory()->create(['role_id' => $custom->id]);
        $this->delete(route('superadmin.roles.destroy', $custom))->assertSessionHas('error');

        $unused = Role::create(['name' => 'Unused', 'slug' => 'unused', 'kind' => 'staff']);
        $this->delete(route('superadmin.roles.destroy', $unused))->assertRedirect(route('superadmin.roles.index'));
        $this->assertNull($unused->fresh());
    }

    public function test_super_admin_role_permissions_are_not_editable(): void
    {
        $sa = User::factory()->superAdmin()->create();
        $this->signIn($sa)->put(route('superadmin.roles.update', Role::findBySlug(Role::SUPERADMIN)), ['name' => 'Super Admin', 'permissions' => []])
            ->assertSessionHas('error');
        $this->assertTrue($sa->fresh()->hasPermission(P::ROLES_MANAGE));
    }

    public function test_navigation_shows_only_permitted_links(): void
    {
        $staff = User::factory()->staffWith(P::USERS_MANAGE)->create();
        $this->signIn($staff)->get('/admin')->assertOk()
            ->assertSee(route('superadmin.users.index'))
            ->assertDontSee(route('superadmin.settings.edit'))
            ->assertDontSee(route('superadmin.roles.index'));
    }
}
