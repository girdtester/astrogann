@php($editing = $role->exists)
<x-page :title="$editing ? 'Edit role: '.$role->name : 'New staff role'">
    <x-slot name="actions">
        <x-link-button :href="route('superadmin.roles.index')" variant="secondary">Back</x-link-button>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ $editing ? route('superadmin.roles.update', $role) : route('superadmin.roles.store') }}" class="space-y-5 max-w-3xl">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field name="name" label="Role name" :value="$role->name" required />
            <x-field name="description" label="Description" :value="$role->description" />

            <div>
                <x-input-label value="Permissions" />
                <p class="text-xs text-gray-500">You can only give permissions you hold yourself.</p>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($permissionGroups as $group => $perms)
                        <fieldset class="border rounded-md p-3">
                            <legend class="px-1 text-xs font-semibold uppercase text-gray-500">{{ $group }}</legend>
                            @foreach ($perms as $perm)
                                @php($allowed = in_array($perm->key, $grantable, true))
                                <label class="flex items-start gap-2 py-1 text-sm {{ $allowed ? '' : 'opacity-50' }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->key }}" class="mt-0.5 rounded border-gray-300 text-indigo-600"
                                        @checked(in_array($perm->key, $selected, true)) @disabled(! $allowed)>
                                    <span>{{ $perm->label }}<span class="block text-xs text-gray-500">{{ $perm->description }}</span></span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('permissions')" class="mt-1" />
            </div>

            <x-primary-button>{{ $editing ? 'Save role' : 'Create role' }}</x-primary-button>
        </form>
    </x-card>
</x-page>
