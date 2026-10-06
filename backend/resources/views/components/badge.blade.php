@props(['color' => 'gray'])
@php
    $map = [
        'green' => 'bg-green-100 text-green-800', 'yellow' => 'bg-yellow-100 text-yellow-800',
        'yellow' => 'bg-yellow-100 text-yellow-800', 'blue' => 'bg-blue-100 text-blue-800',
        'gray' => 'bg-gray-100 text-gray-700', 'indigo' => 'bg-indigo-100 text-indigo-800',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.($map[$color] ?? $map['gray'])]) }}>{{ $slot }}</span>
