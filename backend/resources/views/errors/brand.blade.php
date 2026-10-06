{{-- Dark ASTRO GANN error page. Self-contained (no DB, no Vite) so it renders even when the app is broken. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · ASTRO GANN</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <style>
        
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #f9fafb; color: #1f2937; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .box { text-align: center; padding: 2rem; }
        img { width: 96px; height: 96px; }
        .code { font-size: 3rem; font-weight: 700; color: #6b7c2e; margin: 1rem 0 .25rem; }
        .msg { color: #4b5563; margin-bottom: 1.5rem; }
        a { display: inline-block; padding: .6rem 1.2rem; border-radius: .4rem; background: #6b7c2e; color: #ffffff;
            text-decoration: none; font-weight: 600; }
        a:hover { background: #556227; }
    </style>
</head>
<body>
<div class="box">
    <img src="{{ asset('images/logo-192.png') }}" alt="ASTRO GANN">
    <div class="code">@yield('code')</div>
    <div class="msg">@yield('message')</div>
    <a href="{{ url('/') }}">Go to home</a>
</div>
</body>
</html>
