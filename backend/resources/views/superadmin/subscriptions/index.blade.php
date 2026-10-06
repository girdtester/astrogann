<x-page title="Subscriptions">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
            <x-text-input name="q" :value="request('q')" placeholder="Search name or email" class="sm:col-span-2" />
            <x-select name="filter" :options="['active' => 'Active', 'expiring' => 'Expiring soon', 'expired' => 'Expired', 'none' => 'Never subscribed']" :value="$filter" placeholder="All users" />
            <div><x-secondary-button type="submit">Filter</x-secondary-button></div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr><th class="py-2 pr-4">User</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Start</th><th class="py-2 pr-4">End</th><th class="py-2 pr-4">Days left</th><th class="py-2 text-right"></th></tr>
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
                            <td class="py-2 pr-4">{{ $sub?->start_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $sub?->end_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $sub?->isCurrentlyActive() ? $sub->daysLeft() : '—' }}</td>
                            <td class="py-2 text-right"><a href="{{ route('superadmin.subscriptions.show', $user) }}" class="text-indigo-600 hover:underline">Manage</a></td>
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
