<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Top navigation links per role. Each role only ever sees links to its own area.
 */
class Navigation
{
    /** @return list<array{label: string, route: string, active: string}> */
    public static function for(User $user): array
    {
        $links = match ($user->role) {
            Role::SuperAdmin => [
                ['Dashboard', 'superadmin.dashboard', 'superadmin.dashboard'],
                ['Admins', 'superadmin.admins.index', 'superadmin.admins.*'],
                ['Users', 'superadmin.users.index', 'superadmin.users.*'],
                ['Subscriptions', 'superadmin.subscriptions.index', 'superadmin.subscriptions.*'],
                ['Categories', 'superadmin.categories.index', 'superadmin.categories.*'],
                ['Entries', 'superadmin.entries.index', 'superadmin.entries.*'],
                ['Reports / Logs', 'superadmin.logs.index', 'superadmin.logs.*'],
                ['Settings', 'superadmin.settings.edit', 'superadmin.settings.*'],
            ],
            Role::Admin => [
                ['Dashboard', 'admin.dashboard', 'admin.dashboard'],
                ['Categories', 'admin.categories.index', 'admin.categories.*'],
                ['Add Entry', 'admin.entries.create', 'admin.entries.create'],
                ['My Entries', 'admin.entries.index', 'admin.entries.index'],
            ],
            Role::User => [
                ['Entries', 'user.dashboard', 'user.dashboard'],
                ['Subscription', 'user.subscription', 'user.subscription'],
                ['Notifications', 'user.notifications.index', 'user.notifications.*'],
            ],
        };

        return array_values(array_map(
            fn (array $l) => ['label' => $l[0], 'route' => $l[1], 'active' => $l[2]],
            array_filter($links, fn (array $l) => Route::has($l[1])),
        ));
    }
}
