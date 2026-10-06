<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->role->homeRoute())
    : redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->role->homeRoute()))->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Super Admin area (spec 3.2 — full access).
    Route::prefix('superadmin')->name('superadmin.')->middleware('role:superadmin')->group(function () {
        Route::get('/', [SuperAdmin\DashboardController::class, 'index'])->name('dashboard');
    });

    // Admin area (spec 3.2 — data entry only).
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    });

    // User / Trader area (spec 3.2, 3.9).
    Route::prefix('user')->name('user.')->middleware('role:user')->group(function () {
        Route::get('/', [User\DashboardController::class, 'index'])->name('dashboard');
    });
});

require __DIR__.'/auth.php';
