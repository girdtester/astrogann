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
                        @if ($n->announcement_id)
                            <div class="mb-0.5 flex items-center gap-2">
                                <x-badge color="blue">Announcement</x-badge>
                                <span class="text-sm font-bold text-gray-900">{{ $n->title() }}</span>
                            </div>
                            <div class="text-sm whitespace-pre-line {{ $n->is_read ? 'text-gray-700' : 'font-semibold text-gray-900' }}">{{ $n->message }}</div>
                        @else
                            <div class="text-sm {{ $n->is_read ? 'text-gray-700' : 'font-semibold text-gray-900' }}">{{ $n->message }}</div>
                        @endif
                        <div class="text-xs text-gray-500"><span title="{{ \App\Support\Fmt::dateTime($n->created_at) }}">{{ \App\Support\Fmt::dateTime($n->created_at) }} · {{ \App\Support\Fmt::ago($n->created_at) }}</span></div>
                    </div>
                    @if ($n->announcement_id)
                        @unless ($n->is_read)
                            <x-action :action="route('user.notifications.read', $n->id)" variant="secondary" icon="check">Mark read</x-action>
                        @endunless
                    @else
                        <x-action :action="route('user.notifications.read', $n->id)" variant="primary" icon="eye">View</x-action>
                    @endif
                </li>
            @empty
                <li class="py-6 text-center text-sm text-gray-500">No notifications yet.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $notifications->links() }}</div>
    </x-card>
</x-page>
