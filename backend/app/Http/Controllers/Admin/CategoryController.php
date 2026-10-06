<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

/**
 * Read-only category list for data-entry staff (spec 3.2 Admin "View Categories").
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->active()->ordered()->withCount('entries')->get(),
        ]);
    }
}
