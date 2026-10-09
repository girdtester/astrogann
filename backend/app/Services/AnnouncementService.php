<?php

namespace App\Services;

use App\Jobs\NotifyUsersOfAnnouncement;
use App\Models\Announcement;
use App\Models\User;

/**
 * Manual announcements from the Super Admin (D-100). Delivered exactly like automatic notifications:
 * a user_notifications row per user (web + app Notifications, live check, toast) and a push when enabled.
 */
class AnnouncementService
{
    public function __construct(private readonly ActivityLogService $logs) {}

    /** @param array{title: string, message: string, audience: string} $data */
    public function send(array $data, User $by): Announcement
    {
        $announcement = Announcement::create([
            'title' => trim($data['title']),
            'message' => trim($data['message']),
            'audience' => $data['audience'],
            'created_by' => $by->id,
        ]);

        $this->logs->log('announcement.sent', $announcement, "Announcement \"{$announcement->title}\" to {$announcement->audienceLabel()}", $by);

        // First chunk right after the response (users see it at once), the rest continues on the queue.
        NotifyUsersOfAnnouncement::dispatchAfterResponse($announcement->id);

        return $announcement;
    }

    public function delete(Announcement $announcement, User $by): void
    {
        $this->logs->log('announcement.deleted', null, "Announcement \"{$announcement->title}\" deleted ({$announcement->recipients} users)", $by);
        $announcement->delete(); // user copies go with it (foreign key cascade)
    }
}
