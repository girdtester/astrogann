<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\PermissionService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Roles & permissions (DECISIONS D-42). Custom roles are staff roles; system roles cannot be deleted.
 */
class RoleController extends Controller
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function index(): View
    {
        return view('superadmin.roles.index', [
            'roles' => Role::query()->withCount('users')->with('permissions')->orderByDesc('is_system')->orderBy('name')->get(),
            'catalog' => Permissions::catalog(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('superadmin.roles.form', $this->formData($request, new Role));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $role = $this->permissions->createRole($data, $data['permissions'] ?? [], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('superadmin.roles.index')->with('success', "Role {$role->name} created.");
    }

    public function edit(Request $request, Role $role): View
    {
        return view('superadmin.roles.form', $this->formData($request, $role->load('permissions')));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);

        try {
            $this->permissions->updateRole($role, $data, $data['permissions'] ?? [], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('superadmin.roles.index')->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        try {
            $this->permissions->deleteRole($role, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('superadmin.roles.index')->with('success', 'Role deleted.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
        ]);
    }

    private function formData(Request $request, Role $role): array
    {
        return [
            'role' => $role,
            'permissionGroups' => $this->permissions->grouped(),
            'grantable' => $request->user()->permissionKeys(),
            'selected' => old('permissions', $role->exists ? $role->permissions->pluck('key')->all() : []),
        ];
    }
}
