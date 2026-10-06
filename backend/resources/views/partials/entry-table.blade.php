{{-- Read-only entry list for staff (entries are immutable — no edit/delete actions anywhere). --}}
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="text-left text-gray-500 border-b">
            <tr>
                <th class="py-2 pr-4">Image</th>
                <th class="py-2 pr-4">Category</th>
                <th class="py-2 pr-4">Entry date</th>
                <th class="py-2 pr-4">Entry time</th>
                @if ($showAdmin ?? false) <th class="py-2 pr-4">Added by</th> @endif
                <th class="py-2">Uploaded</th>
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
                    <td class="py-2 text-gray-500">{{ $entry->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-500">No entries found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $entries->links() }}</div>
