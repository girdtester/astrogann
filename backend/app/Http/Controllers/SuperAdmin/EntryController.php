<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Services\EntryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * View all entries with filters (spec 3.2 "View All Entries"). Read-only: entries are immutable (D-19).
 */
class EntryController extends Controller
{
    public function __construct(private readonly EntryService $entries) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'admin_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('superadmin.entries.index', [
            'entries' => $this->entries->search($filters),
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(),
            'admins' => User::query()->ofKind(RoleKind::Staff, RoleKind::SuperAdmin)->orderBy('name')->get(),
        ]);
    }
}
