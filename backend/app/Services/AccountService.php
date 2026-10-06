<?php

namespace App\Services;

use App\Enums\ActiveStatus;
use App\Enums\RoleKind;
use App\Models\Role;
use App\Models\User;
use InvalidArgumentException;

/**
 * Management of staff (admin + custom roles) and user/trader accounts (spec 3.2, DECISIONS D-42).
 *
 * Guard rails: Super Admin accounts are never managed here; nobody manages their own account here;
 * an actor can only manage accounts whose permissions are a subset of its own, and can only assign
 * roles / extra permissions it holds itself (no privilege escalation).
 */
class AccountService
{
    public function __construct(
        private readonly ActivityLogService $logs,
        private readonly SessionService $sessions,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, phone?: ?string, status?: string, role_id?: ?int, permissions?: list<string>}  $data
     */
    public function create(RoleKind $kind, array $data, User $by): User
    {
        $role = $this->resolveRole($kind, $data['role_id'] ?? null, $by);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $role->id,
            'status' => $data['status'] ?? ActiveStatus::Active->value,
        ]);

        if ($kind === RoleKind::Staff && array_key_exists('permissions', $data)) {
            $this->permissions->syncUserPermissions($user, $data['permissions'] ?? [], $by);
        }

        $this->logs->log("{$kind->value}.created", $user, "Created {$role->name} {$user->email}", $by);

        return $user;
    }

    /** Update name/email/phone/status/role/extra permissions and optionally the password. */
    public function update(User $user, array $data, User $by): User
    {
        $this->assertCanManage($user, $by);
        $wasActive = $user->isActive();

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'status' => $data['status'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        if ($user->isStaff() && filled($data['role_id'] ?? null)) {
            $user->role_id = $this->resolveRole(RoleKind::Staff, (int) $data['role_id'], $by)->id;
        }
        $changes = array_keys($user->getDirty());
        $user->save();
        $user->flushPermissionCache();

        if ($user->isStaff() && array_key_exists('permissions', $data)) {
            $this->permissions->syncUserPermissions($user, $data['permissions'] ?? [], $by);
        }

        if ($wasActive && ! $user->isActive()) {
            $this->sessions->endAll($user);
        }

        $fields = $changes ? ' ('.implode(', ', $changes).')' : '';
        $this->logs->log("{$user->kind()->value}.updated", $user, "Updated {$user->email}{$fields}", $by);

        return $user;
    }

    public function setStatus(User $user, ActiveStatus $status, User $by): User
    {
        $this->assertCanManage($user, $by);
        $user->update(['status' => $status]);

        if ($status === ActiveStatus::Inactive) {
            $this->sessions->endAll($user);
        }

        $verb = $status === ActiveStatus::Active ? 'activated' : 'deactivated';
        $this->logs->log("{$user->kind()->value}.{$verb}", $user, ucfirst($verb)." {$user->email}", $by);

        return $user;
    }

    public function canManage(User $user, User $by): bool
    {
        return ! $user->isSuperAdmin()
            && $user->id !== $by->id
            && $this->permissions->canGrant($by, $user->permissionKeys());
    }

    /** Accounts with history (entries, subscriptions, payments) are deactivated, never deleted. */
    public function canDelete(User $user): bool
    {
        return ! $user->isSuperAdmin()
            && ! $user->entries()->exists()
            && ! $user->subscriptions()->exists()
            && ! $user->payments()->exists();
    }

    public function delete(User $user, User $by): void
    {
        $this->assertCanManage($user, $by);
        if (! $this->canDelete($user)) {
            throw new InvalidArgumentException('This account has history (entries, subscriptions or payments). Deactivate it instead.');
        }

        $this->sessions->endAll($user);
        $this->logs->log("{$user->kind()->value}.deleted", null, "Deleted {$user->role->name} {$user->email}", $by);
        $user->delete();
    }

    private function resolveRole(RoleKind $kind, ?int $roleId, User $by): Role
    {
        return match ($kind) {
            RoleKind::User => Role::findBySlug(Role::USER),
            RoleKind::Staff => $this->staffRole($roleId, $by),
            RoleKind::SuperAdmin => throw new InvalidArgumentException('Super Admin accounts cannot be created from the application.'),
        };
    }

    private function staffRole(?int $roleId, User $by): Role
    {
        $role = $roleId ? Role::query()->ofKind(RoleKind::Staff)->find($roleId) : Role::findBySlug(Role::ADMIN);
        if (! $role) {
            throw new InvalidArgumentException('Choose a valid staff role.');
        }
        $this->permissions->assertCanGrant($by, $role->permissions()->pluck('key')->all());

        return $role;
    }

    private function assertCanManage(User $user, User $by): void
    {
        if ($user->isSuperAdmin()) {
            throw new InvalidArgumentException('Super Admin accounts are not managed here.');
        }
        if ($user->id === $by->id) {
            throw new InvalidArgumentException('You cannot change your own account here. Use your profile page.');
        }
        if (! $this->permissions->canGrant($by, $user->permissionKeys())) {
            throw new InvalidArgumentException('This account has permissions you do not have, so you cannot manage it.');
        }
    }
}
