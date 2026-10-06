<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff home (Admin and custom staff roles). Shows the screens this account's permissions allow.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.dashboard', [
            'links' => array_slice(Navigation::for($user), 1), // without "Dashboard" itself
            'myEntriesToday' => $user->entries()->where('created_at', '>=', today())->count(),
            'myEntriesTotal' => $user->entries()->count(),
            'latest' => $user->entries()->with('category')->latestFirst()->limit(5)->get(),
        ]);
    }
}
