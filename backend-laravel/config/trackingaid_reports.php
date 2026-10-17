<?php

return [
    'reports_url' => env('TRACKINGAID_REPORTS_URL', ''),
    'reports_api_key' => env('TRACKINGAID_REPORTS_API_KEY'),
    'timeout' => (int) env('TRACKINGAID_REPORTS_TIMEOUT', 60),
];