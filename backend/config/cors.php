<?php

/*
 * CORS for the API (DECISIONS D-80).
 * - Native Android/iOS apps do not need CORS at all.
 * - Local development: the Flutter web build runs on http://localhost:<port> (e.g. `flutter run -d chrome
 *   --web-port 5555`), so any localhost / 127.0.0.1 port is allowed when APP_ENV=local.
 * - Production: nothing is allowed unless listed in CORS_ALLOWED_ORIGINS (comma-separated), e.g. if you ever host
 *   the Flutter web build on another domain.
 * Auth is a bearer token (no cookies), so credentials are not needed.
 */

$local = env('APP_ENV', 'production') === 'local';

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => $local ? ['#^http://(localhost|127\.0\.0\.1)(:\d+)?$#'] : [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
