{{--
    Entry list for staff. Edit / Delete show only when EntryPolicy allows it (DECISIONS D-71).
    "Market date & time" = the date/time the admin chose for the entry; "Added on" = when it was saved (D-78).
--}}
@php($fmt = \App\Support\Fmt::class)
<x-list-toolbar :paginator="$entries" />
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="text-left text-gray-500 border-b">
            <tr>
                <th class="py-2 pr-4">Image</th>
                <x-sort-th column="category" label="Category" :sorting="$sorting" />
                <x-sort-th column="entry" label="Market date & time" :sorting="$sorting" title="The date and time chosen for this entry" />
                @if ($showAdmin ?? false) <x-sort-th column="admin" label="Added by" :sorting="$sorting" /> @endif
                <x-sort-th column="uploaded" label="Added on" :sorting="$sorting" title="When the entry was saved in the system" />
                <th class="py-2 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($entries as $entry)
                <tr class="hover:bg-gray-50">
                    <td class="py-2 pr-4">
                        <a href="{{ $entry->imageUrl() }}" target="_blank" rel="noopener">
                            <img src="{{ $entry->imageUrl() }}" alt="Entry {{ $entry->id }}" class="h-14 w-24 object-cover rounded border" loading="lazy">
                        </a>
                    </td>
                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $entry->category->name }}</td>
                    <td class="py-2 pr-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900">{{ $fmt::date($entry->entry_date) }}</div>
                        <div class="text-gray-600">{{ $fmt::time($entry->entry_time) }}</div>
                    </td>
                    @if ($showAdmin ?? false) <td class="py-2 pr-4">{{ $entry->admin->name }}</td> @endif
                    <td class="py-2 pr-4 whitespace-nowrap" title="{{ $fmt::dateTime($entry->created_at) }}">
                        <div class="text-gray-900">{{ $fmt::dateTime($entry->created_at) }}</div>
                        <div class="text-xs text-gray-500">{{ $fmt::ago($entry->created_at) }}</div>
                    </td>
                    <td class="py-2 text-right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <x-action :href="$entry->imageUrl()" icon="eye" target="_blank" rel="noopener">View</x-action>
                            @can('update', $entry)
                                <x-action :href="route('admin.entries.edit', $entry)" variant="primary" icon="edit">Edit</x-action>
                            @endcan
                            @can('delete', $entry)
                                <x-action :action="route('admin.entries.destroy', $entry)" method="DELETE" variant="warning" icon="trash"
                                    confirm="Delete this entry and its image? This cannot be undone.">Delete</x-action>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-500">No entries match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $entries->links() }}</div>
