<?php

namespace App\Services;

use App\Enums\RoleKind;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Dynamic roles & permissions (DECISIONS D-42). First-party only: Gates + these tables.
 *
 * Escalation rule: an actor can only grant permissions it holds itself, and can only assign
 * roles whose permissions it holds. The Super Admin holds everything.
 */
class PermissionService
{
    public function __construct(private readonly ActivityLogService $logs) {}

    /** Mirror the code catalogue into the permissions table. Idempotent; safe in production. */
    public function syncCatalog(): void
    {
        $sort = 0;
        foreach (Permissions::catalog() as $key => $meta) {
            Permission::updateOrCreate(['key' => $key], $meta + ['sort' => $sort++]);
        }
        Permission::query()->whereNotIn('key', Permissions::keys())->delete();
    }

    /** Create the three system roles if missing (Admin gets its default permissions only when created). */
    public function ensureSystemRoles(): void
    {
        $this->syncCatalog();

        Role::firstOrCreate(['slug' => Role::SUPERADMIN], [
            'name' => 'Super Admin', 'kind' => RoleKind::SuperAdmin, 'is_system' => true,
            'description' => 'Full access to everything.',
        ]);

        $admin = Role::firstOrCreate(['slug' => Role::ADMIN], [
            'name' => 'Admin', 'kind' => RoleKind::Staff, 'is_system' => true,
            'description' => 'Data entry: view categories, add entries, view own entries.',
        ]);
        if ($admin->wasRecentlyCreated) {
            $admin->permissions()->sync(Permission::whereIn('key', Permissions::adminDefaults())->pluck('id'));
        }

        Role::firstOrCreate(['slug' => Role::USER], [
            'name' => 'User / Trader', 'kind' => RoleKind::User, 'is_system' => true,
            'description' => 'Subscriber. Sees entries according to the subscription.',
        ]);
    }

    /** Permissions grouped for the checkbox UI. */
    public function grouped(): \Illuminate\Support\Collection
    {
        return Permission::query()->orderBy('sort')->get()->groupBy('group');
    }

    /** Staff roles the actor may assign (all their permissions are held by the actor). */
    public function assignableStaffRoles(User $actor): Collection
    {
        return Role::query()->ofKind(RoleKind::Staff)->with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $role) => $this->canGrant($actor, $role->permissions->pluck('key')->all()))
            ->values();
    }

    /** @param list<string> $keys */
    public function canGrant(User $actor, array $keys): bool
    {
        return array_diff($keys, $actor->permissionKeys()) === [];
    }

    /** @param list<string> $keys */
    public function assertCanGrant(User $actor, array $keys): void
    {
        $unknown = array_diff($keys, Permissions::keys());
        if ($unknown) {
            throw new InvalidArgumentException('Unknown permission: '.implode(', ', $unknown));
        }
        if (! $this->canGrant($actor, $keys)) {
            throw new InvalidArgumentException('You cannot grant permissions you do not have yourself.');
        }
    }

    /** @param list<string> $permissionKeys */
    public function createRole(array $data, array $permissionKeys, User $actor): Role
    {
        $this->assertCanGrant($actor, $permissionKeys);

        return DB::transaction(function () use ($data, $permissionKeys, $actor) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'kind' => RoleKind::Staff, // custom roles are always back-office roles
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);
            $this->syncRolePermissions($role, $permissionKeys);
            $this->logs->log('role.created', $role, "Created role {$role->name} [".implode(', ', $permissionKeys).']', $actor);

            return $role;
        });
    }

    /** @param list<string> $permissionKeys */
    public function updateRole(Role $role, array $data, array $permissionKeys, User $actor): Role
    {
        if (! $role->hasEditablePermissions()) {
            throw new InvalidArgumentException("The {$role->name} role's permissions cannot be changed.");
        }
        // The actor must hold both what is added and what is already there (no editing above your level).
        $this->assertCanGrant($actor, array_values(array_unique([...$permissionKeys, ...$role->permissions()->pluck('key')->all()])));
        if ($actor->role_id === $role->id && ! $actor->isSuperAdmin()) {
            throw new InvalidArgumentException('You cannot change the permissions of your own role.');
        }

        return DB::transaction(function () use ($role, $data, $permissionKeys, $actor) {
            $role->update([
                'name' => $data['name'] ?? $role->name,
                'description' => $data['description'] ?? $role->description,
            ]);
            $this->syncRolePermissions($role, $permissionKeys);
            $this->logs->log('role.updated', $role, "Updated role {$role->name} [".implode(', ', $permissionKeys).']', $actor);

            return $role;
        });
    }

    public function deleteRole(Role $role, User $actor): void
    {
        if ($role->is_system) {
            throw new InvalidArgumentException('System roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            throw new InvalidArgumentException('Move the accounts using this role to another role first.');
        }
        $this->assertCanGrant($actor, $role->permissions()->pluck('key')->all());

        $this->logs->log('role.deleted', null, "Deleted role {$role->name}", $actor);
        $role->delete();
    }

    /**
     * Replace a staff user's extra (direct) permissions.
     *
     * @param  list<string>  $permissionKeys
     */
    public function syncUserPermissions(User $user, array $permissionKeys, User $actor): void
    {
        if (! $user->isStaff()) {
            throw new InvalidArgumentException('Extra permissions can only be given to staff accounts.');
        }
        $current = $user->directPermissions()->pluck('key')->all();
        $this->assertCanGrant($actor, array_values(array_unique([...$permissionKeys, ...$current])));

        $user->directPermissions()->sync(Permission::whereIn('key', $permissionKeys)->pluck('id'));
        $user->flushPermissionCache();

        if (array_diff($permissionKeys, $current) || array_diff($current, $permissionKeys)) {
            $this->logs->log('user.permissions_changed', $user,
                "Extra permissions for {$user->email}: [".implode(', ', $permissionKeys).']', $actor);
        }
    }

    /** @param list<string> $permissionKeys */
    private function syncRolePermissions(Role $role, array $permissionKeys): void
    {
        $role->permissions()->sync(Permission::whereIn('key', $permissionKeys)->pluck('id'));
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'role';
        $slug = $base;
        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
