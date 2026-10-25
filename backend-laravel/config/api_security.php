<?php

$local = env('APP_ENV', 'production') === 'local';
$appOrigin = rtrim((string) env('APP_URL', ''), '/');
$origins = array_values(array_filter(array_map('trim', explode(',', env('API_ALLOWED_ORIGINS', $appOrigin)))));
return [
    'allowed_origins' => $origins,
    'allowed_hosts' => array_values(array_unique(array_filter(array_merge(
        array_map(fn ($origin) => parse_url($origin, PHP_URL_HOST), $origins),
        array_map('trim', explode(',', env('API_ALLOWED_HOSTS', $local ? 'localhost,127.0.0.1,::1' : ''))),
    )))),
    // Shared disaster-site Wi-Fi may carry all responders' GPS traffic.
    // Existing per-user and login limits remain stricter behind this perimeter.
    'requests_per_minute' => max(1, (int) env('API_EDGE_REQUESTS_PER_MINUTE', 6000)),
    'rate_limit_store' => env('API_RATE_LIMIT_STORE', 'file'),
    'max_body_bytes' => 25 * 1024 * 1024,
];
