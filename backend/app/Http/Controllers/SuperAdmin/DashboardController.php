<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(SettingsService $settings): View
    {
        $traders = User::query()->ofKind(RoleKind::User);
        $soon = today()->addDays($settings->reminderDays());

        $activeSubs = User::query()->ofKind(RoleKind::User)->whereHas('subscriptions', fn ($q) => $q->currentlyActive())->count();
        $tradersTotal = (clone $traders)->count();

        return view('superadmin.dashboard', [
            'counts' => [
                'Staff accounts' => User::query()->ofKind(RoleKind::Staff)->count(),
                'Users / Traders' => $tradersTotal,
                'Inactive users' => (clone $traders)->where('status', 'inactive')->count(),
                'Active subscriptions' => $activeSubs,
                'Expired / no subscription' => $tradersTotal - $activeSubs,
                "Expiring in {$settings->reminderDays()} days" => Subscription::query()->currentlyActive()->whereDate('end_date', '<=', $soon)->count(),
                'Active categories' => Category::query()->active()->count(),
                'Entries today' => Entry::query()->whereDate('entry_date', today())->count(),
                'Entries total' => Entry::query()->count(),
            ],
            'recentLogs' => ActivityLog::query()->with('user')->latest('id')->limit(10)->get(),
        ]);
    }
}
