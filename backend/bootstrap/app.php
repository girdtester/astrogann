<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
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
        $middleware->api(prepend: [\App\Http\Middleware\ForceJsonResponse::class]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Guests hitting a protected page go to the login page; logged-in users hitting /login go home.
        $middleware->redirectUsersTo(fn ($request) => route($request->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // /api/* errors use the JSON envelope (docs/API.md).
        $exceptions->render(new \App\Exceptions\ApiExceptionRenderer);
    })->create();

/*
 * Shared hosting whose web root must be public_html (docs/DEPLOYMENT.md §3b): put the absolute path of that
 * folder in a file named ".public_path" next to artisan. Used by web requests AND artisan/cron, so uploads and
 * asset URLs always point at the real web root.
 */
if (is_file($marker = dirname(__DIR__)."/.public_path") && is_dir($publicPath = trim((string) file_get_contents($marker)))) {
    $app->usePublicPath($publicPath);
}

return $app;
