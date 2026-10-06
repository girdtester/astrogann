<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntryRequest;
use App\Http\Requests\Admin\UpdateEntryRequest;
use App\Models\Category;
use App\Models\Entry;
use App\Services\EntryService;
use App\Support\Permissions;
use App\Support\Sorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Data entry (spec 3.2): add entries, view own entries, and — with entries.edit / entries.delete — edit or delete
 * them (DECISIONS D-71). Authorisation: EntryPolicy (route middleware + Form Request).
 */
class EntryController extends Controller
{
    public function __construct(private readonly EntryService $entries) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        [$entries, $sorting] = $this->entries->search($filters, $request->user(), Sorting::perPage($request), $request);

        return view('admin.entries.index', [
            'entries' => $entries,
            'sorting' => $sorting,
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

    public function edit(Entry $entry): View
    {
        return view('admin.entries.edit', [
            'entry' => $entry->load('category'),
            'categories' => Category::query()->active()->ordered()->get(),
            'maxKb' => config('astrogann.entry_image_max_kb'),
        ]);
    }

    public function update(UpdateEntryRequest $request, Entry $entry): RedirectResponse
    {
        try {
            $this->entries->update($entry, $request->validated(), $request->file('image'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($this->listUrl($request))->with('success', "Entry #{$entry->id} updated.");
    }

    public function destroy(Request $request, Entry $entry): RedirectResponse
    {
        $this->entries->delete($entry, $request->user());

        return redirect($this->listUrl($request))->with('success', 'Entry deleted.');
    }

    private function listUrl(Request $request): string
    {
        return $request->user()->can(Permissions::ENTRIES_VIEW_ALL)
            ? route('superadmin.entries.index')
            : route('admin.entries.index');
    }
}