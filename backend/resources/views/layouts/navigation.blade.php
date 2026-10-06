{{-- Top bar with grouped dropdown menus (DECISIONS D-70). Items come from App\Support\Navigation (permission-based). --}}
@php($isGroupActive = fn (array $item) => isset($item['children'])
    ? collect($item['active'])->contains(fn ($p) => request()->routeIs($p))
    : request()->routeIs($item['active']))
<nav x-data="{ open: false }" class="bg-white border-b border-gray-200 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex min-w-0">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/favicon-64.png') }}" alt="" class="h-9 w-9">
                        <span class="font-bold text-lg tracking-wide text-olive-700 whitespace-nowrap">{{ $appSettings['app_name'] }}</span>
                    </a>
                </div>

                <!-- Desktop menu -->
                <div class="hidden lg:flex lg:items-center lg:ms-8 gap-1">
                    @foreach ($navMenu as $item)
                        @php($active = $isGroupActive($item))
                        @if (isset($item['children']))
                            <div class="relative" x-data="{ dd: false }" @click.outside="dd = false" @keydown.escape="dd = false">
                                <button type="button" @click="dd = ! dd"
                                    class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap {{ $active ? 'bg-olive-50 text-olive-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                                    :aria-expanded="dd">
                                    {{ $item['label'] }}
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                                <div x-show="dd" x-cloak x-transition.origin.top.left
                                     class="absolute left-0 z-50 mt-1 w-56 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                    @foreach ($item['children'] as $child)
                                        <a href="{{ route($child['route']) }}"
                                           class="block px-4 py-2 text-sm {{ request()->routeIs($child['active']) ? 'bg-olive-50 font-semibold text-olive-800' : 'text-gray-700 hover:bg-gray-50' }}">
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a href="{{ route($item['route']) }}"
                               class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap {{ $active ? 'bg-olive-50 text-olive-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                                {{ $item['label'] }}
                                @if ($item['badge'] ?? null)
                                    <span class="ms-1 inline-flex items-center justify-center rounded-full bg-yellow-400 px-1.5 text-xs font-bold text-gray-900">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Account dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-4">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-600 bg-white hover:text-gray-900 focus:outline-none transition ease-in-out duration-150">
                            <div class="whitespace-nowrap">{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-gray-500">{{ Auth::user()->role->name }}</div>
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger (below 1024px) -->
            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none" aria-label="Menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile / tablet menu: groups shown as sections -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden border-t border-gray-200">
        <div class="pt-2 pb-3 space-y-1">
            @foreach ($navMenu as $item)
                @if (isset($item['children']))
                    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $item['label'] }}</div>
                    @foreach ($item['children'] as $child)
                        <x-responsive-nav-link :href="route($child['route'])" :active="request()->routeIs($child['active'])" class="ps-6">
                            {{ $child['label'] }}
                        </x-responsive-nav-link>
                    @endforeach
                @else
                    <x-responsive-nav-link :href="route($item['route'])" :active="request()->routeIs($item['active'])">
                        {{ $item['label'] }}
                        @if ($item['badge'] ?? null)
                            <span class="ms-1 inline-flex items-center justify-center rounded-full bg-yellow-400 px-1.5 text-xs font-bold text-gray-900">{{ $item['badge'] }}</span>
                        @endif
                    </x-responsive-nav-link>
                @endif
            @endforeach
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }} · {{ Auth::user()->role->name }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">{{ __('Profile') }}</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
