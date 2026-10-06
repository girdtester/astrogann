<x-page :title="$title">
    <x-slot name="actions">
        <x-link-button :href="route($routePrefix.'.create')">+ New {{ $singular }}</x-link-button>
    </x-slot>

    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
            <x-text-input name="q" :value="request('q')" placeholder="Search name, email, phone" class="sm:col-span-2" />
            <x-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" placeholder="Any status" />
            @if ($isTraders)
                <x-select name="subscription" :options="['active' => 'Subscription active', 'expired' => 'Subscription expired', 'none' => 'Never subscribed']" :value="request('subscription')" placeholder="Any subscription" />
            @else
                <x-select name="role_id" :options="\App\Models\Role::query()->ofKind(\App\Enums\RoleKind::Staff)->orderBy('name')->pluck('name', 'id')->all()" :value="request('role_id')" placeholder="Any role" />
            @endif
            <input type="hidden" name="sort" value="{{ $sorting['sort'] }}"><input type="hidden" name="dir" value="{{ $sorting['dir'] }}">
            <div class="sm:col-span-4"><x-primary-button>Apply filters</x-primary-button></div>
        </form>

        <x-list-toolbar :paginator="$accounts" />
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr>
                        <x-sort-th column="name" label="Name" :sorting="$sorting" />
                        <x-sort-th column="email" label="Email / Phone" :sorting="$sorting" />
                        @if ($isTraders)
                            <x-sort-th column="expiry" label="Subscription" :sorting="$sorting" />
                        @else
                            <x-sort-th column="role" label="Role" :sorting="$sorting" />
                            <th class="py-2 pr-4">Entries</th>
                        @endif
                        <x-sort-th column="status" label="Status" :sorting="$sorting" />
                        <x-sort-th column="created" label="Created" :sorting="$sorting" />
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
                                        <x-badge color="green">active</x-badge> <span class="text-xs text-gray-500">until {{ \App\Support\Fmt::date($sub->end_date) }}</span>
                                    @else
                                        <x-badge color="yellow">expired</x-badge> <span class="text-xs text-gray-500">on {{ \App\Support\Fmt::date($sub->end_date) }}</span>
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
                            <td class="py-2 pr-4 whitespace-nowrap text-gray-500">{{ \App\Support\Fmt::date($account->created_at) }}<div class="text-xs">{{ \App\Support\Fmt::ago($account->created_at) }}</div></td>
                            <td class="py-2 text-right">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    @if ($isTraders)
                                        @can('subscriptions.manage')
                                            <x-action :href="route('superadmin.subscriptions.show', $account)" variant="info" icon="money">Subscription</x-action>
                                        @endcan
                                    @endif
                                    <x-action :href="route($routePrefix.'.edit', $account)" variant="primary" icon="edit">Edit</x-action>
                                    @if ($account->id !== auth()->id())
                                        <x-action :action="route($routePrefix.'.status', $account)" method="PATCH"
                                            :variant="$account->isActive() ? 'warning' : 'secondary'" :icon="$account->isActive() ? 'power' : 'check'">
                                            {{ $account->isActive() ? 'Deactivate' : 'Activate' }}
                                        </x-action>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-500">No accounts match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $accounts->links() }}</div>
    </x-card>
</x-page>
