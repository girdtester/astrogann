<?php

namespace App\Console\Commands;

use App\Jobs\SendPushToUsers;
use App\Mail\SubscriptionExpiringMail;
use App\Models\Subscription;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: e-mail users whose active subscription ends within `reminder_days` (setting, default 3) days.
 * Never twice for the same period: reminder_sent_at is claimed atomically and reset only on extend.
 */
class SendExpiryReminders extends Command
{
    protected $signature = 'astrogann:send-expiry-reminders';

    protected $description = 'Queue the "subscription will expire" e-mails (and in-app notification + push)';

    public function handle(SubscriptionService $subscriptions, SettingsService $settings, NotificationService $notifications, ActivityLogService $logs): int
    {
        $sent = 0;

        $subscriptions->dueForReminder($settings->reminderDays())->chunkById(100, function ($batch) use (&$sent, $notifications, $logs) {
            foreach ($batch as $sub) {
                // Atomic claim: only one run can flip reminder_sent_at from NULL.
                $claimed = Subscription::query()->whereKey($sub->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if (! $claimed) {
                    continue;
                }

                $user = $sub->user;
                $daysLeft = max(0, (int) today()->diffInDays($sub->end_date, false));
                $message = $daysLeft === 0
                    ? 'Your subscription expires today. Contact the admin to renew.'
                    : "Your subscription will expire in {$daysLeft} day".($daysLeft === 1 ? '' : 's').' ('.$sub->end_date->format('d M Y').'). Contact the admin to renew.';

                Mail::to($user)->queue(new SubscriptionExpiringMail($user, $sub, $daysLeft));
                $notifications->storeExpiryReminder($user, $message);
                SendPushToUsers::dispatch([$user->id], 'Subscription expiring', $message, ['type' => 'expiry']);
                $logs->log('subscription.reminder_sent', $sub, "Expiry reminder queued for {$user->email} ({$daysLeft} days left)", $user);
                $sent++;
            }
        });

        $this->info("Queued {$sent} expiry reminder(s).");

        return self::SUCCESS;
    }
}
