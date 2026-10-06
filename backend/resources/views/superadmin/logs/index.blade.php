<x-page title="Reports / Logs">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-3 mb-4 items-end">
            <x-select name="action" label="Action" :options="$actions->mapWithKeys(fn ($a) => [$a => $a])->all()" :value="$filters['action'] ?? null" placeholder="All actions" />
            <x-field name="user" label="User (name or email)" :value="$filters['user'] ?? null" />
            <x-field name="from" label="From" type="date" :value="$filters['from'] ?? null" />
            <x-field name="to" label="To" type="date" :value="$filters['to'] ?? null" />
            <input type="hidden" name="sort" value="{{ $sorting['sort'] }}"><input type="hidden" name="dir" value="{{ $sorting['dir'] }}">
            <div><x-primary-button>Apply filters</x-primary-button></div>
        </form>

        <x-list-toolbar :paginator="$logs" />
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr><x-sort-th column="date" label="When" :sorting="$sorting" /><th class="py-2 pr-4">User</th><x-sort-th column="action" label="Action" :sorting="$sorting" /><th class="py-2 pr-4">Details</th><th class="py-2">IP</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="py-2 pr-4 whitespace-nowrap" title="{{ $log->created_at?->format('d M Y, g:i:s A') }}">{{ \App\Support\Fmt::dateTime($log->created_at) }}<div class="text-xs text-gray-500">{{ \App\Support\Fmt::ago($log->created_at) }}</div></td>
                            <td class="py-2 pr-4">{{ $log->user?->email ?? 'system' }}</td>
                            <td class="py-2 pr-4"><x-badge color="indigo">{{ $log->action }}</x-badge></td>
                            <td class="py-2 pr-4">{{ $log->description }}</td>
                            <td class="py-2 text-gray-500">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No log entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </x-card>
</x-page>
