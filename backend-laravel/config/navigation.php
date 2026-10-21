<?php

return [
    'valhalla_url' => env('VALHALLA_URL', 'http://valhalla:8002'),
    'pack_root' => env('NAVIGATION_PACK_ROOT', storage_path('app/offline-packs')),
    'pack_version' => env('NAVIGATION_PACK_VERSION', 'v1'),
    'tileserver_base_url' => env('TILESERVER_PUBLIC_URL', 'http://localhost:8080'),
    'timeout_seconds' => (int) env('VALHALLA_TIMEOUT_SECONDS', 30),
    'profiles' => ['auto', 'pedestrian'],
    'route_failure_log_channel' => env('NAVIGATION_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    'trip_stats' => [
        'max_accuracy_m' => (float) env('TRIP_MAX_ACCURACY_M', 30),
        'max_gap_s' => (int) env('TRIP_MAX_GAP_SECONDS', 120),
        'profiles' => [
            'pedestrian' => ['max_speed_ms' => (float) env('TRIP_FOOT_MAX_SPEED_MS', 3.5), 'min_step_m' => (float) env('TRIP_FOOT_MIN_STEP_M', 3)],
            'auto' => ['max_speed_ms' => (float) env('TRIP_AUTO_MAX_SPEED_MS', 45), 'min_step_m' => (float) env('TRIP_AUTO_MIN_STEP_M', 7)],
        ],
    ],
    'trip_point_retention_days' => (int) env('TRIP_POINT_RETENTION_DAYS', 365),
];