<x-page title="Categories">
    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Code</th><th class="py-2 pr-4">Entries</th><th class="py-2 text-right"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="py-2 pr-4 font-medium">{{ $category->name }}</td>
                            <td class="py-2 pr-4"><code>{{ $category->code }}</code></td>
                            <td class="py-2 pr-4">{{ $category->entries_count }}</td>
                            <td class="py-2 text-right">
                                @can('entries.create')
                                    <x-action :href="route('admin.entries.create', ['category_id' => $category->id])" variant="primary" icon="plus">Add entry</x-action>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-500">No active categories.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-page>
