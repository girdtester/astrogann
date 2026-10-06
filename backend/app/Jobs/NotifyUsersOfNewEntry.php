<?php

namespace App\Jobs;

use App\Models\Entry;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notification fan-out for one new entry, one chunk of users per run (spec 3.7).
 *
 * The first chunk runs right after the admin's request (dispatchAfterResponse, D-73) so users see it at once;
 * later chunks re-dispatch themselves onto the database queue, so every run stays well inside shared-hosting
 * time limits. Active subscribers get "New entry added…" + push; with the notify_expired_users setting on,
 * expired / never-subscribed users get a "renew your subscription" notification without push (D-18, D-73).
 */
class NotifyUsersOfNewEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 50;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $entryId, public readonly int $afterUserId = 0) {}

    public function handle(SubscriptionService $subscriptions, NotificationService $notifications, SettingsService $settings): void
    {
        $entry = Entry::with('category')->find($this->entryId);
        if (! $entry) {
            return;
        }

        $chunk = max(1, (int) config('astrogann.notification_chunk', 200));
        $includeInactive = $settings->bool('notify_expired_users');

        // One chunk of traders (id-ordered). Active subscribers always; expired / never-subscribed users too when
        // the "notify_expired_users" setting is on (they get a "renew" message and no push — DECISIONS D-73).
        $userIds = ($includeInactive ? $subscriptions->notifiableTraders() : $subscriptions->eligibleUsersFor($entry->category))
            ->where('users.id', '>', $this->afterUserId)
            ->orderBy('users.id')
            ->limit($chunk)
            ->pluck('users.id')
            ->all();

        if ($userIds === []) {
            return;
        }

        $activeIds = $includeInactive ? $subscriptions->filterActive($userIds, $entry->category) : $userIds;
        $inactiveIds = array_values(array_diff($userIds, $activeIds));

        // Only users notified *now* get a push, so a retried job never pushes twice.
        $newlyNotifiedActive = DB::transaction(function () use ($notifications, $entry, $activeIds, $inactiveIds) {
            $notifications->storeNewEntryFor($entry, $inactiveIds, NotificationService::renewMessage($entry));

            return $notifications->storeNewEntryFor($entry, $activeIds);
        });

        if ($newlyNotifiedActive !== []) {
            $push = new SendPushToUsers(
                $newlyNotifiedActive,
                $entry->category->name,
                NotificationService::newEntryMessage($entry),
                ['type' => 'new_entry', 'category_id' => (string) $entry->category_id, 'entry_id' => (string) $entry->id],
            );
            // Running right after the admin's request (first chunk)? Push immediately too; on the queue, queue it.
            $this->job ? dispatch($push) : dispatch_sync($push);
        }

        if (count($userIds) === $chunk) {
            self::dispatch($this->entryId, (int) end($userIds));
        }
    }

    public function failed(Throwable $e): void
    {
        app(ActivityLogService::class)->log('job.failed', null, "NotifyUsersOfNewEntry entry #{$this->entryId}: ".$e->getMessage());
    }
}
