<x-page title="Subscriptions">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
            <x-text-input name="q" :value="request('q')" placeholder="Search name or email" class="sm:col-span-2" />
            <x-select name="filter" :options="['active' => 'Active', 'expiring' => 'Expiring soon', 'expired' => 'Expired', 'none' => 'Never subscribed']" :value="$filter" placeholder="All users" />
            <input type="hidden" name="sort" value="{{ $sorting['sort'] }}"><input type="hidden" name="dir" value="{{ $sorting['dir'] }}">
            <div><x-primary-button>Apply filters</x-primary-button></div>
        </form>

        <x-list-toolbar :paginator="$users" />
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr><x-sort-th column="name" label="User" :sorting="$sorting" /><th class="py-2 pr-4">Status</th><x-sort-th column="start" label="Start" :sorting="$sorting" /><x-sort-th column="end" label="End" :sorting="$sorting" /><th class="py-2 pr-4">Days left</th><th class="py-2 text-right"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        @php($sub = $user->latestSubscription)
                        <tr>
                            <td class="py-2 pr-4"><div class="font-medium">{{ $user->name }}</div><div class="text-xs text-gray-500">{{ $user->email }}</div></td>
                            <td class="py-2 pr-4">
                                @if (! $sub) <x-badge>none</x-badge>
                                @elseif ($sub->isCurrentlyActive()) <x-badge color="green">active</x-badge>
                                @else <x-badge color="yellow">expired</x-badge>
                                @endif
                                @unless ($user->isActive()) <x-badge color="yellow">account inactive</x-badge> @endunless
                            </td>
                            <td class="py-2 pr-4">{{ \App\Support\Fmt::date($sub?->start_date) }}</td>
                            <td class="py-2 pr-4">{{ \App\Support\Fmt::date($sub?->end_date) }}</td>
                            <td class="py-2 pr-4">{{ $sub?->isCurrentlyActive() ? $sub->daysLeft() : '—' }}</td>
                            <td class="py-2 text-right"><x-action :href="route('superadmin.subscriptions.show', $user)" variant="primary" icon="money">Manage</x-action></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </x-card>
</x-page>
