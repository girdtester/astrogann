<x-page :title="auth()->user()->role->name.' Dashboard'">
    @can('entries.create')
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-surface shadow-sm rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">My entries today</div>
                <div class="mt-1 text-2xl font-bold">{{ $myEntriesToday }}</div>
            </div>
            <div class="bg-surface shadow-sm rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">My entries total</div>
                <div class="mt-1 text-2xl font-bold">{{ $myEntriesTotal }}</div>
            </div>
            <a href="{{ route('admin.entries.create') }}" class="bg-indigo-600 text-white rounded-lg p-4 flex items-center justify-center font-semibold hover:bg-indigo-700 md:col-span-2">+ Add new entry</a>
        </div>
    @endcan

    <x-card title="Your screens">
        @if ($links)
            <div class="flex flex-wrap gap-2">
                @foreach ($links as $link)
                    <x-link-button :href="route($link['route'])" variant="secondary">{{ $link['label'] }}</x-link-button>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-600">Your role has no permissions yet. Ask the Super Admin to assign some.</p>
        @endif
    </x-card>

    @if ($latest->isNotEmpty())
        <x-card title="My latest entries">
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($latest as $entry)
                    <li class="py-2 flex items-center gap-3">
                        <img src="{{ $entry->imageUrl() }}" class="h-10 w-16 object-cover rounded border" alt="">
                        <span class="font-medium">{{ $entry->category->name }}</span>
                        <span class="text-gray-500">{{ \App\Support\Fmt::date($entry->entry_date) }}, {{ \App\Support\Fmt::time($entry->entry_time) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-page>
