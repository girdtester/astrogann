@php($editing = $category->exists)
<x-page :title="$editing ? 'Edit category' : 'New category'">
    <x-slot name="actions">
        <x-link-button :href="route('superadmin.categories.index')" variant="secondary">Back</x-link-button>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ $editing ? route('superadmin.categories.update', $category) : route('superadmin.categories.store') }}" class="space-y-5 max-w-xl">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field name="name" label="Name (tab title)" :value="$category->name" required help="e.g. Nifty 50" />
            <x-field name="code" label="Code" :value="$category->code" required help="Letters, numbers, _ or -. e.g. NIFTY50" />
            <x-field name="sort_order" label="Sort order (tab position, lowest first)" type="number" min="0" :value="$category->sort_order" />
            <x-select name="status" label="Status" :options="['active' => 'Active (shown to users)', 'inactive' => 'Inactive (hidden)']" :value="$category->status?->value ?? 'active'" />

            <x-primary-button>{{ $editing ? 'Save' : 'Create' }}</x-primary-button>
        </form>
    </x-card>
</x-page>
