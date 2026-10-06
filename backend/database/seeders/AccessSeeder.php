<?php

namespace Database\Seeders;

use App\Services\PermissionService;
use Illuminate\Database\Seeder;

/**
 * Syncs the permission catalogue and creates missing system roles (Super Admin, Admin, User/Trader).
 * Safe for production: never removes custom roles or changes existing role permissions.
 * Run after every deploy that adds permissions: php artisan db:seed --class=AccessSeeder --force
 */
class AccessSeeder extends Seeder
{
    public function run(PermissionService $permissions): void
    {
        $permissions->ensureSystemRoles();
    }
}
