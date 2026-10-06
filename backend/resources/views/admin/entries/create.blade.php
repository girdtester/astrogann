<x-page title="Add New Entry">
    <x-card>
        @if ($categories->isEmpty())
            <p class="text-sm text-gray-600">There are no active categories yet.</p>
        @else
            <form method="POST" action="{{ route('admin.entries.store') }}" enctype="multipart/form-data" class="space-y-5 max-w-xl"
                  x-data="{ preview: null }">
                @csrf

                <x-select name="category_id" label="Category" :options="$categories->pluck('name', 'id')->all()"
                    :value="request('category_id')" placeholder="Select category" required />

                <div class="grid grid-cols-2 gap-4">
                    <x-field name="entry_date" label="Date" type="date" :value="today()->toDateString()" required />
                    <x-field name="entry_time" label="Time" type="time" :value="now()->format('H:i')" required />
                </div>

                <div>
                    <x-input-label for="image" value="Image" />
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required
                        class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-indigo-700"
                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG or WebP, up to {{ round($maxKb / 1024, 1) }} MB.</p>
                    <x-input-error :messages="$errors->get('image')" class="mt-1" />
                    <template x-if="preview"><img :src="preview" class="mt-3 max-h-64 rounded border" alt="Preview"></template>
                </div>

                <p class="text-xs text-gray-500">Entries cannot be edited or deleted after saving. Please check before submitting.</p>
                <x-primary-button>Save entry</x-primary-button>
            </form>
        @endif
    </x-card>
</x-page>
