@php($fmt = \App\Support\Fmt::class)
<x-page title="Expiry Reminders">
    <x-slot name="actions">
        <x-action :action="route('superadmin.reminders.collect')" icon="plus">Collect now</x-action>
        <x-action :action="route('superadmin.reminders.send')" variant="primary" icon="arrow">Send pending now</x-action>
        @if (($counts['failed'] ?? 0) > 0)
            <x-action :action="route('superadmin.reminders.retry')" variant="warning" icon="check">Retry failed</x-action>
        @endif
    </x-slot>

    <x-card>
        <p class="mb-4 text-sm text-gray-600">
            Every day at <strong>6:00 PM</strong> the system saves all users whose subscription expires within
            <strong>{{ app(\App\Services\SettingsService::class)->reminderDays() }} days</strong> (each user only once per expiry date).
            The cron then e-mails them <strong>one by one</strong> ({{ config('astrogann.mail_per_minute') }} per minute).
        </p>

        <div class="mb-4 grid grid-cols-3 gap-3 text-center text-sm">
            <a href="{{ request()->fullUrlWithQuery(['status' => 'pending', 'page' => null]) }}" class="rounded-lg border border-blue-200 bg-blue-50 p-3 hover:bg-blue-100">
                <div class="text-2xl font-bold text-blue-800">{{ number_format($counts['pending'] ?? 0) }}</div><div class="text-blue-800">Waiting to send</div>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'sent', 'page' => null]) }}" class="rounded-lg border border-olive-200 bg-olive-50 p-3 hover:bg-olive-100">
                <div class="text-2xl font-bold text-olive-800">{{ number_format($counts['sent'] ?? 0) }}</div><div class="text-olive-800">Sent</div>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'failed', 'page' => null]) }}" class="rounded-lg border border-yellow-300 bg-yellow-50 p-3 hover:bg-yellow-100">
                <div class="text-2xl font-bold text-yellow-800">{{ number_format($counts['failed'] ?? 0) }}</div><div class="text-yellow-800">Failed / skipped</div>
            </a>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4 items-end">
            <x-field name="q" label="User (name or email)" :value="$filters['q'] ?? null" />
            <x-select name="status" label="Status" :options="['pending' => 'Waiting to send', 'sent' => 'Sent', 'failed' => 'Failed / skipped']" :value="$filters['status'] ?? null" placeholder="All" />
            <input type="hidden" name="sort" value="{{ $sorting['sort'] }}"><input type="hidden" name="dir" value="{{ $sorting['dir'] }}">
            <div><x-primary-button>Apply filters</x-primary-button></div>
        </form>

        <x-list-toolbar :paginator="$reminders" />
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr>
                        <th class="py-2 pr-4">User</th>
                        <x-sort-th column="end" label="Expires on" :sorting="$sorting" />
                        <x-sort-th column="collected" label="Saved on" :sorting="$sorting" />
                        <th class="py-2 pr-4">Status</th>
                        <x-sort-th column="sent" label="E-mail sent" :sorting="$sorting" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($reminders as $r)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="py-2 pr-4"><div class="font-medium text-gray-900">{{ $r->user?->name }}</div><div class="text-xs text-gray-500">{{ $r->user?->email }}</div></td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $fmt::date($r->end_date) }}<div class="text-xs text-gray-500">{{ $r->days_left }} day(s) left when saved</div></td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $fmt::dateTime($r->created_at) }}</td>
                            <td class="py-2 pr-4">
                                @if ($r->status === 'sent') <x-badge color="green">sent</x-badge>
                                @elseif ($r->status === 'pending') <x-badge color="blue">waiting</x-badge>
                                @else <x-badge color="yellow">failed</x-badge>
                                @endif
                                @if ($r->attempts > 0 && $r->status !== 'sent') <span class="text-xs text-gray-500">{{ $r->attempts }} attempt(s)</span> @endif
                                @if ($r->last_error) <div class="mt-1 max-w-xs text-xs text-yellow-800">{{ $r->last_error }}</div> @endif
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $r->sent_at ? $fmt::dateTime($r->sent_at) : '—' }}<div class="text-xs text-gray-500">{{ $fmt::ago($r->sent_at) }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No reminders saved yet. They are collected every day at 6:00 PM (or press "Collect now").</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reminders->links() }}</div>
    </x-card>
</x-page>
