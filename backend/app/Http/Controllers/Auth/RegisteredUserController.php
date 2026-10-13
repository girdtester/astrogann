<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\AccountService;
use App\Services\SessionService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Website sign-up for users / traders (DECISIONS D-85). New accounts have no subscription: they see the latest
 * few entries and "Show More" until the admin activates a subscription.
 */
class RegisteredUserController extends Controller
{
    public function create(SettingsService $settings): View
    {
        abort_unless($settings->bool('allow_registration'), 404);

        return view('auth.register');
    }

    public function store(RegisterRequest $request, AccountService $accounts, SessionService $sessions): RedirectResponse
    {
        $user = $accounts->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();
        $sessions->startWeb($user, $request);

        return redirect()->route('user.dashboard')
            ->with('success', 'Welcome! Your account is ready. You can see a few older entries — contact the admin to subscribe for full access.');
    }
}
