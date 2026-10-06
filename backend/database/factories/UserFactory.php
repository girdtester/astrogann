<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state (a user/trader).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn () => self::systemRoleId(Role::USER),
            'status' => ActiveStatus::Active->value,
            'remember_token' => Str::random(10),
        ];
    }

    /** Make sure the permission catalogue and system roles exist, then return a role id. */
    public static function systemRoleId(string $slug): int
    {
        if (! Role::where('slug', $slug)->exists()) {
            app(PermissionService::class)->ensureSystemRoles();
        }

        return Role::where('slug', $slug)->value('id');
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role_id' => self::systemRoleId(Role::SUPERADMIN)]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => self::systemRoleId(Role::ADMIN)]);
    }

    public function trader(): static
    {
        return $this->state(fn () => ['role_id' => self::systemRoleId(Role::USER)]);
    }

    /** A staff account with a custom role holding exactly these permissions. */
    public function staffWith(string ...$permissionKeys): static
    {
        return $this->state(function () use ($permissionKeys) {
            self::systemRoleId(Role::ADMIN); // ensures the catalogue is synced
            $role = Role::create(['name' => 'Custom '.Str::random(5), 'slug' => 'custom-'.Str::lower(Str::random(8)), 'kind' => 'staff']);
            $role->permissions()->sync(Permission::whereIn('key', $permissionKeys)->pluck('id'));

            return ['role_id' => $role->id];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ActiveStatus::Inactive->value]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
