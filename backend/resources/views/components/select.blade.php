@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null])
<div>
    @if ($label)
        <x-input-label :for="$name" :value="$label" />
    @endif
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
