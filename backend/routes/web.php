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

// URL cron (D-86): call every minute from cPanel or cron-job.org. Secret key from CRON_TOKEN; otherwise 404.
Route::get('/cron/run/{token}', \App\Http\Controllers\CronController::class)
    ->where('token', '[A-Za-z0-9]+')->middleware('throttle:20,1')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)->name('cron.run');

// Mail check for the live server (D-98): settings, reachable SMTP ports, optional test e-mail. Same secret as the cron link.
Route::get('/diagnostics/mail/{token}', [\App\Http\Controllers\MailDiagnosticsController::class, 'withToken'])
    ->where('token', '[A-Za-z0-9]+')->middleware('throttle:10,1')->name('diagnostics.mail.token');

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
            Route::get('reminders', [SuperAdmin\ExpiryReminderController::class, 'index'])->name('reminders.index');
            Route::post('reminders/collect', [SuperAdmin\ExpiryReminderController::class, 'collect'])->name('reminders.collect');
            Route::post('reminders/send', [SuperAdmin\ExpiryReminderController::class, 'send'])->name('reminders.send');
            Route::post('reminders/retry', [SuperAdmin\ExpiryReminderController::class, 'retry'])->name('reminders.retry');
            Route::post('reminders/test-mail', [SuperAdmin\ExpiryReminderController::class, 'testMail'])->name('reminders.test-mail');
            Route::get('diagnostics/mail', [\App\Http\Controllers\MailDiagnosticsController::class, 'report'])
                ->middleware(['role:superadmin', 'throttle:10,1'])->name('diagnostics.mail');
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
            // Push notifications / Firebase key (D-89)
            Route::post('settings/push/upload', [SuperAdmin\PushSettingsController::class, 'upload'])->name('settings.push.upload');
            Route::post('settings/push/toggle', [SuperAdmin\PushSettingsController::class, 'toggle'])->name('settings.push.toggle');
            Route::post('settings/push/check', [SuperAdmin\PushSettingsController::class, 'check'])->name('settings.push.check');
            Route::post('settings/push/test', [SuperAdmin\PushSettingsController::class, 'test'])->middleware('throttle:10,1')->name('settings.push.test');
            Route::delete('settings/push', [SuperAdmin\PushSettingsController::class, 'remove'])->name('settings.push.remove');
        });

        Route::middleware('can:'.P::ANNOUNCEMENTS_SEND)->group(function () {
            Route::get('announcements', [SuperAdmin\AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('announcements', [SuperAdmin\AnnouncementController::class, 'store'])->middleware('throttle:10,1')->name('announcements.store');
            Route::delete('announcements/{announcement}', [SuperAdmin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
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
        // Edit / delete need entries.edit / entries.delete + visibility of the entry (EntryPolicy, DECISIONS D-71).
        Route::get('entries/{entry}/edit', [Admin\EntryController::class, 'edit'])->middleware('can:update,entry')->name('entries.edit');
        Route::put('entries/{entry}', [Admin\EntryController::class, 'update'])->middleware('can:update,entry')->name('entries.update');
        Route::delete('entries/{entry}', [Admin\EntryController::class, 'destroy'])->middleware('can:delete,entry')->name('entries.destroy');
    });

    // User / Trader area (spec 3.2, 3.9).
    Route::prefix('user')->name('user.')->middleware('role:user')->group(function () {
        Route::get('/', [User\DashboardController::class, 'index'])->name('dashboard');
        Route::get('subscription', [User\AccountController::class, 'subscription'])->name('subscription');
        Route::get('notifications', [User\AccountController::class, 'notifications'])->name('notifications.index');
        Route::get('notifications/poll', [User\AccountController::class, 'poll'])->middleware('throttle:30,1')->name('notifications.poll');
        Route::post('notifications/read-all', [User\AccountController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [User\AccountController::class, 'markRead'])->whereNumber('id')->name('notifications.read');
    });
});

require __DIR__.'/auth.php';
