<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->whereHas('user', fn ($w) => $w
                ->where('email', 'like', "%{$u}%")->orWhere('name', 'like', "%{$u}%")))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $actions = ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('superadmin.logs.index', compact('logs', 'filters', 'actions'));
    }
}
