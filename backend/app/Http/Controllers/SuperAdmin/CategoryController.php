<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(): View
    {
        return view('superadmin.categories.index', [
            'categories' => Category::query()->withCount('entries')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('superadmin.categories.form', [
            'category' => new Category(['status' => 'active', 'sort_order' => (int) Category::max('sort_order') + 1]),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->create($request->validated(), $request->user());

        return redirect()->route('superadmin.categories.index')->with('success', "Category {$category->name} created.");
    }

    public function edit(Category $category): View
    {
        return view('superadmin.categories.form', ['category' => $category]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categories->update($category, $request->validated(), $request->user());

        return redirect()->route('superadmin.categories.index')->with('success', "Category {$category->name} updated.");
    }

    public function toggleStatus(Request $request, Category $category): RedirectResponse
    {
        $status = $category->isActive() ? 'inactive' : 'active';
        $this->categories->update($category, ['status' => $status], $request->user());

        return back()->with('success', "{$category->name} is now {$status}.");
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        try {
            $this->categories->delete($category, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('superadmin.categories.index')->with('success', 'Category deleted.');
    }
}
