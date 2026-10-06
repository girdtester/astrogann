@props(['title', 'actions' => null])
<x-app-layout :title="$title">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
            @if ($actions)
                <div class="flex gap-2">{{ $actions }}</div>
            @endif
        </div>
    </x-slot>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        {{ $slot }}
    </div>
</x-app-layout>
