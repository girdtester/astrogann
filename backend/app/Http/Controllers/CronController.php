<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * URL cron for hosting without a cron job (DECISIONS D-86): https://your-domain/cron/run/{CRON_TOKEN}
 * Call it every minute from cPanel "wget"/"curl" or a free service like cron-job.org. It runs Laravel's scheduler,
 * i.e. exactly what `php artisan schedule:run` does: expiry reminders (09:00), expire subscriptions (00:05),
 * queued e-mails / pushes / notifications (every minute), clean-up (02:30).
 */
class CronController extends Controller
{
    public function __invoke(string $token): Response
    {
        $expected = (string) config('astrogann.cron_token');

        // Unknown / missing key → look like a missing page.
        if (strlen($expected) < 32 || ! hash_equals($expected, $token)) {
            abort(404);
        }

        // Keep going even if the cron service closes the connection early; shared hosts often cap at 30–60 s.
        ignore_user_abort(true);
        @set_time_limit(300);

        $started = microtime(true);
        Artisan::call('schedule:run');
        $output = trim(preg_replace('/\e\[[0-9;]*m/', '', Artisan::output()));
        Log::channel('single')->info('[cron:url] schedule:run', ['seconds' => round(microtime(true) - $started, 2)]);

        return response('OK '.now()->toDateTimeString()."\n".$output."\n", 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
