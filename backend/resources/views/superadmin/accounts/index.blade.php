<x-page :title="$title">
    <x-slot name="actions">
        <x-link-button :href="route($routePrefix.'.create')">+ New {{ $singular }}</x-link-button>
    </x-slot>

    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
            <x-text-input name="q" :value="request('q')" placeholder="Search name, email, phone" class="sm:col-span-2" />
            <x-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" placeholder="Any status" />
            @unless ($isTraders)
                <x-select name="role_id" :options="\App\Models\Role::query()->ofKind(\App\Enums\RoleKind::Staff)->orderBy('name')->pluck('name', 'id')->all()" :value="request('role_id')" placeholder="Any role" />
            @endunless
            <div class="sm:col-span-4"><x-secondary-button type="submit">Filter</x-secondary-button></div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr>
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Email / Phone</th>
                        @if ($isTraders)
                            <th class="py-2 pr-4">Subscription</th>
                        @else
                            <th class="py-2 pr-4">Role</th>
                            <th class="py-2 pr-4">Entries</th>
                        @endif
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="py-2 pr-4 font-medium text-gray-900">{{ $account->name }}</td>
                            <td class="py-2 pr-4">{{ $account->email }}<div class="text-xs text-gray-500">{{ $account->phone }}</div></td>
                            @if ($isTraders)
                                @php($sub = $account->latestSubscription)
                                <td class="py-2 pr-4">
                                    @if (! $sub)
                                        <x-badge>none</x-badge>
                                    @elseif ($sub->isCurrentlyActive())
                                        <x-badge color="green">active</x-badge> <span class="text-xs text-gray-500">until {{ $sub->end_date->format('d M Y') }}</span>
                                    @else
                                        <x-badge color="yellow">expired</x-badge> <span class="text-xs text-gray-500">{{ $sub->end_date->format('d M Y') }}</span>
                                    @endif
                                </td>
                            @else
                                <td class="py-2 pr-4">
                                    {{ $account->role->name }}
                                    @if ($account->direct_permissions_count)
                                        <x-badge color="yellow">+{{ $account->direct_permissions_count }} extra</x-badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $account->entries_count }}</td>
                            @endif
                            <td class="py-2 pr-4">
                                <x-badge :color="$account->isActive() ? 'green' : 'yellow'">{{ $account->status->value }}</x-badge>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap space-x-2">
                                @if ($isTraders)
                                    @can('subscriptions.manage')
                                        <a href="{{ route('superadmin.subscriptions.show', $account) }}" class="text-indigo-600 hover:underline">Subscription</a>
                                    @endcan
                                @endif
                                <a href="{{ route($routePrefix.'.edit', $account) }}" class="text-indigo-600 hover:underline">Edit</a>
                                @if ($account->id !== auth()->id())
                                    <form method="POST" action="{{ route($routePrefix.'.status', $account) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="{{ $account->isActive() ? 'text-yellow-600' : 'text-green-700' }} hover:underline">
                                            {{ $account->isActive() ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $accounts->links() }}</div>
    </x-card>
</x-page>
