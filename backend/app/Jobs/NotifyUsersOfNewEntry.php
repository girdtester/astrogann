<?php

namespace App\Jobs;

use App\Models\Entry;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notification fan-out for one new entry, one chunk of users per run (spec 3.7):
 * eligible users (active account + active subscription, DECISIONS D-17/D-18) with id > $afterUserId get a
 * user_notifications row and a push (separate SendPushToUsers job). If the chunk was full, the job re-dispatches
 * itself for the next chunk — so every run stays well inside shared-hosting time limits.
 */
class NotifyUsersOfNewEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 50;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $entryId, public readonly int $afterUserId = 0) {}

    public function handle(SubscriptionService $subscriptions, NotificationService $notifications): void
    {
        $entry = Entry::with('category')->find($this->entryId);
        if (! $entry) {
            return;
        }

        $chunk = max(1, (int) config('astrogann.notification_chunk', 200));
        $userIds = $subscriptions->eligibleUsersFor($entry->category)
            ->where('users.id', '>', $this->afterUserId)
            ->orderBy('users.id')
            ->limit($chunk)
            ->pluck('users.id')
            ->all();

        if ($userIds === []) {
            return;
        }

        DB::transaction(fn () => $notifications->storeNewEntryFor($entry, $userIds));

        SendPushToUsers::dispatch(
            $userIds,
            $entry->category->name,
            NotificationService::newEntryMessage($entry),
            ['type' => 'new_entry', 'category_id' => (string) $entry->category_id, 'entry_id' => (string) $entry->id],
        );

        if (count($userIds) === $chunk) {
            self::dispatch($this->entryId, (int) end($userIds));
        }
    }

    public function failed(Throwable $e): void
    {
        app(ActivityLogService::class)->log('job.failed', null, "NotifyUsersOfNewEntry entry #{$this->entryId}: ".$e->getMessage());
    }
}
