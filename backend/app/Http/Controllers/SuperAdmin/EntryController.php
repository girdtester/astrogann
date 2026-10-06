<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Services\EntryService;
use App\Support\Sorting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * View all entries with filters and sorting (spec 3.2 "View All Entries"). Edit/delete per EntryPolicy (D-71).
 */
class EntryController extends Controller
{
    public function __construct(private readonly EntryService $entries) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'admin_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        [$entries, $sorting] = $this->entries->search($filters, null, Sorting::perPage($request), $request);

        return view('superadmin.entries.index', [
            'entries' => $entries,
            'sorting' => $sorting,
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(),
            'admins' => User::query()->ofKind(RoleKind::Staff, RoleKind::SuperAdmin)->orderBy('name')->get(),
        ]);
    }
}
