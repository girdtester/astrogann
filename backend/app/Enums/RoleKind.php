<?php

namespace App\Enums;

/**
 * The panel a role belongs to. Roles themselves (names, permissions) are data in the `roles`
 * table (DECISIONS D-42); the kind only decides which area the account uses.
 *
 * - superadmin: implicitly holds every permission; home /superadmin
 * - staff: back-office roles (Admin and any custom role) — access = granted permissions; home /admin
 * - user: subscribers/traders; home /user
 */
enum RoleKind: string
{
    case SuperAdmin = 'superadmin';
    case Staff = 'staff';
    case User = 'user';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Staff => 'Staff',
            self::User => 'User / Trader',
        };
    }

    /** Named route of this kind's dashboard. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::SuperAdmin => 'superadmin.dashboard',
            self::Staff => 'admin.dashboard',
            self::User => 'user.dashboard',
        };
    }
}
