<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;
use App\Support\Permissions;

/**
 * Entry access (DECISIONS D-71, supersedes the "immutable" rule of D-19/D-47):
 * edit/delete need the entries.edit / entries.delete permission AND visibility of that entry
 * (all entries with entries.view_all, otherwise only own). Super Admin holds every permission.
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
        return $user->hasPermission(Permissions::ENTRIES_EDIT) && $this->reach($user, $entry);
    }

    public function delete(User $user, Entry $entry): bool
    {
        return $user->hasPermission(Permissions::ENTRIES_DELETE) && $this->reach($user, $entry);
    }

    /** Can this user reach the entry at all (all entries, or own ones)? */
    private function reach(User $user, Entry $entry): bool
    {
        return $user->hasPermission(Permissions::ENTRIES_VIEW_ALL) || $entry->admin_id === $user->id;
    }
}
