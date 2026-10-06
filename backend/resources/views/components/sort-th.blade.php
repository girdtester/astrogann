{{-- Sortable column header (D-77). Keeps every other filter in the query string and resets to page 1. --}}
@props(['column', 'label', 'sorting', 'align' => 'left'])
@php
    $isActive = ($sorting['sort'] ?? null) === $column;
    $nextDir = $isActive && $sorting['dir'] === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir, 'page' => null]);
@endphp
<th {{ $attributes->merge(['class' => 'py-2 pr-4 whitespace-nowrap text-'.$align]) }}
    @if ($isActive) aria-sort="{{ $sorting['dir'] === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $url }}" class="group inline-flex items-center gap-1 {{ $isActive ? 'text-olive-700 font-semibold' : 'hover:text-gray-800' }}">
        {{ $label }}
        <span class="text-xs {{ $isActive ? '' : 'opacity-0 group-hover:opacity-50' }}">
            {{ $isActive && $sorting['dir'] === 'asc' ? '▲' : '▼' }}
        </span>
    </a>
</th>
