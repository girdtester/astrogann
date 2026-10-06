<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\User;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->homeRoute())
    : redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->homeRoute()))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    /*
     * Management area (spec 3.2 Super Admin). Open to the Super Admin and to staff roles, but every
     * page is guarded by its own permission (DECISIONS D-42). Traders never get in.
     */
    Route::prefix('superadmin')->name('superadmin.')->middleware('role:superadmin,staff')->group(function () {
        Route::get('/', [SuperAdmin\DashboardController::class, 'index'])->middleware('role:superadmin')->name('dashboard');

        Route::middleware('can:'.P::STAFF_MANAGE)->group(function () {
            Route::patch('admins/{account}/status', [SuperAdmin\AdminAccountController::class, 'toggleStatus'])->name('admins.status');
            Route::resource('admins', SuperAdmin\AdminAccountController::class)->except('show')->parameters(['admins' => 'account']);
        });

        Route::middleware('can:'.P::USERS_MANAGE)->group(function () {
            Route::patch('users/{account}/status', [SuperAdmin\UserAccountController::class, 'toggleStatus'])->name('users.status');
            Route::resource('users', SuperAdmin\UserAccountController::class)->except('show')->parameters(['users' => 'account']);
        });

        Route::middleware('can:'.P::SUBSCRIPTIONS_MANAGE)->group(function () {
            Route::get('subscriptions', [SuperAdmin\SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::get('subscriptions/{user}', [SuperAdmin\SubscriptionController::class, 'show'])->name('subscriptions.show');
            Route::post('subscriptions/{user}', [SuperAdmin\SubscriptionController::class, 'action'])->name('subscriptions.action');
            Route::post('subscriptions/{user}/payments', [SuperAdmin\SubscriptionController::class, 'storePayment'])->name('subscriptions.payments.store');
        });

        Route::middleware('can:'.P::CATEGORIES_MANAGE)->group(function () {
            Route::patch('categories/{category}/status', [SuperAdmin\CategoryController::class, 'toggleStatus'])->name('categories.status');
            Route::resource('categories', SuperAdmin\CategoryController::class)->except('show');
        });

        Route::get('entries', [SuperAdmin\EntryController::class, 'index'])->middleware('can:'.P::ENTRIES_VIEW_ALL)->name('entries.index');
        Route::get('logs', [SuperAdmin\ActivityLogController::class, 'index'])->middleware('can:'.P::LOGS_VIEW)->name('logs.index');

        Route::middleware('can:'.P::SETTINGS_MANAGE)->group(function () {
            Route::get('settings', [SuperAdmin\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SuperAdmin\SettingsController::class, 'update'])->name('settings.update');
        });

        Route::resource('roles', SuperAdmin\RoleController::class)->except('show')->middleware('can:'.P::ROLES_MANAGE);
    });

    // Staff (Admin) area (spec 3.2 Admin — data entry). Pages guarded by permission.
    Route::prefix('admin')->name('admin.')->middleware('role:staff,superadmin')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->middleware('role:staff')->name('dashboard');
        Route::get('categories', [Admin\CategoryController::class, 'index'])->middleware('can:'.P::CATEGORIES_VIEW)->name('categories.index');
        Route::get('entries/create', [Admin\EntryController::class, 'create'])->middleware('can:'.P::ENTRIES_CREATE)->name('entries.create');
        Route::post('entries', [Admin\EntryController::class, 'store'])->middleware(['can:'.P::ENTRIES_CREATE, 'throttle:30,1'])->name('entries.store');
        Route::get('entries', [Admin\EntryController::class, 'index'])->middleware('can:'.P::ENTRIES_VIEW_OWN)->name('entries.index');
        // No edit / update / delete routes: entries are immutable (DECISIONS D-19).
    });

    // User / Trader area (spec 3.2, 3.9).
    Route::prefix('user')->name('user.')->middleware('role:user')->group(function () {
        Route::get('/', [User\DashboardController::class, 'index'])->name('dashboard');
        Route::get('subscription', [User\AccountController::class, 'subscription'])->name('subscription');
        Route::get('notifications', [User\AccountController::class, 'notifications'])->name('notifications.index');
        Route::post('notifications/read-all', [User\AccountController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [User\AccountController::class, 'markRead'])->whereNumber('id')->name('notifications.read');
    });
});

require __DIR__.'/auth.php';
