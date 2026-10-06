<?php

namespace App\Listeners;

use App\Events\EntryCreated;
use App\Jobs\NotifyUsersOfNewEntry;

/**
 * Starts the notification fan-out for a new entry without slowing down the admin's request.
 */
class QueueEntryNotifications
{
    public function handle(EntryCreated $event): void
    {
        // First chunk right after the response is sent (no waiting for the cron-driven queue); further chunks
        // re-dispatch themselves onto the database queue (DECISIONS D-73).
        NotifyUsersOfNewEntry::dispatchAfterResponse($event->entry->id);
    }
}
