@php($editing = $account->exists)
<x-page :title="($editing ? 'Edit ' : 'New ').$singular">
    <x-slot name="actions">
        <x-link-button :href="route($routePrefix.'.index')" variant="secondary">Back</x-link-button>
    </x-slot>

    @if ($editing && ! $canManage)
        <x-card>
            <p class="text-sm text-red-700">You cannot change this account (it is yours, a Super Admin, or it has permissions you do not have).</p>
        </x-card>
    @else
        <x-card>
            <form method="POST" action="{{ $editing ? route($routePrefix.'.update', $account) : route($routePrefix.'.store') }}" class="space-y-5 max-w-2xl">
                @csrf
                @if ($editing) @method('PUT') @endif

                <x-field name="name" label="Name" :value="$account->name" required />
                <x-field name="email" label="Email (login)" type="email" :value="$account->email" required />
                <x-field name="phone" label="Phone" :value="$account->phone" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-field name="password" :label="$editing ? 'New password (leave blank to keep)' : 'Password'" type="password" :required="! $editing" autocomplete="new-password" />
                    <x-field name="password_confirmation" label="Confirm password" type="password" :required="! $editing" autocomplete="new-password" />
                </div>

                <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$account->status?->value ?? 'active'" />

                @unless ($isTraders)
                    <x-select name="role_id" label="Role" :options="$staffRoles->pluck('name', 'id')->all()"
                        :value="$account->role_id ?? $staffRoles->firstWhere('slug', 'admin')?->id" />
                    <p class="-mt-3 text-xs text-gray-500">Roles and their permissions are managed under <em>Roles</em>. Only roles whose permissions you hold are listed.</p>

                    <div>
                        <x-input-label value="Extra permissions for this account (on top of the role)" />
                        @php($selected = old('permissions', $editing ? $account->directPermissions->pluck('key')->all() : []))
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($permissionGroups as $group => $perms)
                                <fieldset class="border rounded-md p-3">
                                    <legend class="px-1 text-xs font-semibold uppercase text-gray-500">{{ $group }}</legend>
                                    @foreach ($perms as $perm)
                                        @php($allowed = in_array($perm->key, $grantable, true))
                                        <label class="flex items-start gap-2 py-1 text-sm {{ $allowed ? '' : 'opacity-50' }}" title="{{ $perm->description }}">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm->key }}" class="mt-0.5 rounded border-gray-300 text-indigo-600"
                                                @checked(in_array($perm->key, $selected, true)) @disabled(! $allowed)>
                                            <span>{{ $perm->label }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('permissions')" class="mt-1" />
                    </div>
                @endunless

                <div class="flex items-center gap-3">
                    <x-primary-button>{{ $editing ? 'Save changes' : 'Create' }}</x-primary-button>
                </div>
            </form>
        </x-card>

        @if ($editing)
            <x-card title="Delete account">
                @if ($canDelete)
                    <form method="POST" action="{{ route($routePrefix.'.destroy', $account) }}" onsubmit="return confirm('Delete this account permanently?')">
                        @csrf @method('DELETE')
                        <x-danger-button>Delete</x-danger-button>
                    </form>
                @else
                    <p class="text-sm text-gray-600">This account has history (entries, subscriptions or payments), so it cannot be deleted. Deactivate it instead.</p>
                @endif
            </x-card>
        @endif
    @endif
</x-page>
