<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $appSettings['app_name'] ?? config('app.name') }}</title>

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
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    <span class="flex flex-col items-center gap-3"><img src="{{ asset('images/logo-192.png') }}" alt="" class="h-24 w-24"><span class="text-3xl font-bold tracking-wide text-indigo-700">{{ $appSettings['app_name'] ?? config('app.name') }}</span></span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-surface shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
