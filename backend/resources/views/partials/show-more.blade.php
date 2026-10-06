{{-- "Show More" / contact admin (spec 3.4). All text and contact details come from settings. --}}
@php($wa = preg_replace('/\D+/', '', (string) ($settings['contact_whatsapp'] ?? '')))
<div x-data="{ open: {{ ($open ?? false) ? 'true' : 'false' }} }" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-amber-900">{{ $settings['show_more_text'] }}</p>
        <button type="button" @click="open = ! open" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
            Show More
        </button>
    </div>
    <div x-show="open" x-cloak class="mt-3 flex flex-wrap gap-3 text-sm">
        @if (! empty($settings['contact_phone']))
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $settings['contact_phone']) }}" class="rounded-md bg-white px-3 py-2 border hover:bg-gray-50">📞 {{ $settings['contact_phone'] }}</a>
        @endif
        @if ($wa)
            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="rounded-md bg-white px-3 py-2 border hover:bg-gray-50">💬 WhatsApp</a>
        @endif
        @if (! empty($settings['contact_email']))
            <a href="mailto:{{ $settings['contact_email'] }}" class="rounded-md bg-white px-3 py-2 border hover:bg-gray-50">✉️ {{ $settings['contact_email'] }}</a>
        @endif
    </div>
</div>
