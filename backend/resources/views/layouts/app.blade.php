<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ $appSettings['app_name'] ?? config('app.name') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo-192.png') }}">
        <meta name="theme-color" content="#6b7c2e">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-surface shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @if (auth()->user()?->isTrader())
                {{-- Live notifications (D-73): app.js polls this URL and shows toasts + updates the unread badge. --}}
                <div id="notify-root" data-poll-url="{{ route('user.notifications.poll') }}"
                     data-unread="{{ app(\App\Services\NotificationService::class)->unreadCount(auth()->user()) }}"
                     data-last-id="{{ (int) auth()->user()->appNotifications()->max('id') }}"
                     x-data class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
                    <template x-for="t in $store.notify.toasts" :key="t.id">
                        <a :href="t.url" x-transition
                           class="pointer-events-auto block rounded-lg border border-olive-200 bg-white p-3 shadow-lg hover:bg-olive-50">
                            <div class="flex items-center gap-2 text-sm font-semibold text-olive-800">
                                <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                                <span x-text="t.title"></span>
                            </div>
                            <div class="mt-1 text-sm text-gray-700" x-text="t.message"></div>
                        </a>
                    </template>
                </div>
            @endif

            <!-- Page Content -->
            <main class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <x-flash />
                </div>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
