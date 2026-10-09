<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fan-out of one announcement, one chunk of users per run (D-100) — same pattern as NotifyUsersOfNewEntry:
 * the first chunk runs right after the Super Admin's request, later chunks continue on the database queue.
 * Idempotent: unique (user_id, announcement_id), and only newly notified users get a push.
 */
class NotifyUsersOfAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 50;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $announcementId, public readonly int $afterUserId = 0) {}

    public function handle(SubscriptionService $subscriptions, NotificationService $notifications): void
    {
        $announcement = Announcement::find($this->announcementId);
        if (! $announcement) {
            return; // deleted meanwhile
        }

        // Plain inserts, so a bigger chunk than for entries is fine on shared hosting.
        $chunk = max(1, (int) config('astrogann.notification_chunk', 200) * 5);

        $userIds = $subscriptions->notifiableTraders()
            ->where('users.id', '>', $this->afterUserId)
            ->orderBy('users.id')
            ->limit($chunk)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($userIds === []) {
            return;
        }

        $targets = match ($announcement->audience) {
            'active' => $subscriptions->filterActive($userIds),
            'inactive' => array_values(array_diff($userIds, $subscriptions->filterActive($userIds))),
            default => $userIds,
        };

        $new = $notifications->storeAnnouncementFor($announcement, $targets);
        if ($new !== []) {
            $announcement->increment('recipients', count($new));
            $push = new SendPushToUsers($new, $announcement->title, $announcement->message,
                ['type' => 'announcement', 'announcement_id' => (string) $announcement->id]);
            // Right after the request (first chunk, a "sync" job): push now (D-104); on the database queue: queue it.
            ($this->job !== null && ! $this->job instanceof SyncJob) ? dispatch($push) : dispatch_sync($push);
        }

        if (count($userIds) === $chunk) {
            self::dispatch($this->announcementId, (int) end($userIds));
        }
    }

    public function failed(Throwable $e): void
    {
        app(ActivityLogService::class)->log('job.failed', null, "NotifyUsersOfAnnouncement #{$this->announcementId}: ".$e->getMessage());
    }
}
