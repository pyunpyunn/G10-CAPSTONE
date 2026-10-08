<?php

return [
    'disk' => env('REPORT_DISK', 'local'),
    'external_api_key' => env('EXTERNAL_SYSTEM_API_KEY'),
    'expires_minutes' => (int) env('REPORT_URL_EXPIRES_MINUTES', 30),
    'pdf_max_rows' => (int) env('REPORT_PDF_MAX_ROWS', 5000),
    'queue_threshold' => (int) env('REPORT_QUEUE_THRESHOLD', 1000),
    'queue_connection' => env('REPORT_QUEUE_CONNECTION', 'reports'),
];
