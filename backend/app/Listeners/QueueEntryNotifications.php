<?php

namespace App\Listeners;

use App\Events\EntryCreated;
use App\Jobs\NotifyUsersOfNewEntry;

/**
 * Keeps the request fast: the fan-out to subscribers runs on the queue (database driver, cron-driven worker).
 */
class QueueEntryNotifications
{
    public function handle(EntryCreated $event): void
    {
        NotifyUsersOfNewEntry::dispatch($event->entry->id)->afterCommit();
    }
}
