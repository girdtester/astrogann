@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
@php($key = str_replace(['[', ']'], ['.', ''], $name))
<div>
    <x-input-label :for="$key" :value="$label" />
    <x-text-input :id="$key" :name="$name" :type="$type" class="mt-1 block w-full"
        :value="$type === 'password' ? null : old($key, $value)" :required="$required" {{ $attributes }} />
    @if ($help)
        <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
    @endif
    <x-input-error :messages="$errors->get($key)" class="mt-1" />
</div>
