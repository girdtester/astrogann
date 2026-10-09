<x-page title="Latest Entries">
    @if (! empty($settings['notice_text']))
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ $settings['notice_text'] }}</div>
    @endif

    {{-- Subscription strip --}}
    <div class="flex flex-wrap items-center gap-2 text-sm">
        @if ($subscription['is_active'])
            <x-badge color="green">Subscription active</x-badge>
            <span class="text-gray-600">until {{ \App\Support\Fmt::date($subscription['end_date']) }} ({{ $subscription['days_left'] }} days left)</span>
        @elseif ($subscription['status'] === 'expired')
            <x-badge color="yellow">Subscription expired</x-badge>
            <span class="text-gray-600">on {{ \App\Support\Fmt::date($subscription['end_date']) }}</span>
        @else
            <x-badge>No subscription</x-badge>
        @endif
    </div>

    @if ($tabs->isEmpty())
        <x-card><p class="text-sm text-gray-600">No categories are available yet.</p></x-card>
    @else
        {{-- Tabs: one per active category, order and names from the database (spec 3.9) --}}
        <div class="border-b border-gray-200 overflow-x-auto overflow-y-hidden">
            <nav class="-mb-px flex gap-1" aria-label="Categories">
                @foreach ($tabs as $tab)
                    <a href="{{ route('user.dashboard', ['tab' => $tab->tabKey()]) }}"
                       class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium {{ $current->is($tab) ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                       @if ($current->is($tab)) aria-current="page" @endif>{{ $tab->name }}</a>
                @endforeach
            </nav>
        </div>

        @if ($visible['entries']->isEmpty())
            <x-card><p class="text-sm text-gray-600">No entries in {{ $current->name }} yet.</p></x-card>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" x-data="{ zoom: null }">
                @foreach ($visible['entries'] as $entry)
                    <figure class="bg-surface shadow-sm rounded-lg overflow-hidden">
                        <button type="button" class="block w-full" @click="zoom = '{{ $entry->imageUrl() }}'">
                            <img src="{{ $entry->thumbUrl() }}" alt="{{ $current->name }} {{ \App\Support\Fmt::date($entry->entry_date) }} {{ \App\Support\Fmt::time($entry->entry_time) }}"
                                 class="w-full aspect-[5/3] object-cover" loading="lazy">
                        </button>
                        <figcaption class="flex justify-between px-3 py-2 text-sm">
                            <span class="font-medium text-gray-800">{{ \App\Support\Fmt::date($entry->entry_date) }}</span>
                            <span class="text-gray-600">{{ \App\Support\Fmt::time($entry->entry_time) }}</span>
                        </figcaption>
                    </figure>
                @endforeach

                <div x-show="zoom" x-cloak @click="zoom = null" @keydown.escape.window="zoom = null"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                    <img :src="zoom" class="max-h-full max-w-full rounded" alt="Entry image">
                </div>
            </div>
        @endif

        @if ($visible['restricted'])
            @include('partials.show-more', ['settings' => $settings, 'open' => $openSubscribe ?? false, 'highlight' => $openSubscribe ?? false])
        @endif
    @endif
</x-page>
