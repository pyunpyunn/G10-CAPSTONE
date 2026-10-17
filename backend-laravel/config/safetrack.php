<?php

return [
    'base_url' => env('SAFETRACK_API_URL', ''),
    'api_key' => env('SAFETRACK_API_KEY'),
    'api_prefix' => env('SAFETRACK_API_PREFIX', 'api/external'),
    'timeout' => (int) env('SAFETRACK_API_TIMEOUT', 20),
    'max_pages' => (int) env('SAFETRACK_REPORT_MAX_PAGES', 20),
];