<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntryRequest;
use App\Models\Category;
use App\Services\EntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Admin data entry (spec 3.2): add entries and view own entries. No edit / delete routes exist.
 */
class EntryController extends Controller
{
    public function __construct(private readonly EntryService $entries) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('admin.entries.index', [
            'entries' => $this->entries->search($filters, onlyAdmin: $request->user()),
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.entries.create', [
            'categories' => Category::query()->active()->ordered()->get(),
            'maxKb' => config('astrogann.entry_image_max_kb'),
        ]);
    }

    public function store(StoreEntryRequest $request): RedirectResponse
    {
        try {
            $entry = $this->entries->create($request->user(), $request->validated(), $request->file('image'));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $back = $request->user()->can('entries.view_own') ? route('admin.entries.index') : route('admin.entries.create');

        return redirect($back)->with('success', "Entry added to {$entry->category->name}. Subscribed users will be notified.");
    }
}
