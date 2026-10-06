<x-page title="My Entries">
    @can('entries.create')
        <x-slot name="actions">
            <x-link-button :href="route('admin.entries.create')">+ Add entry</x-link-button>
        </x-slot>
    @endcan

    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4 items-end">
            <x-select name="category_id" label="Category" :options="$categories->pluck('name', 'id')->all()" :value="$filters['category_id'] ?? null" placeholder="All" />
            <x-field name="from" label="From" type="date" :value="$filters['from'] ?? null" />
            <x-field name="to" label="To" type="date" :value="$filters['to'] ?? null" />
            <div><x-secondary-button type="submit">Filter</x-secondary-button></div>
        </form>

        @include('partials.entry-table', ['entries' => $entries, 'showAdmin' => false])
    </x-card>
</x-page>
