<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Scheduler — production runs ONE cPanel cron every minute: `php artisan schedule:run` (docs/DEPLOYMENT.md).
 * Locally: `php artisan schedule:work`. Times are in APP_TIMEZONE (Asia/Kolkata).
 */

// Database queue worker without a daemon: drain the queue each minute, stop when empty or after 50 s.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('queue-drain');

// Spec 3.4 daily jobs.
Schedule::command('astrogann:expire-subscriptions')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('astrogann:send-expiry-reminders')->dailyAt('09:00')->withoutOverlapping();

// Housekeeping.
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('astrogann:prune')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
