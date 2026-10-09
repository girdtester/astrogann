<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
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

    /** Live updates for the web (D-73): unread count + notifications newer than ?after=<id>. */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $after = max(0, (int) $request->query('after'));

        $new = $user->appNotifications()->with('category:id,name,code,status')->where('id', '>', $after)->latest('id')->limit(5)->get();
        // Nothing new (the usual case) → 2 small indexed queries only (D-96).
        $subscribed = $new->isEmpty() || $this->subscriptions->isActiveFor($user);

        return response()->json([
            'unread_count' => $this->notifications->unreadCount($user),
            'last_id' => (int) ($new->first()?->id ?? $after),
            'items' => $new->reverse()->values()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->category?->name ?? 'Subscription',
                'message' => $n->message,
                'url' => $n->category?->isActive()
                    ? route('user.dashboard', ['tab' => $n->category->tabKey()] + ($subscribed ? [] : ['subscribe' => 1]))
                    : ($n->category_id ? route('user.notifications.index') : route('user.subscription')),
            ]),
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

        // Go to the category tab only if it is still active (D-76); otherwise stay on the list.
        // Not subscribed? Open the tab with the "contact admin to subscribe" panel already open (D-94).
        $subscribed = $this->subscriptions->isActiveFor($request->user());

        return match (true) {
            (bool) $notification->category?->isActive() && ! $subscribed => redirect()
                ->route('user.dashboard', ['tab' => $notification->category->tabKey(), 'subscribe' => 1])
                ->with('status', 'Subscribe to see all the latest updates — contact the admin below.'),
            (bool) $notification->category?->isActive() => redirect()->route('user.dashboard', ['tab' => $notification->category->tabKey()]),
            $notification->category_id !== null => redirect()->route('user.notifications.index')->with('status', 'That category is not available any more.'),
            default => redirect()->route('user.subscription'),
        };
    }
}
