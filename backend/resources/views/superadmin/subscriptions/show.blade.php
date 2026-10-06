@php($paymentStatuses = collect(\App\Enums\PaymentStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
<x-page :title="'Subscription — '.$user->name">
    <x-slot name="actions">
        <x-link-button :href="route('superadmin.subscriptions.index')" variant="secondary">Back</x-link-button>
    </x-slot>

    <x-card title="Current status">
        <dl class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div><dt class="text-gray-500">User</dt><dd class="font-medium">{{ $user->email }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd>
                <x-badge :color="$summary['status'] === 'active' ? 'green' : ($summary['status'] === 'none' ? 'gray' : 'red')">{{ $summary['status'] }}</x-badge>
            </dd></div>
            <div><dt class="text-gray-500">Start</dt><dd>{{ $summary['start_date'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">End (inclusive)</dt><dd>{{ $summary['end_date'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Entries visible</dt><dd>{{ $summary['entries_limit'] }} per category</dd></div>
        </dl>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card title="Activate (new period)">
            <form method="POST" action="{{ route('superadmin.subscriptions.action', $user) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="action" value="activate">
                <x-field name="start_date" label="Start date" type="date" :value="today()->toDateString()" />
                <x-field name="end_date" label="End date" type="date" :value="today()->addMonth()->toDateString()" required />
                <x-field name="notes" label="Notes" />
                <details class="text-sm"><summary class="cursor-pointer text-gray-600">Record payment (optional)</summary>
                    <div class="mt-2 space-y-2">
                        <x-field name="payment[amount]" label="Amount" type="number" step="0.01" min="0" />
                        <x-field name="payment[payment_date]" label="Payment date" type="date" :value="today()->toDateString()" />
                        <x-field name="payment[transaction_ref]" label="Transaction ref" />
                    </div>
                </details>
                <x-primary-button>Activate</x-primary-button>
            </form>
        </x-card>

        <x-card title="Extend current period">
            @if ($current)
                <form method="POST" action="{{ route('superadmin.subscriptions.action', $user) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="action" value="extend">
                    <x-field name="end_date" label="New end date" type="date"
                        :value="$current->end_date->copy()->max(today())->addMonth()->toDateString()" required />
                    <details class="text-sm"><summary class="cursor-pointer text-gray-600">Record payment (optional)</summary>
                        <div class="mt-2 space-y-2">
                            <x-field name="payment[amount]" label="Amount" type="number" step="0.01" min="0" />
                            <x-field name="payment[payment_date]" label="Payment date" type="date" :value="today()->toDateString()" />
                            <x-field name="payment[transaction_ref]" label="Transaction ref" />
                        </div>
                    </details>
                    <x-primary-button>Extend</x-primary-button>
                </form>
            @else
                <p class="text-sm text-gray-600">No subscription yet — activate one first.</p>
            @endif
        </x-card>

        <x-card title="Expire now">
            @if ($summary['is_active'])
                <p class="text-sm text-gray-600 mb-3">The user keeps logging in but sees only the restricted number of entries.</p>
                <form method="POST" action="{{ route('superadmin.subscriptions.action', $user) }}" onsubmit="return confirm('Expire this subscription now?')">
                    @csrf
                    <input type="hidden" name="action" value="expire">
                    <x-danger-button>Expire</x-danger-button>
                </form>
            @else
                <p class="text-sm text-gray-600">Nothing to expire.</p>
            @endif
        </x-card>
    </div>

    <x-card title="Record a payment">
        <form method="POST" action="{{ route('superadmin.subscriptions.payments.store', $user) }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
            @csrf
            <x-field name="amount" label="Amount" type="number" step="0.01" min="0" required />
            <x-field name="payment_date" label="Date" type="date" :value="today()->toDateString()" required />
            <x-field name="transaction_ref" label="Transaction ref" />
            <x-select name="status" label="Status" :options="$paymentStatuses" value="paid" />
            <div><x-primary-button>Save payment</x-primary-button></div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="History">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1 pr-3">Start</th><th class="py-1 pr-3">End</th><th class="py-1 pr-3">Status</th><th class="py-1">By</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($history as $s)
                        <tr><td class="py-1 pr-3">{{ $s->start_date->format('d M Y') }}</td><td class="py-1 pr-3">{{ $s->end_date->format('d M Y') }}</td>
                            <td class="py-1 pr-3"><x-badge :color="$s->isCurrentlyActive() ? 'green' : 'red'">{{ $s->isCurrentlyActive() ? 'active' : 'expired' }}</x-badge></td>
                            <td class="py-1 text-gray-500">{{ $s->creator?->name ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-gray-500">No subscriptions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
        <x-card title="Payments">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b"><tr><th class="py-1 pr-3">Date</th><th class="py-1 pr-3">Amount</th><th class="py-1 pr-3">Ref</th><th class="py-1">Status</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $p)
                        <tr><td class="py-1 pr-3">{{ $p->payment_date->format('d M Y') }}</td><td class="py-1 pr-3">{{ number_format((float) $p->amount, 2) }}</td>
                            <td class="py-1 pr-3">{{ $p->transaction_ref ?? '—' }}</td><td class="py-1">{{ $p->status->label() }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-gray-500">No payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</x-page>
