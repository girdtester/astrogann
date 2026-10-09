@php($fmt = \App\Support\Fmt::class)
<x-page title="Announcements">
    <x-card title="New announcement" class="mb-6">
        <p class="mb-4 text-sm text-gray-600">
            Appears in users' <strong>Notifications</strong> on the website and in the app (with the unread badge and the pop-up),
            and as a phone notification when push is switched on.
        </p>
        <form method="POST" action="{{ route('superadmin.announcements.store') }}" class="space-y-4"
              onsubmit="return confirm('Send this announcement to the selected users now?')">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2"><x-field name="title" label="Title" maxlength="100" required help="Short heading, e.g. Market holiday on Friday" /></div>
                <x-select name="audience" label="Send to" :options="$audiences" :value="old('audience', 'all')" />
            </div>
            <div x-data="{ n: {{ strlen(old('message', '')) }} }">
                <x-input-label for="message" value="Message" />
                <textarea id="message" name="message" rows="3" maxlength="500" required @input="n = $event.target.value.length"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('message') }}</textarea>
                <div class="mt-1 flex justify-between text-xs text-gray-500"><span>Plain text, up to 500 characters.</span><span x-text="n + ' / 500'"></span></div>
                <x-input-error :messages="$errors->get('message')" class="mt-1" />
            </div>
            <x-primary-button>Send announcement</x-primary-button>
        </form>
    </x-card>

    <x-card title="Sent announcements">
        <x-list-toolbar :paginator="$announcements" />
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr>
                        <th class="py-2 pr-4">Announcement</th>
                        <th class="py-2 pr-4">Sent to</th>
                        <th class="py-2 pr-4">Delivered / read</th>
                        <th class="py-2 pr-4">Sent</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($announcements as $a)
                        <tr class="align-top hover:bg-gray-50">
                            <td class="py-2 pr-4 max-w-md">
                                <div class="font-medium text-gray-900">{{ $a->title }}</div>
                                <div class="text-gray-600 whitespace-pre-line">{{ $a->message }}</div>
                            </td>
                            <td class="py-2 pr-4">{{ $a->audienceLabel() }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ number_format($a->recipients) }} users<div class="text-xs text-gray-500">{{ number_format($a->read_count) }} read</div></td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $fmt::dateTime($a->created_at) }}<div class="text-xs text-gray-500">{{ $fmt::ago($a->created_at) }} · {{ $a->author?->name ?? '—' }}</div></td>
                            <td class="py-2 text-right">
                                <x-action :action="route('superadmin.announcements.destroy', $a)" method="DELETE" variant="warning" icon="trash"
                                    :confirm="'Delete “'.$a->title.'”? It disappears from every user\'s Notifications.'">Delete</x-action>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No announcements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $announcements->links() }}</div>
    </x-card>
</x-page>
