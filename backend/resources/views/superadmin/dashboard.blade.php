<x-page title="Super Admin Dashboard">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach ($counts as $label => $value)
            <div class="bg-surface shadow-sm rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <x-card title="Recent activity">
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($recentLogs as $log)
                <li class="py-2 flex flex-wrap justify-between gap-2">
                    <span><x-badge color="indigo">{{ $log->action }}</x-badge> {{ $log->description }}</span>
                    <span class="text-gray-500">{{ $log->user?->email ?? 'system' }} · {{ $log->created_at?->diffForHumans() }}</span>
                </li>
            @empty
                <li class="py-2 text-gray-500">No activity yet.</li>
            @endforelse
        </ul>
        @can('logs.view')
            <div class="mt-4"><a href="{{ route('superadmin.logs.index') }}" class="text-sm text-indigo-600 hover:underline">All logs →</a></div>
        @endcan
    </x-card>
</x-page>
