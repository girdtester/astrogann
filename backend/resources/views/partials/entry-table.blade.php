{{-- Entry list for staff. Edit / Delete show only when EntryPolicy allows it (DECISIONS D-71). --}}
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="text-left text-gray-500 border-b">
            <tr>
                <th class="py-2 pr-4">Image</th>
                <th class="py-2 pr-4">Category</th>
                <th class="py-2 pr-4">Entry date</th>
                <th class="py-2 pr-4">Entry time</th>
                @if ($showAdmin ?? false) <th class="py-2 pr-4">Added by</th> @endif
                <th class="py-2 pr-4">Uploaded</th>
                <th class="py-2 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($entries as $entry)
                <tr>
                    <td class="py-2 pr-4">
                        <a href="{{ $entry->imageUrl() }}" target="_blank" rel="noopener">
                            <img src="{{ $entry->imageUrl() }}" alt="Entry {{ $entry->id }}" class="h-14 w-24 object-cover rounded border" loading="lazy">
                        </a>
                    </td>
                    <td class="py-2 pr-4">{{ $entry->category->name }}</td>
                    <td class="py-2 pr-4">{{ $entry->entry_date->format('d M Y') }}</td>
                    <td class="py-2 pr-4">{{ $entry->timeLabel() }}</td>
                    @if ($showAdmin ?? false) <td class="py-2 pr-4">{{ $entry->admin->name }}</td> @endif
                    <td class="py-2 pr-4 text-gray-500">{{ $entry->created_at?->format('d M Y H:i') }}</td>
                    <td class="py-2 text-right whitespace-nowrap space-x-2">
                        @can('update', $entry)
                            <a href="{{ route('admin.entries.edit', $entry) }}" class="text-olive-700 hover:underline">Edit</a>
                        @endcan
                        @can('delete', $entry)
                            <form method="POST" action="{{ route('admin.entries.destroy', $entry) }}" class="inline" onsubmit="return confirm('Delete this entry and its image? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="text-yellow-700 hover:underline">Delete</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-gray-500">No entries found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $entries->links() }}</div>
