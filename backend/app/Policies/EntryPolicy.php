<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;
use App\Support\Permissions;

/**
 * Entries are immutable for everyone (DECISIONS D-19): update/delete always return false,
 * whatever role or extra permissions an account has.
 */
class EntryPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permissions::ENTRIES_CREATE);
    }

    public function view(User $user, Entry $entry): bool
    {
        return $user->hasPermission(Permissions::ENTRIES_VIEW_ALL)
            || ($entry->admin_id === $user->id && $user->hasPermission(Permissions::ENTRIES_VIEW_OWN));
    }

    public function update(User $user, Entry $entry): bool
    {
        return false;
    }

    public function delete(User $user, Entry $entry): bool
    {
        return false;
    }
}
