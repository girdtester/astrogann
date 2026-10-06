<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ActiveStatus;
use App\Enums\RoleKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AccountRequest;
use App\Models\User;
use App\Services\AccountService;
use App\Services\PermissionService;
use App\Support\Permissions;
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
        $accounts = User::query()
            ->ofKind($this->kind())
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('role_id'), fn ($q, $r) => $q->where('role_id', $r))
            ->when($this->isTraders(), fn ($q) => $q->with('latestSubscription'))
            ->when(! $this->isTraders(), fn ($q) => $q->withCount(['entries', 'directPermissions']))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.accounts.index', $this->viewData($request, ['accounts' => $accounts]));
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
