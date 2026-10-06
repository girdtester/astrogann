<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ActiveStatus;
use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AccountRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountService;
use App\Services\PermissionService;
use App\Support\Permissions;
use App\Support\Sorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Shared CRUD for staff (admins + custom roles) and user/trader accounts. Subclasses fix the kind.
 */
abstract class AccountController extends Controller
{
    abstract protected function kind(): RoleKind;

    /** Route-name prefix, e.g. "superadmin.admins". */
    abstract protected function routePrefix(): string;

    public function __construct(
        protected readonly AccountService $accounts,
        protected readonly PermissionService $permissions,
    ) {}

    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'role_id' => ['nullable', 'integer'],
            'subscription' => ['nullable', 'in:active,expired,none'],
        ]);

        $query = User::query()
            ->select('users.*')
            ->ofKind($this->kind())
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('users.name', 'like', "%{$term}%")->orWhere('users.email', 'like', "%{$term}%")->orWhere('users.phone', 'like', "%{$term}%")))
            ->when($request->input('status'), fn ($q, $s) => $q->where('users.status', $s))
            ->when($request->input('role_id'), fn ($q, $r) => $q->where('users.role_id', $r))
            ->when($this->isTraders() && $request->input('subscription') === 'active', fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->currentlyActive()))
            ->when($this->isTraders() && $request->input('subscription') === 'expired', fn ($q) => $q->whereHas('subscriptions')->whereDoesntHave('subscriptions', fn ($s) => $s->currentlyActive()))
            ->when($this->isTraders() && $request->input('subscription') === 'none', fn ($q) => $q->whereDoesntHave('subscriptions'))
            ->when($this->isTraders(), fn ($q) => $q->with('latestSubscription'))
            ->when(! $this->isTraders(), fn ($q) => $q->withCount(['entries', 'directPermissions']));

        $sorting = Sorting::apply($query, $request, [
            'name' => 'users.name',
            'email' => 'users.email',
            'status' => 'users.status',
            'created' => 'users.created_at',
            'role' => fn ($q, string $dir) => $q->join('roles', 'roles.id', '=', 'users.role_id')->orderBy('roles.name', $dir),
            'expiry' => fn ($q, string $dir) => $q->orderBy(
                Subscription::select('end_date')->whereColumn('subscriptions.user_id', 'users.id')->latest('id')->limit(1), $dir),
        ], 'created', 'desc');

        // Traders can be lakhs of rows: deferred pagination. Staff lists are small and need withCount().
        $accounts = $this->isTraders()
            ? Sorting::paginateDeferred($query, Sorting::perPage($request), $request)
            : $query->paginate(Sorting::perPage($request))->onEachSide(1)->withQueryString();

        return view('superadmin.accounts.index', $this->viewData($request, ['accounts' => $accounts, 'sorting' => $sorting]));
    }

    public function create(Request $request): View
    {
        return view('superadmin.accounts.form', $this->viewData($request, ['account' => new User(['status' => 'active'])]));
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        try {
            $user = $this->accounts->create($this->kind(), $request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($this->isTraders() && $request->user()->can(Permissions::SUBSCRIPTIONS_MANAGE)) {
            return redirect()->route('superadmin.subscriptions.show', $user)
                ->with('success', "{$user->name} created. Now activate a subscription.");
        }

        return redirect()->route($this->routePrefix().'.index')->with('success', "{$user->name} created.");
    }

    public function edit(Request $request, User $account): View
    {
        $this->ensureKind($account);
        $account->load('directPermissions');

        return view('superadmin.accounts.form', $this->viewData($request, [
            'account' => $account,
            'canManage' => $this->accounts->canManage($account, $request->user()),
            'canDelete' => $this->accounts->canDelete($account, $request->user()),
            'hasHistory' => $this->accounts->hasHistory($account),
        ]));
    }

    public function update(AccountRequest $request, User $account): RedirectResponse
    {
        $this->ensureKind($account);

        $data = $request->validated();
        if (! $this->isTraders() && ! $request->has('permissions')) {
            $data['permissions'] = []; // all boxes unticked
        }

        try {
            $this->accounts->update($account, $data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->routePrefix().'.index')->with('success', "{$account->name} updated.");
    }

    public function toggleStatus(Request $request, User $account): RedirectResponse
    {
        $this->ensureKind($account);
        $status = $account->isActive() ? ActiveStatus::Inactive : ActiveStatus::Active;

        try {
            $this->accounts->setStatus($account, $status, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$account->name} is now {$status->value}.");
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->ensureKind($account);

        try {
            $this->accounts->delete($account, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route($this->routePrefix().'.index')->with('success', 'Account deleted.');
    }

    protected function isTraders(): bool
    {
        return $this->kind() === RoleKind::User;
    }

    protected function ensureKind(User $account): void
    {
        abort_unless($account->kind() === $this->kind(), 404);
    }

    protected function viewData(Request $request, array $data): array
    {
        $actor = $request->user();

        return $data + [
            'kind' => $this->kind(),
            'isTraders' => $this->isTraders(),
            'routePrefix' => $this->routePrefix(),
            'title' => $this->isTraders() ? 'Users / Traders' : 'Admins / Staff',
            'singular' => $this->isTraders() ? 'User' : 'Staff account',
            'staffRoles' => $this->isTraders() ? collect() : $this->permissions->assignableStaffRoles($actor),
            'permissionGroups' => $this->isTraders() ? collect() : $this->permissions->grouped(),
            'grantable' => $actor->permissionKeys(),
            'canManage' => $data['canManage'] ?? true,
            'canDelete' => $data['canDelete'] ?? false,
            'hasHistory' => $data['hasHistory'] ?? false,
        ];
    }
}
