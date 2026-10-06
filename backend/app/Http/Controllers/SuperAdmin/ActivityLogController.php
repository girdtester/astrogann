<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Sorting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Reports / logs (spec 3.2). Filter by action, user (email) and date range.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        // Index-friendly date range (no DATE() around the column) for lakhs of log rows (D-75).
        $query = ActivityLog::query()
            ->with('user:id,name,email,role_id')
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->whereIn('user_id', User::query()->select('id')
                ->where(fn ($w) => $w->where('email', 'like', "%{$u}%")->orWhere('name', 'like', "%{$u}%"))))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<', Carbon::parse($d)->addDay()->startOfDay()));

        $sorting = Sorting::apply($query, $request, ['date' => 'created_at', 'action' => 'action'], 'date');
        $logs = $query->paginate(Sorting::perPage($request, 50))->onEachSide(1)->withQueryString();

        $actions = Cache::remember('activity_log_actions', 600, fn () => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'));

        return view('superadmin.logs.index', compact('logs', 'filters', 'actions', 'sorting'));
    }
}
