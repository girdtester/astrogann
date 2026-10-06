<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** GET /api/notifications?page=N — newest first, with unread_count. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::paginated(
            $this->notifications->list($user, min(50, max(1, (int) $request->query('per_page', 20)))),
            NotificationResource::class,
            ['unread_count' => $this->notifications->unreadCount($user)],
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $request->user()->appNotifications()->findOrFail($id);
        $this->notifications->markRead($request->user(), $id);

        return ApiResponse::success(['unread_count' => $this->notifications->unreadCount($request->user())], 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return ApiResponse::success(['unread_count' => 0], 'All marked as read.');
    }
}
