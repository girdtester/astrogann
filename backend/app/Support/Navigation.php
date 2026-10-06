<?php

namespace App\Support;

use App\Enums\RoleKind;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Top navigation built from the user's permissions (DECISIONS D-42): a link is shown only when
 * the user holds the permission its route requires, so custom roles get exactly their screens.
 */
class Navigation
{
    /** @return list<array{label: string, route: string, active: string}> */
    public static function for(User $user): array
    {
        if ($user->kind() === RoleKind::User) {
            $links = [
                ['Entries', 'user.dashboard', 'user.dashboard', null],
                ['Subscription', 'user.subscription', 'user.subscription', null],
                ['Notifications', 'user.notifications.index', 'user.notifications.*', null],
            ];
        } else {
            $links = [
                ['Dashboard', $user->homeRoute(), $user->homeRoute(), null],
                ['Admins / Staff', 'superadmin.admins.index', 'superadmin.admins.*', Permissions::STAFF_MANAGE],
                ['Users', 'superadmin.users.index', 'superadmin.users.*', Permissions::USERS_MANAGE],
                ['Subscriptions', 'superadmin.subscriptions.index', 'superadmin.subscriptions.*', Permissions::SUBSCRIPTIONS_MANAGE],
                $user->hasPermission(Permissions::CATEGORIES_MANAGE)
                    ? ['Categories', 'superadmin.categories.index', 'superadmin.categories.*', Permissions::CATEGORIES_MANAGE]
                    : ['Categories', 'admin.categories.index', 'admin.categories.*', Permissions::CATEGORIES_VIEW],
                ['Add Entry', 'admin.entries.create', 'admin.entries.create', Permissions::ENTRIES_CREATE],
                ['My Entries', 'admin.entries.index', 'admin.entries.index', Permissions::ENTRIES_VIEW_OWN],
                ['All Entries', 'superadmin.entries.index', 'superadmin.entries.*', Permissions::ENTRIES_VIEW_ALL],
                ['Reports / Logs', 'superadmin.logs.index', 'superadmin.logs.*', Permissions::LOGS_VIEW],
                ['Roles', 'superadmin.roles.index', 'superadmin.roles.*', Permissions::ROLES_MANAGE],
                ['Settings', 'superadmin.settings.edit', 'superadmin.settings.*', Permissions::SETTINGS_MANAGE],
            ];
        }

        $visible = array_filter($links, fn (array $l) => Route::has($l[1])
            && ($l[3] === null || $user->hasPermission($l[3])));

        return array_values(array_map(
            fn (array $l) => ['label' => $l[0], 'route' => $l[1], 'active' => $l[2]],
            $visible,
        ));
    }
}
