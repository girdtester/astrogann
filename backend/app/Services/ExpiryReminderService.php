<?php

namespace App\Services;

use App\Jobs\SendPushToUsers;
use App\Mail\SubscriptionExpiringMail;
use App\Models\ExpiryReminder;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Expiry reminders in two steps (owner request, DECISIONS D-88):
 *  1. collect() — once a day (18:00): save every subscription ending within `reminder_days` that was not reminded
 *     yet. Each subscription period is saved only once (reminder_sent_at + unique subscription_id/end_date).
 *  2. sendPending() — every cron minute: send the next few saved reminders ONE BY ONE (MAIL_PER_MINUTE), mark each
 *     sent / failed. A failure is retried on the next runs, up to 3 attempts.
 */
class ExpiryReminderService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
        private readonly ActivityLogService $logs,
    ) {}

    /** @return int number of users added to the list */
    public function collect(): int
    {
        $added = 0;

        $this->subscriptions->dueForReminder($this->settings->reminderDays())->chunkById(500, function ($batch) use (&$added) {
            foreach ($batch as $sub) {
                // Claim the subscription period atomically: only one collect run can ever add it.
                $claimed = Subscription::query()->whereKey($sub->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if (! $claimed) {
                    continue;
                }
                $added += ExpiryReminder::query()->insertOrIgnore([[
                    'subscription_id' => $sub->id,
                    'user_id' => $sub->user_id,
                    'end_date' => $sub->end_date->toDateString(),
                    'days_left' => max(0, (int) today()->diffInDays($sub->end_date, false)),
                    'status' => ExpiryReminder::PENDING,
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);
            }
        });

        if ($added > 0) {
            $this->logs->log('subscription.reminders_collected', null, "{$added} user(s) saved for the expiry reminder e-mail");
        }

        return $added;
    }

    /**
     * Send up to $limit pending reminders, one after another.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendPending(?int $limit = null): array
    {
        $limit ??= max(1, (int) config('astrogann.mail_per_minute'));
        $result = ['sent' => 0, 'failed' => 0];

        $rows = ExpiryReminder::query()
            ->with(['user', 'subscription'])
            ->where('status', ExpiryReminder::PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            // Skip people who were deactivated or renewed since the list was saved.
            if (! $row->user?->isActive() || ! $row->subscription?->isCurrentlyActive()
                || $row->subscription->end_date->toDateString() !== $row->end_date->toDateString()) {
                $row->update(['status' => ExpiryReminder::FAILED, 'last_error' => 'Skipped: account inactive or subscription changed']);

                continue;
            }

            $daysLeft = max(0, (int) today()->diffInDays($row->end_date, false));

            // In-app notice (Notifications tab) + push at the same moment as the e-mail — once, even if the e-mail
            // itself has to be retried later (D-95).
            if ($row->notified_at === null) {
                DB::transaction(function () use ($row, $daysLeft) {
                    $this->notifications->storeExpiryReminder($row->user, $this->message($daysLeft, $row));
                    $row->update(['notified_at' => now()]);
                });
                SendPushToUsers::dispatch([$row->user_id], 'Subscription expiring', $this->message($daysLeft, $row), ['type' => 'expiry']);
            }

            try {
                Mail::to($row->user->email)->send(new SubscriptionExpiringMail($row->user, $row->subscription, $daysLeft));

                $row->update(['status' => ExpiryReminder::SENT, 'sent_at' => now(), 'attempts' => $row->attempts + 1, 'last_error' => null]);
                $this->logs->log('subscription.reminder_sent', $row->subscription, "Expiry e-mail sent to {$row->user->email} ({$daysLeft} days left)", $row->user);
                $result['sent']++;
            } catch (Throwable $e) {
                $attempts = $row->attempts + 1;
                $row->update([
                    'attempts' => $attempts,
                    'last_error' => Str::limit($e->getMessage(), 490),
                    'status' => $attempts >= ExpiryReminder::MAX_ATTEMPTS ? ExpiryReminder::FAILED : ExpiryReminder::PENDING,
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /** Put failed rows back in the queue (Super Admin "Retry failed"). */
    public function retryFailed(): int
    {
        return ExpiryReminder::query()->where('status', ExpiryReminder::FAILED)
            ->update(['status' => ExpiryReminder::PENDING, 'attempts' => 0, 'last_error' => null, 'updated_at' => now()]);
    }

    private function message(int $daysLeft, ExpiryReminder $row): string
    {
        return $daysLeft === 0
            ? 'Your subscription expires today. Contact the admin to renew.'
            : "Your subscription will expire in {$daysLeft} day".($daysLeft === 1 ? '' : 's').' ('.$row->end_date->format('d M Y').'). Contact the admin to renew.';
    }
}
