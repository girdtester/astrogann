<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
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
            self::Admin => 'Admin',
            self::User => 'User / Trader',
        };
    }

    /** Named route of this role's dashboard. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::SuperAdmin => 'superadmin.dashboard',
            self::Admin => 'admin.dashboard',
            self::User => 'user.dashboard',
        };
    }
}
