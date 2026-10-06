<?php

namespace App\Providers;

use App\Models\User;
use App\Services\SettingsService;
use App\Support\Navigation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        // Role gates (docs/SECURITY.md §2). Model-level rules live in app/Policies.
        Gate::define('superadmin', fn (User $user) => $user->isSuperAdmin());
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('trader', fn (User $user) => $user->isTrader());

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        View::composer(['layouts.app', 'layouts.guest', 'layouts.navigation'], function ($view) {
            $view->with('appSettings', app(SettingsService::class)->public());
        });

        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();
            $view->with('navLinks', $user ? Navigation::for($user) : []);
        });
    }
}
