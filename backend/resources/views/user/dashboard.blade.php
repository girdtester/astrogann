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
            {{-- Table: Sr. No | Date | Time | Image — tap the image for the full HD picture (D-101) --}}
            <div class="bg-surface shadow-sm rounded-lg overflow-x-auto" x-data="{ zoom: null, caption: '' }">
                <table class="min-w-full text-sm">
                    <thead class="bg-olive-50 text-left text-olive-900">
                        <tr>
                            <th class="w-14 px-3 py-2.5 font-semibold sm:w-20 sm:px-4">Sr. No</th>
                            <th class="px-3 py-2.5 font-semibold sm:px-4">Date</th>
                            <th class="px-3 py-2.5 font-semibold sm:px-4">Time</th>
                            <th class="px-3 py-2.5 font-semibold sm:px-4">Image</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($visible['entries'] as $entry)
                            @php($caption = $current->name.' · '.\App\Support\Fmt::date($entry->entry_date).', '.\App\Support\Fmt::time($entry->entry_time))
                            <tr class="align-middle hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-500 sm:px-4">{{ $loop->iteration }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap sm:px-4">{{ \App\Support\Fmt::date($entry->entry_date) }}</td>
                                <td class="px-3 py-2 text-gray-700 whitespace-nowrap sm:px-4">{{ \App\Support\Fmt::time($entry->entry_time) }}</td>
                                <td class="px-3 py-2 sm:px-4">
                                    <button type="button" class="group relative block overflow-hidden rounded-md border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-olive-500"
                                            @click="zoom = @js($entry->imageUrl()); caption = @js($caption)" aria-label="Open HD image: {{ $caption }}">
                                        <img src="{{ $entry->thumbUrl() }}" alt="{{ $caption }}" loading="lazy"
                                             class="h-14 w-24 object-cover transition group-hover:opacity-90 sm:h-20 sm:w-36">
                                        <span class="absolute bottom-1 right-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold text-white">HD</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Full HD viewer: fits the screen; "Open full size" shows every pixel (zoom / pinch in the browser) --}}
                <div x-show="zoom" x-cloak @keydown.escape.window="zoom = null"
                     class="fixed inset-0 z-50 flex flex-col bg-black/90" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-white">
                        <span class="font-semibold" x-text="caption"></span>
                        <div class="flex items-center gap-2">
                            <a :href="zoom" target="_blank" rel="noopener" class="rounded-md border border-white/40 px-3 py-1.5 font-semibold hover:bg-white/10">Open full size</a>
                            <button type="button" @click="zoom = null" class="rounded-md bg-white px-3 py-1.5 font-semibold text-gray-900 hover:bg-gray-100">Close</button>
                        </div>
                    </div>
                    <div class="flex flex-1 items-center justify-center overflow-auto p-2" @click.self="zoom = null">
                        <img :src="zoom" :alt="caption" class="max-h-full max-w-full object-contain">
                    </div>
                </div>
            </div>
        @endif

        @if ($visible['restricted'])
            @include('partials.show-more', ['settings' => $settings, 'open' => $openSubscribe ?? false, 'highlight' => $openSubscribe ?? false])
        @endif
    @endif
</x-page>
