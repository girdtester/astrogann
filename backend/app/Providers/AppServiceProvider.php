<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Push\FcmPushService;
use App\Services\Push\LogPushService;
use App\Services\Push\PushService;
use App\Services\SettingsService;
use App\Support\Navigation;
use App\Support\Permissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);

        // Push driver (DECISIONS D-28): PUSH_DRIVER=log (default) | fcm.
        $this->app->singleton(PushService::class, function () {
            $cfg = config('astrogann.push');

            return $cfg['driver'] === 'fcm' && $cfg['fcm_project_id'] && $cfg['fcm_credentials']
                ? new FcmPushService($cfg['fcm_project_id'], $cfg['fcm_credentials'])
                : new LogPushService;
        });
    }

    public function boot(): void
    {
        // One gate per catalogue permission (DECISIONS D-42). Routes use `can:<key>`, views @can('<key>').
        // Super Admin passes through User::permissionKeys(), which returns the whole catalogue.
        foreach (Permissions::keys() as $key) {
            Gate::define($key, fn (User $user) => $user->hasPermission($key));
        }

        // Kind gates (panel checks).
        Gate::define('superadmin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('staff', fn (User $user) => $user->isStaff());
        Gate::define('trader', fn (User $user) => $user->isTrader());

        // API rate limits (docs/SECURITY.md §3).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        // Outgoing e-mail pace (D-87): one by one, MAIL_PER_MINUTE (default 20) — under Gmail / GoDaddy limits.
        RateLimiter::for('outgoing-mail', fn () => Limit::perMinute(max(1, (int) config('astrogann.mail_per_minute'))));
        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        View::composer(['layouts.app', 'layouts.guest', 'layouts.navigation'], function ($view) {
            $view->with('appSettings', app(SettingsService::class)->public());
        });

        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();
            $view->with('navMenu', $user ? Navigation::menu($user) : []);
        });
    }
}
