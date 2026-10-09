{{-- "Show More" / contact admin (spec 3.4). All text and contact details come from settings. --}}
@php($contact = app(\App\Services\SettingsService::class)->contact())
<div id="subscribe" x-data="{ open: {{ ($open ?? false) ? 'true' : 'false' }} }"
     @if ($highlight ?? false) x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))" @endif
     class="rounded-lg border p-4 {{ ($highlight ?? false) ? 'border-yellow-500 bg-yellow-100 ring-4 ring-yellow-300' : 'border-yellow-300 bg-yellow-50' }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-yellow-900">{{ $contact['text'] }}</p>
        <button type="button" @click="open = ! open" class="rounded-md bg-yellow-400 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-yellow-300">
            Show More
        </button>
    </div>
    <div x-show="open" x-cloak class="mt-3 flex flex-wrap gap-3 text-sm">
        @foreach ($contact['phones'] as $phone)
            <a href="tel:{{ $phone['tel'] }}" class="rounded-md bg-surface px-3 py-2 border border-gray-300 text-blue-700 hover:bg-gray-200">📞 Call {{ $phone['label'] }}</a>
        @endforeach
        @foreach ($contact['whatsapp'] as $wa)
            <a href="{{ $wa['url'] }}" target="_blank" rel="noopener" class="rounded-md bg-surface px-3 py-2 border border-gray-300 text-green-700 hover:bg-gray-200">💬 WhatsApp {{ $wa['label'] }}</a>
        @endforeach
        @if ($contact['email'])
            <a href="mailto:{{ $contact['email'] }}" class="rounded-md bg-surface px-3 py-2 border border-gray-300 text-blue-700 hover:bg-gray-200">✉️ {{ $contact['email'] }}</a>
        @endif
    </div>
</div>
