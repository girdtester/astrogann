@props(['href', 'variant' => 'primary'])
@php
    $classes = $variant === 'primary'
        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
        : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50';
@endphp
<a href="{{ $href }}" {{ $attributes->merge(['class' => "inline-flex items-center rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest {$classes}"]) }}>{{ $slot }}</a>
