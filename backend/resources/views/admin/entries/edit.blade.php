<x-page :title="'Edit Entry #'.$entry->id">
    <x-card>
        <form method="POST" action="{{ route('admin.entries.update', $entry) }}" enctype="multipart/form-data" class="space-y-5 max-w-xl"
              x-data="{ preview: null }">
            @csrf
            @method('PUT')

            <x-select name="category_id" label="Category" :options="$categories->pluck('name', 'id')->all()"
                :value="$entry->category_id" placeholder="Select category" required />
            @unless ($entry->category->isActive())
                <p class="-mt-3 text-xs text-yellow-800">The current category "{{ $entry->category->name }}" is inactive — choose an active one.</p>
            @endunless

            <div class="grid grid-cols-2 gap-4">
                <x-field name="entry_date" label="Date" type="date" :value="$entry->entry_date->toDateString()" required />
                <x-field name="entry_time" label="Time" type="time" :value="$entry->timeLabel()" required />
            </div>

            <div>
                <x-input-label value="Current image" />
                <img src="{{ $entry->imageUrl() }}" alt="Current image" class="mt-1 max-h-48 rounded border">
            </div>

            <div>
                <x-input-label for="image" value="Replace image (optional)" />
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                    class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-olive-50 file:px-4 file:py-2 file:text-olive-700"
                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                <p class="mt-1 text-xs text-gray-500">JPG, PNG or WebP, up to {{ round($maxKb / 1024, 1) }} MB. Leave empty to keep the current image.</p>
                <x-input-error :messages="$errors->get('image')" class="mt-1" />
                <template x-if="preview"><img :src="preview" class="mt-3 max-h-48 rounded border" alt="New image preview"></template>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>Save changes</x-primary-button>
                <x-link-button :href="url()->previous()" variant="secondary">Cancel</x-link-button>
            </div>
        </form>
    </x-card>

    @can('delete', $entry)
        <x-card title="Delete entry">
            <form method="POST" action="{{ route('admin.entries.destroy', $entry) }}" onsubmit="return confirm('Delete this entry and its image? This cannot be undone.')">
                @csrf @method('DELETE')
                <x-danger-button>Delete entry</x-danger-button>
            </form>
        </x-card>
    @endcan
</x-page>
