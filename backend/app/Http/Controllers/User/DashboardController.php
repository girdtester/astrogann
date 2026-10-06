<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Services\EntryService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tab-based entries screen (spec 3.9). Tabs = active categories in sort order, straight from the DB.
 * Entries per tab = latest 7 (active) or 3 (expired), limited in the query by EntryService.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly EntryService $entries,
        private readonly SubscriptionService $subscriptions,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tabs = $this->categories->activeTabs();
        $current = $tabs->firstWhere('id', (int) $request->query('category')) ?? $tabs->first();

        return view('user.dashboard', [
            'tabs' => $tabs,
            'current' => $current,
            'visible' => $current ? $this->entries->visibleFor($user, $current) : null,
            'subscription' => $this->subscriptions->summary($user),
            'settings' => $this->settings->public(),
        ]);
    }
}
