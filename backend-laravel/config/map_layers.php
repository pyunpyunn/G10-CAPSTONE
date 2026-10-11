<?php

return [
    'street' => [
        'label' => 'Street',
        'tile_url' => env('MAP_STREET_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'max_zoom' => 19,
    ],
    'satellite' => [
        'label' => 'Satellite',
        'tile_url' => env('MAP_SATELLITE_TILE_URL', 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'),
        'attribution' => 'Source: Esri, Vantor, Earthstar Geographics, and the GIS User Community',
        'max_zoom' => 19,
    ],
];
