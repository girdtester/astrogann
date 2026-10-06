{{--
    Real buttons for table/row actions (D-79). Use :href for links, or :action (+ method) for POST/PATCH/DELETE forms.
    variant: primary (olive) | secondary (white) | warning (yellow) | info (light blue)
    icon: edit | trash | power | check | eye | money | arrow | plus
--}}
@props(['href' => null, 'action' => null, 'method' => 'POST', 'variant' => 'secondary', 'icon' => null, 'confirm' => null])
@php
    $styles = [
        'primary' => 'bg-olive-600 text-white border-olive-600 hover:bg-olive-700 focus-visible:ring-olive-500',
        'secondary' => 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400 focus-visible:ring-olive-500',
        'warning' => 'bg-yellow-400 text-gray-900 border-yellow-500 hover:bg-yellow-300 focus-visible:ring-yellow-500',
        'info' => 'bg-blue-50 text-blue-800 border-blue-200 hover:bg-blue-100 focus-visible:ring-blue-500',
    ];
    $classes = 'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-semibold shadow-sm transition '
        .'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 whitespace-nowrap '.($styles[$variant] ?? $styles['secondary']);
    $paths = [
        'edit' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z',
        'trash' => 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0115.916 21.75H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
        'power' => 'M5.636 5.636a9 9 0 1012.728 0M12 3v9',
        'check' => 'M4.5 12.75l6 6 9-13.5',
        'eye' => 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'money' => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
        'arrow' => 'M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3',
        'plus' => 'M12 4.5v15m7.5-7.5h-15',
    ];
@endphp
@php($iconSvg = $icon && isset($paths[$icon])
    ? '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$paths[$icon].'"/></svg>'
    : '')
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{!! $iconSvg !!}<span>{{ $slot }}</span></a>
@else
    <form method="POST" action="{{ $action }}" class="inline"
          @if ($confirm) onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})" @endif>
        @csrf
        @if (strtoupper($method) !== 'POST') @method($method) @endif
        <button type="submit" {{ $attributes->merge(['class' => $classes]) }}>{!! $iconSvg !!}<span>{{ $slot }}</span></button>
    </form>
@endif
