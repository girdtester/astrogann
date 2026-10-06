<x-page title="Notifications">
    @if ($unread > 0)
        <x-slot name="actions">
            <form method="POST" action="{{ route('user.notifications.read-all') }}">
                @csrf
                <x-secondary-button type="submit">Mark all as read ({{ $unread }})</x-secondary-button>
            </form>
        </x-slot>
    @endif

    <x-card>
        <ul class="divide-y divide-gray-100">
            @forelse ($notifications as $n)
                <li class="py-3 flex items-start justify-between gap-3 {{ $n->is_read ? '' : 'bg-indigo-50/50 -mx-3 px-3 rounded' }}">
                    <div>
                        <div class="text-sm {{ $n->is_read ? 'text-gray-700' : 'font-semibold text-gray-900' }}">{{ $n->message }}</div>
                        <div class="text-xs text-gray-500">{{ $n->created_at?->format('d M Y H:i') }} · {{ $n->type->label() }}</div>
                    </div>
                    <form method="POST" action="{{ route('user.notifications.read', $n->id) }}">
                        @csrf
                        <button class="text-sm text-indigo-600 hover:underline whitespace-nowrap">{{ $n->category_id ? 'View' : 'Open' }}</button>
                    </form>
                </li>
            @empty
                <li class="py-6 text-center text-sm text-gray-500">No notifications yet.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $notifications->links() }}</div>
    </x-card>
</x-page>
