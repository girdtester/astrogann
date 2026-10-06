<?php

namespace App\Events;

use App\Models\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an admin adds an entry (spec 3.7). Listener: QueueEntryNotifications.
 */
class EntryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Entry $entry) {}
}
