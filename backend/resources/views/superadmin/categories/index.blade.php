<x-page title="Categories">
    <x-slot name="actions">
        <x-link-button :href="route('superadmin.categories.create')">+ New category</x-link-button>
    </x-slot>

    <x-card>
        <p class="text-sm text-gray-600 mb-4">Active categories appear as tabs for users on web and mobile, in sort order. Changes apply immediately — no app update needed.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr><th class="py-2 pr-4">Order</th><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Code</th><th class="py-2 pr-4">Entries</th><th class="py-2 pr-4">Status</th><th class="py-2 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="py-2 pr-4">{{ $category->sort_order }}</td>
                            <td class="py-2 pr-4 font-medium text-gray-900">{{ $category->name }}</td>
                            <td class="py-2 pr-4"><code>{{ $category->code }}</code></td>
                            <td class="py-2 pr-4">{{ $category->entries_count }}</td>
                            <td class="py-2 pr-4"><x-badge :color="$category->isActive() ? 'green' : 'yellow'">{{ $category->status->value }}</x-badge></td>
                            <td class="py-2 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('superadmin.categories.edit', $category) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('superadmin.categories.status', $category) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="{{ $category->isActive() ? 'text-yellow-600' : 'text-green-700' }} hover:underline">{{ $category->isActive() ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                @if ($category->entries_count === 0)
                                    <form method="POST" action="{{ route('superadmin.categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Delete {{ $category->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="text-yellow-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-page>
