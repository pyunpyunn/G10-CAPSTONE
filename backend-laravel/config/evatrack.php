<?php

return [
    'reports_url' => env('EVATRACK_REPORTS_URL', 'http://127.0.0.1:9000'),
    'reports_api_key' => env('EVATRACK_REPORTS_API_KEY'),
    'timeout' => (int) env('EVATRACK_REPORTS_TIMEOUT', 60),
];
