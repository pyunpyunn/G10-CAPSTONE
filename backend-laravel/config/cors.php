<?php

$security = require __DIR__.'/api_security.php';
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $security['allowed_origins'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Device-UUID', 'Idempotency-Key', 'X-Requested-With', 'ngrok-skip-browser-warning'],
    'exposed_headers' => ['X-Request-ID', 'Retry-After', 'Idempotency-Replayed'],
    'max_age' => 600,
    'supports_credentials' => false,
];
