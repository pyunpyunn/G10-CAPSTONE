<?php
return [
    'feed_url' => 'https://publicalert.pagasa.dost.gov.ph/feeds/',
    'cache_store' => env('WEATHER_ADVISORY_CACHE_STORE', 'file'),
    'refresh_seconds' => 600,
];
