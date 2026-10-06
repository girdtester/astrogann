@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    // Dates and times use the themed picker (D-74); the submitted value stays Y-m-d / H:i.
    $picker = in_array($type, ['date', 'time'], true) ? $type : null;
@endphp
<div>
    <x-input-label :for="$key" :value="$label" />
    <div class="relative mt-1">
        <x-text-input :id="$key" :name="$name" :type="$picker ? 'text' : $type"
            class="block w-full {{ $picker ? 'pe-10' : '' }}"
            :value="$type === 'password' ? null : old($key, $value)" :required="$required"
            :data-picker="$picker" :autocomplete="$picker ? 'off' : null" {{ $attributes }} />
        @if ($picker === 'date')
            <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-olive-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        @elseif ($picker === 'time')
            <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-olive-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @endif
    </div>
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    <x-input-error :messages="$errors->get($key)" class="mt-1" />
</div>
