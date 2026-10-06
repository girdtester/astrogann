<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Entry;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * In-app notifications stored in user_notifications (spec 3.7, DECISIONS D-21).
 * Web shows them with an unread count at the next page load; mobile reads them via the API.
 */
class NotificationService
{
    public function list(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->appNotifications()->with('category')->latest('id')->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $user->appNotifications()->unread()->count();
    }

    public function markAllRead(User $user): int
    {
        return $user->appNotifications()->unread()->update(['is_read' => true]);
    }

    public function markRead(User $user, int $id): bool
    {
        return (bool) $user->appNotifications()->whereKey($id)->update(['is_read' => true]);
    }

    public static function newEntryMessage(Entry $entry): string
    {
        return "New entry added in {$entry->category->name}. Check latest update now!";
    }

    /** Insert one new_entry row per user id (bulk insert; used by the queued fan-out job). */
    public function storeNewEntryFor(Entry $entry, array $userIds): void
    {
        $now = now();
        $message = self::newEntryMessage($entry);

        UserNotification::insert(array_map(fn (int $id) => [
            'user_id' => $id,
            'category_id' => $entry->category_id,
            'type' => NotificationType::NewEntry->value,
            'message' => $message,
            'is_read' => false,
            'created_at' => $now,
        ], $userIds));
    }

    public function storeExpiryReminder(User $user, string $message): UserNotification
    {
        return $user->appNotifications()->create([
            'type' => NotificationType::Expiry,
            'message' => $message,
            'is_read' => false,
        ]);
    }
}
