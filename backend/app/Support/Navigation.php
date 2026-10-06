<?php

namespace App\Support;

use App\Enums\RoleKind;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Route;

/**
 * Top navigation built from the user's permissions (DECISIONS D-42), grouped into dropdown menus so it fits
 * the screen without horizontal scrolling (D-70). A link is shown only when the user holds its permission;
 * a group with a single visible child collapses into a plain link; an empty group disappears.
 */
class Navigation
{
    /**
     * Flat list of visible links (used by the staff dashboard "Your screens").
     *
     * @return list<array{label: string, route: string, active: string, badge: ?int}>
     */
    public static function for(User $user): array
    {
        $flat = [];
        foreach (self::menu($user) as $item) {
            foreach ($item['children'] ?? [$item] as $link) {
                $flat[] = $link;
            }
        }

        return $flat;
    }

    /**
     * Menu tree: items are links {label, route, active, badge} or groups {label, active, children: links}.
     *
     * @return list<array<string, mixed>>
     */
    public static function menu(User $user): array
    {
        if ($user->kind() === RoleKind::User) {
            $tree = [
                self::link('Entries', 'user.dashboard', 'user.dashboard'),
                self::link('Subscription', 'user.subscription', 'user.subscription'),
                self::link('Notifications', 'user.notifications.index', 'user.notifications.*', null,
                    app(NotificationService::class)->unreadCount($user)),
            ];
        } else {
            $p = fn (string $key) => $user->hasPermission($key);
            $tree = [
                self::link('Dashboard', $user->homeRoute(), $user->homeRoute()),
                self::group('Accounts', [
                    self::link('Admins / Staff', 'superadmin.admins.index', 'superadmin.admins.*', Permissions::STAFF_MANAGE),
                    self::link('Users / Traders', 'superadmin.users.index', 'superadmin.users.*', Permissions::USERS_MANAGE),
                    self::link('Roles & Permissions', 'superadmin.roles.index', 'superadmin.roles.*', Permissions::ROLES_MANAGE),
                ]),
                self::group('Subscriptions', [
                    self::link('Subscriptions', 'superadmin.subscriptions.index', 'superadmin.subscriptions.*', Permissions::SUBSCRIPTIONS_MANAGE),
                    self::link('Expiry reminders', 'superadmin.reminders.index', 'superadmin.reminders.*', Permissions::SUBSCRIPTIONS_MANAGE),
                ]),
                self::group('Entries', [
                    self::link('Add Entry', 'admin.entries.create', 'admin.entries.create', Permissions::ENTRIES_CREATE),
                    $p(Permissions::ENTRIES_VIEW_ALL)
                        ? self::link('All Entries', 'superadmin.entries.index', 'superadmin.entries.*', Permissions::ENTRIES_VIEW_ALL)
                        : self::link('My Entries', 'admin.entries.index', 'admin.entries.index', Permissions::ENTRIES_VIEW_OWN),
                    $p(Permissions::CATEGORIES_MANAGE)
                        ? self::link('Categories', 'superadmin.categories.index', 'superadmin.categories.*', Permissions::CATEGORIES_MANAGE)
                        : self::link('Categories', 'admin.categories.index', 'admin.categories.*', Permissions::CATEGORIES_VIEW),
                ]),
                self::link('Reports / Logs', 'superadmin.logs.index', 'superadmin.logs.*', Permissions::LOGS_VIEW),
                self::link('Settings', 'superadmin.settings.edit', 'superadmin.settings.*', Permissions::SETTINGS_MANAGE),
            ];
        }

        $visible = fn (array $l) => Route::has($l['route']) && ($l['permission'] === null || $user->hasPermission($l['permission']));
        $menu = [];

        foreach ($tree as $item) {
            if (! isset($item['children'])) {
                if ($visible($item)) {
                    $menu[] = $item;
                }

                continue;
            }
            $children = array_values(array_filter($item['children'], $visible));
            if (count($children) === 1) {
                $menu[] = $children[0];
            } elseif ($children !== []) {
                $menu[] = [
                    'label' => $item['label'],
                    'children' => $children,
                    'active' => array_map(fn ($c) => $c['active'], $children), // route patterns for highlighting
                ];
            }
        }

        return $menu;
    }

    private static function link(string $label, string $route, string $active, ?string $permission = null, ?int $badge = null): array
    {
        return ['label' => $label, 'route' => $route, 'active' => $active, 'permission' => $permission, 'badge' => $badge];
    }

    private static function group(string $label, array $children): array
    {
        return ['label' => $label, 'children' => $children];
    }
}
