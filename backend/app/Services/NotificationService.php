<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Announcement;
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
        return $user->appNotifications()->with(['category', 'announcement:id,title'])->latest('id')->paginate($perPage);
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

    public static function renewMessage(Entry $entry): string
    {
        return "New entry added in {$entry->category->name}. Renew your subscription to see all the latest updates.";
    }

    /**
     * Insert one new_entry row per user id (bulk). Idempotent: users who already have a notification for this
     * entry are skipped (unique user_id+entry_id). Returns the ids that were actually notified now.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function storeNewEntryFor(Entry $entry, array $userIds, ?string $message = null): array
    {
        if ($userIds === []) {
            return [];
        }
        $already = UserNotification::query()->where('entry_id', $entry->id)->whereIn('user_id', $userIds)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $userIds = array_values(array_diff($userIds, $already));
        if ($userIds === []) {
            return [];
        }
        $now = now();
        $message ??= self::newEntryMessage($entry);

        UserNotification::insertOrIgnore(array_map(fn (int $id) => [
            'user_id' => $id,
            'category_id' => $entry->category_id,
            'entry_id' => $entry->id,
            'type' => NotificationType::NewEntry->value,
            'message' => $message,
            'is_read' => false,
            'created_at' => $now,
        ], $userIds));

        return $userIds;
    }

    /**
     * Store one announcement for these users (D-100). Returns the ids that were newly notified.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function storeAnnouncementFor(Announcement $announcement, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $already = UserNotification::query()->where('announcement_id', $announcement->id)->whereIn('user_id', $userIds)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $userIds = array_values(array_diff($userIds, $already));
        if ($userIds === []) {
            return [];
        }
        $now = now();

        UserNotification::insertOrIgnore(array_map(fn (int $id) => [
            'user_id' => $id,
            'announcement_id' => $announcement->id,
            'type' => NotificationType::Announcement->value,
            'message' => $announcement->message,
            'is_read' => false,
            'created_at' => $now,
        ], $userIds));

        return $userIds;
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
