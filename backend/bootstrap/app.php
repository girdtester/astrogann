<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'session.single' => \App\Http\Middleware\EnsureActiveSession::class,
        ]);

        // Every authenticated web request must belong to the user's single active session.
        $middleware->web(append: [\App\Http\Middleware\EnsureActiveSession::class]);

        // Guests hitting a protected page go to the login page; logged-in users hitting /login go home.
        $middleware->redirectUsersTo(fn ($request) => route($request->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
