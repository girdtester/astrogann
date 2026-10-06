<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\PaymentRequest;
use App\Http\Requests\SuperAdmin\SubscriptionActionRequest;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SettingsService $settings,
    ) {}

    /** All traders with their current subscription; filter by state. */
    public function index(Request $request): View
    {
        $filter = $request->input('filter');
        $soon = today()->addDays($this->settings->reminderDays());

        $users = User::query()
            ->ofKind(RoleKind::User)
            ->with('latestSubscription')
            ->when($request->string('q')->trim()->value(), fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->when($filter === 'active', fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->currentlyActive()))
            ->when($filter === 'expiring', fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->currentlyActive()->whereDate('end_date', '<=', $soon)))
            ->when($filter === 'expired', fn ($q) => $q->whereHas('subscriptions')->whereDoesntHave('subscriptions', fn ($s) => $s->currentlyActive()))
            ->when($filter === 'none', fn ($q) => $q->whereDoesntHave('subscriptions'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.subscriptions.index', compact('users', 'filter'));
    }

    public function show(User $user): View
    {
        abort_unless($user->isTrader(), 404);

        return view('superadmin.subscriptions.show', [
            'user' => $user,
            'summary' => $this->subscriptions->summary($user),
            'current' => $this->subscriptions->current($user),
            'history' => $user->subscriptions()->with('creator')->latest('id')->get(),
            'payments' => $user->payments()->with('recorder')->latest('id')->get(),
        ]);
    }

    /** activate | extend | expire */
    public function action(SubscriptionActionRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isTrader(), 404);
        $data = $request->validated();
        $by = $request->user();

        try {
            match ($data['action']) {
                'activate' => $this->subscriptions->activate($user, $data['start_date'] ?? today(), $data['end_date'], $by, $data['notes'] ?? null, $data['payment'] ?? null),
                'extend' => $this->subscriptions->extend($user, $data['end_date'], $by, $data['payment'] ?? null),
                'expire' => $this->subscriptions->expire($user, $by),
            };
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $done = ['activate' => 'activated', 'extend' => 'extended', 'expire' => 'expired'][$data['action']];

        return redirect()->route('superadmin.subscriptions.show', $user)->with('success', "Subscription {$done}.");
    }

    public function storePayment(PaymentRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isTrader(), 404);
        $this->subscriptions->recordPayment($user, $request->validated(), $request->user());

        return back()->with('success', 'Payment recorded.');
    }
}
