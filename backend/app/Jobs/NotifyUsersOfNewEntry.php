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
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notification fan-out for one new entry, one chunk of users per run (spec 3.7).
 *
 * The first chunk runs right after the admin's request (dispatchAfterResponse, D-73) so users see it at once;
 * later chunks re-dispatch themselves onto the database queue, so every run stays well inside shared-hosting
 * time limits. Active subscribers get "New entry added…" + push; with the notify_expired_users setting on,
 * expired / never-subscribed users get a "renew your subscription" notification, and a push too when the
 * push_expired_users setting is on (D-73, D-89 — supersedes the "no push for expired" default of D-18).
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
        [$newActive, $newInactive] = DB::transaction(fn () => [
            $notifications->storeNewEntryFor($entry, $activeIds),
            $notifications->storeNewEntryFor($entry, $inactiveIds, NotificationService::renewMessage($entry)),
        ]);

        $data = ['type' => 'new_entry', 'category_id' => (string) $entry->category_id, 'entry_id' => (string) $entry->id];
        $pushes = [];
        if ($newActive !== []) {
            // Active subscribers: the entry image shows in the notification.
            $pushes[] = new SendPushToUsers($newActive, $entry->category->name, NotificationService::newEntryMessage($entry), $data + ['image' => $entry->imageUrl()]);
        }
        if ($newInactive !== [] && $settings->bool('push_expired_users')) {
            // Expired / never subscribed: push with the "renew" text, no image (D-89).
            $pushes[] = new SendPushToUsers($newInactive, $entry->category->name, NotificationService::renewMessage($entry), $data + ['renew' => '1']);
        }
        foreach ($pushes as $push) {
            // Running right after the admin's request (first chunk, a "sync" job)? Push immediately (D-104);
            // a later chunk on the database queue queues it.
            $this->onRealQueue() ? dispatch($push) : dispatch_sync($push);
        }

        if (count($userIds) === $chunk) {
            self::dispatch($this->entryId, (int) end($userIds));
        }
    }

    /** True when this run came from the database queue (cron), false right after the admin's request. */
    private function onRealQueue(): bool
    {
        return $this->job !== null && ! $this->job instanceof SyncJob;
    }

    public function failed(Throwable $e): void
    {
        app(ActivityLogService::class)->log('job.failed', null, "NotifyUsersOfNewEntry entry #{$this->entryId}: ".$e->getMessage());
    }
}
