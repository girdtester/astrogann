<?php

namespace App\Support;

/**
 * Catalogue of every permission the code checks. Permissions are tied to features, so they are
 * defined here and synced into the `permissions` table (PermissionService::syncCatalog). Which
 * role / user holds them is data managed by the Super Admin (DECISIONS D-42).
 *
 * Adding a feature = add a key here, guard the route with `can:<key>`, run the sync (seeder).
 */
final class Permissions
{
    public const STAFF_MANAGE = 'staff.manage';

    public const USERS_MANAGE = 'users.manage';

    public const SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';

    public const CATEGORIES_VIEW = 'categories.view';

    public const CATEGORIES_MANAGE = 'categories.manage';

    public const ENTRIES_CREATE = 'entries.create';

    public const ENTRIES_VIEW_OWN = 'entries.view_own';

    public const ENTRIES_VIEW_ALL = 'entries.view_all';

    public const LOGS_VIEW = 'logs.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const ROLES_MANAGE = 'roles.manage';

    /** @return array<string, array{group: string, label: string, description: string}> */
    public static function catalog(): array
    {
        return [
            self::STAFF_MANAGE => ['group' => 'Accounts', 'label' => 'Manage admins / staff',
                'description' => 'Create, edit, activate/deactivate staff accounts and give them roles and extra permissions.'],
            self::USERS_MANAGE => ['group' => 'Accounts', 'label' => 'Manage users / traders',
                'description' => 'Create, edit, activate/deactivate user (trader) accounts.'],
            self::SUBSCRIPTIONS_MANAGE => ['group' => 'Subscriptions', 'label' => 'Manage subscriptions & payments',
                'description' => 'Activate, extend, expire subscriptions and record payments.'],
            self::CATEGORIES_VIEW => ['group' => 'Categories', 'label' => 'View categories',
                'description' => 'See the list of categories.'],
            self::CATEGORIES_MANAGE => ['group' => 'Categories', 'label' => 'Manage categories',
                'description' => 'Create, rename, reorder, activate/deactivate categories (changes user tabs).'],
            self::ENTRIES_CREATE => ['group' => 'Entries', 'label' => 'Add entries',
                'description' => 'Add new entries (category, date, time, image). Entries can never be edited or deleted.'],
            self::ENTRIES_VIEW_OWN => ['group' => 'Entries', 'label' => 'View own entries',
                'description' => 'See the entries this account added.'],
            self::ENTRIES_VIEW_ALL => ['group' => 'Entries', 'label' => 'View all entries',
                'description' => 'See and filter every entry from every admin.'],
            self::LOGS_VIEW => ['group' => 'Reports', 'label' => 'View reports / logs',
                'description' => 'See the activity log and dashboard counts.'],
            self::SETTINGS_MANAGE => ['group' => 'System', 'label' => 'System settings',
                'description' => 'Change entry limits, reminder days, contact details and app texts.'],
            self::ROLES_MANAGE => ['group' => 'System', 'label' => 'Manage roles & permissions',
                'description' => 'Create staff roles and choose their permissions.'],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }

    /**
     * Default permissions of the built-in "Admin" role — the spec's "data entry only" admin (spec 3.2).
     * The Super Admin can change them later from the Roles screen.
     *
     * @return list<string>
     */
    public static function adminDefaults(): array
    {
        return [self::CATEGORIES_VIEW, self::ENTRIES_CREATE, self::ENTRIES_VIEW_OWN];
    }
}
