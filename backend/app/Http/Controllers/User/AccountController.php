<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Subscription status and notifications for the user/trader (spec 3.2, 3.7).
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
    ) {}

    public function subscription(Request $request): View
    {
        return view('user.subscription', [
            'subscription' => $this->subscriptions->summary($request->user()),
            'settings' => $this->settings->public(),
        ]);
    }

    public function notifications(Request $request): View
    {
        return view('user.notifications', [
            'notifications' => $this->notifications->list($request->user()),
            'unread' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back()->with('success', 'All notifications marked as read.');
    }

    public function markRead(Request $request, int $id): RedirectResponse
    {
        $notification = $request->user()->appNotifications()->findOrFail($id);
        $this->notifications->markRead($request->user(), $notification->id);

        return $notification->category_id
            ? redirect()->route('user.dashboard', ['category' => $notification->category_id])
            : redirect()->route('user.subscription');
    }
}
