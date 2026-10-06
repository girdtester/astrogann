<?php

use App\Http\Controllers\Api;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

/*
 * REST API — contract in docs/API.md. Every response uses the { success, message, data, errors } envelope.
 * Authenticated routes: Sanctum token + single active session (session.single) + per-user rate limit.
 */

Route::get('settings', [Api\ContentController::class, 'settings'])->middleware('throttle:api-public');
Route::post('auth/login', [Api\AuthController::class, 'login'])->middleware('throttle:api-public');

Route::middleware(['auth:sanctum', 'session.single', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [Api\AuthController::class, 'logout']);
    Route::get('auth/me', [Api\AuthController::class, 'me']);
    Route::post('update-fcm-token', [Api\AuthController::class, 'updateFcmToken']);

    Route::get('categories', [Api\ContentController::class, 'categories']);
    Route::get('categories/{category}/entries', [Api\ContentController::class, 'entries'])->whereNumber('category');

    Route::post('entries', [Api\ContentController::class, 'storeEntry'])->middleware('can:'.P::ENTRIES_CREATE);

    Route::get('notifications', [Api\NotificationController::class, 'index']);
    Route::post('notifications/read-all', [Api\NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [Api\NotificationController::class, 'markRead'])->whereNumber('id');

    Route::get('subscription', [Api\ContentController::class, 'subscription'])->middleware('role:user');

    Route::prefix('admin')->middleware('role:superadmin,staff')->group(function () {
        Route::middleware('can:'.P::USERS_MANAGE)->group(function () {
            Route::get('users', [Api\Admin\AccountController::class, 'index'])->defaults('kind', 'user');
            Route::post('users', [Api\Admin\AccountController::class, 'store'])->defaults('kind', 'user');
            Route::patch('users/{account}', [Api\Admin\AccountController::class, 'update'])->whereNumber('account')->defaults('kind', 'user');
        });
        Route::middleware('can:'.P::STAFF_MANAGE)->group(function () {
            Route::get('admins', [Api\Admin\AccountController::class, 'index'])->defaults('kind', 'staff');
            Route::post('admins', [Api\Admin\AccountController::class, 'store'])->defaults('kind', 'staff');
            Route::patch('admins/{account}', [Api\Admin\AccountController::class, 'update'])->whereNumber('account')->defaults('kind', 'staff');
        });
        Route::post('subscription', Api\Admin\SubscriptionController::class)->middleware('can:'.P::SUBSCRIPTIONS_MANAGE);
    });
});
