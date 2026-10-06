<x-page title="My Subscription">
    <x-card>
        <dl class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="mt-1">
                    @if ($subscription['is_active']) <x-badge color="green">Active</x-badge>
                    @elseif ($subscription['status'] === 'expired') <x-badge color="yellow">Expired</x-badge>
                    @else <x-badge>No subscription</x-badge>
                    @endif
                </dd>
            </div>
            <div><dt class="text-gray-500">Start date</dt><dd class="mt-1 font-medium">{{ $subscription['start_date'] ? \Illuminate\Support\Carbon::parse($subscription['start_date'])->format('d M Y') : '—' }}</dd></div>
            <div><dt class="text-gray-500">Expiry date</dt><dd class="mt-1 font-medium">{{ $subscription['end_date'] ? \Illuminate\Support\Carbon::parse($subscription['end_date'])->format('d M Y') : '—' }}</dd></div>
            <div><dt class="text-gray-500">Days left</dt><dd class="mt-1 font-medium">{{ $subscription['is_active'] ? $subscription['days_left'] : '—' }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-gray-600">
            You can see the latest <strong>{{ $subscription['entries_limit'] }}</strong> entries in each category.
            @if ($subscription['is_active'])
                You also receive push notifications for new entries and an e-mail {{ $settings['reminder_days'] }} days before expiry.
            @endif
        </p>
    </x-card>

    @unless ($subscription['is_active'])
        @include('partials.show-more', ['settings' => $settings, 'open' => true])
    @endunless
</x-page>
