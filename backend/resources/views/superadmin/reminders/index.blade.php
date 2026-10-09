@php($fmt = \App\Support\Fmt::class)
<x-page title="Expiry Reminders">
    <x-slot name="actions">
        <x-action :action="route('superadmin.reminders.collect')" icon="plus">Collect now</x-action>
        <x-action :action="route('superadmin.reminders.send')" variant="primary" icon="arrow">Send pending now</x-action>
        @if (($counts['failed'] ?? 0) > 0)
            <x-action :action="route('superadmin.reminders.retry')" variant="warning" icon="check">Retry failed</x-action>
        @endif
    </x-slot>

    {{-- Live check (D-97) --}}
    <x-card title="System check (live)" class="mb-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 text-sm">
            <div class="rounded-lg border p-3 {{ $health['cron_ok'] ? 'border-olive-200 bg-olive-50' : 'border-yellow-300 bg-yellow-50' }}">
                <div class="font-semibold {{ $health['cron_ok'] ? 'text-olive-800' : 'text-yellow-800' }}">
                    Cron: {{ $health['cron_ok'] ? 'running ✓' : 'NOT running' }}
                </div>
                <div class="text-gray-600">
                    Last run: {{ $health['cron_last_run'] ? \App\Support\Fmt::dateTime($health['cron_last_run']).' ('.$health['cron_last_run']->diffForHumans().')' : 'never' }}
                </div>
                @unless ($health['cron_ok'])
                    <div class="mt-1 text-xs text-gray-600">Check cPanel → Cron Jobs: <code>…/artisan schedule:run</code> every 1–5 minutes.</div>
                @endunless
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                <div class="font-semibold text-blue-800">Queue</div>
                <div class="text-gray-600">{{ number_format($health['queued']) }} job(s) waiting · {{ number_format($health['failed_jobs']) }} failed</div>
                <div class="mt-1 text-xs text-gray-600">Waiting jobs are sent by the next cron run.</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div class="font-semibold text-gray-800">E-mail</div>
                <div class="text-gray-600">{{ $health['mailer'] }}{{ $health['mail_host'] ? ' · '.$health['mail_host'] : '' }}</div>
                <div class="text-gray-600">From: {{ $health['mail_from'] }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('superadmin.reminders.test-mail') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="sm:w-80"><x-field name="email" type="email" label="Send a test e-mail now to" :value="auth()->user()->email" required /></div>
            <div><x-primary-button>Send test e-mail</x-primary-button></div>
            <div><a href="{{ route('superadmin.diagnostics.mail') }}" target="_blank" class="text-sm font-semibold text-blue-700 underline">Full mail check (ports + settings)</a></div>
        </form>
        <p class="mt-3 text-xs text-gray-500">
            Test the expiry e-mail today: set a test user's subscription to end in {{ app(\App\Services\SettingsService::class)->reminderDays() }} days →
            <strong>Collect now</strong> → <strong>Send pending now</strong> (or wait for the next cron run).
        </p>
    </x-card>

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
