<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderService;
use Illuminate\Console\Command;

/**
 * Step 2 (every cron minute): send the next saved reminders one by one, MAIL_PER_MINUTE at a time (D-88).
 * The list is saved once a day by astrogann:collect-expiry-reminders (18:00).
 */
class SendExpiryReminders extends Command
{
    protected $signature = 'astrogann:send-expiry-reminders {--limit= : Max e-mails this run (default MAIL_PER_MINUTE)}';

    protected $description = 'Send pending "subscription expires soon" e-mails from the saved list, one by one';

    public function handle(ExpiryReminderService $reminders): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $r = $reminders->sendPending($limit);
        $this->info("Sent {$r['sent']} reminder e-mail(s), {$r['failed']} failed this run.");

        return self::SUCCESS;
    }
}
