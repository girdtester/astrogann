<x-page title="Roles & Permissions">
    <x-slot name="actions">
        <x-link-button :href="route('superadmin.roles.create')">+ New role</x-link-button>
    </x-slot>

    <x-card>
        <p class="text-sm text-gray-600 mb-4">
            Roles decide what staff accounts can do. <strong>Super Admin</strong> always has every permission.
            <strong>User / Trader</strong> is the subscriber role (access comes from the subscription).
            You can also give a single staff account extra permissions from its edit page.
        </p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr><th class="py-2 pr-4">Role</th><th class="py-2 pr-4">Type</th><th class="py-2 pr-4">Permissions</th><th class="py-2 pr-4">Accounts</th><th class="py-2 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($roles as $role)
                        <tr class="align-top">
                            <td class="py-2 pr-4">
                                <div class="font-medium text-gray-900">{{ $role->name }}</div>
                                <div class="text-xs text-gray-500">{{ $role->description }}</div>
                                @if ($role->is_system) <x-badge color="blue">system</x-badge> @endif
                            </td>
                            <td class="py-2 pr-4">{{ $role->kind->label() }}</td>
                            <td class="py-2 pr-4">
                                @if ($role->kind === \App\Enums\RoleKind::SuperAdmin)
                                    <x-badge color="green">all permissions</x-badge>
                                @elseif ($role->kind === \App\Enums\RoleKind::User)
                                    <span class="text-gray-500 text-xs">subscriber screens only</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($role->permissions as $perm)
                                            <x-badge>{{ $catalog[$perm->key]['label'] ?? $perm->key }}</x-badge>
                                        @empty
                                            <span class="text-gray-500 text-xs">none</span>
                                        @endforelse
                                    </div>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ $role->users_count }}</td>
                            <td class="py-2 text-right">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    @if ($role->hasEditablePermissions())
                                        <x-action :href="route('superadmin.roles.edit', $role)" variant="primary" icon="edit">Edit</x-action>
                                    @endif
                                    @if (! $role->is_system && $role->users_count === 0)
                                        <x-action :action="route('superadmin.roles.destroy', $role)" method="DELETE" variant="warning" icon="trash"
                                            :confirm="'Delete role '.$role->name.'?'">Delete</x-action>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-page>
