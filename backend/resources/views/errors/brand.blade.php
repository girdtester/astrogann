{{-- Dark ASTRO GANN error page. Self-contained (no DB, no Vite) so it renders even when the app is broken. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · ASTRO GANN</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <style>
        html { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #0f130e; color: #dfe4dc; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .box { text-align: center; padding: 2rem; }
        img { width: 96px; height: 96px; }
        .code { font-size: 3rem; font-weight: 700; color: #e6c638; margin: 1rem 0 .25rem; }
        .msg { color: #aab3a5; margin-bottom: 1.5rem; }
        a { display: inline-block; padding: .6rem 1.2rem; border-radius: .4rem; background: #9db552; color: #0b0e0a;
            text-decoration: none; font-weight: 600; }
        a:hover { background: #b3c86c; }
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
