<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderService;
use Illuminate\Console\Command;

/**
 * Step 1 (daily 18:00): save every user whose subscription expires within `reminder_days` (D-88).
 */
class CollectExpiryReminders extends Command
{
    protected $signature = 'astrogann:collect-expiry-reminders';

    protected $description = 'Save the list of users whose subscription is about to expire (once per subscription period)';

    public function handle(ExpiryReminderService $reminders): int
    {
        $this->info('Saved '.$reminders->collect().' user(s) for the expiry reminder e-mail.');

        return self::SUCCESS;
    }
}
