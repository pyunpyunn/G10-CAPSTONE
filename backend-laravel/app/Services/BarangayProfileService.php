<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarangayProfileService
{
    private const DEFAULT_CENTER = [10.3157, 123.8854];
    private const DEFAULT_BOUNDS = [
        [10.3028, 123.8720],
        [10.3293, 123.8996],
    ];

    public function current(): array
    {
        $profile = $this->activeProfile();

        if ($profile) {
            return $this->fromDatabase($profile);
        }

        return $this->fromEnvironment();
    }

    public function displayName(): string
    {
        return $this->current()['name'];
    }

    public function weatherLocation(): array
    {
        $profile = $this->current();

        return [
            'name' => $profile['weather_name'],
            'latitude' => $profile['weather']['latitude'],
            'longitude' => $profile['weather']['longitude'],
            'timezone' => 'Asia/Manila',
        ];
    }

    public function mapFocus(): array
    {
        $profile = $this->current();

        return [
            'name' => $profile['name'],
            'center' => $profile['center'],
            'bounds' => $profile['bounds'],
            'zoom' => $profile['map_zoom'],
            'tile_provider' => 'OpenStreetMap Standard',
            'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        ];
    }

    private function activeProfile(): ?object
    {
        if (! Schema::hasTable('barangay_profiles')) {
            return null;
        }

        return DB::table('barangay_profiles as bp')
            ->leftJoin('barangays as b', 'b.barangay_id', '=', 'bp.barangay_id')
            ->where('bp.is_active', 1)
            ->orderByDesc('bp.updated_at')
            ->select([
                'bp.*',
                'b.barangay_code',
                'b.barangay_name as registered_barangay_name',
            ])
            ->first();
    }

    private function fromDatabase(object $profile): array
    {
        $name = $profile->display_name
            ?: $profile->registered_barangay_name
            ?: 'Registered Barangay';

        return [
            'profile_id' => $profile->profile_id,
            'barangay_id' => $profile->barangay_id,
            'barangay_code' => $profile->barangay_code,
            'name' => $name,
            'city_name' => $profile->city_name,
            'province_name' => $profile->province_name,
            'center' => [
                'latitude' => (float) $profile->center_latitude,
                'longitude' => (float) $profile->center_longitude,
            ],
            'bounds' => $this->decodeBounds($profile->map_bounds_json),
            'map_zoom' => (int) ($profile->map_zoom ?: 16),
            'weather_name' => $profile->weather_location_name ?: $name,
            'weather' => [
                'latitude' => (float) ($profile->weather_latitude ?: $profile->center_latitude),
                'longitude' => (float) ($profile->weather_longitude ?: $profile->center_longitude),
            ],
            'source' => 'database',
        ];
    }

    private function fromEnvironment(): array
    {
        $name = env('SYSTEM_BARANGAY_NAME')
            ?: env('MAP_BARANGAY_NAME', 'Barangay Guadalupe');

        $latitude = (float) (env('SYSTEM_BARANGAY_LATITUDE')
            ?: env('MAP_BARANGAY_LATITUDE', self::DEFAULT_CENTER[0]));

        $longitude = (float) (env('SYSTEM_BARANGAY_LONGITUDE')
            ?: env('MAP_BARANGAY_LONGITUDE', self::DEFAULT_CENTER[1]));

        return [
            'profile_id' => null,
            'barangay_id' => env('SYSTEM_BARANGAY_ID'),
            'barangay_code' => null,
            'name' => $name,
            'city_name' => env('SYSTEM_CITY_NAME'),
            'province_name' => env('SYSTEM_PROVINCE_NAME'),
            'center' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'bounds' => $this->environmentBounds(),
            'map_zoom' => (int) env('SYSTEM_MAP_ZOOM', env('MAP_BARANGAY_ZOOM', 16)),
            'weather_name' => env('WEATHER_LOCATION_NAME', $name),
            'weather' => [
                'latitude' => (float) env('WEATHER_LATITUDE', $latitude),
                'longitude' => (float) env('WEATHER_LONGITUDE', $longitude),
            ],
            'source' => 'environment',
        ];
    }

    private function decodeBounds(?string $json): array
    {
        $bounds = json_decode((string) $json, true);

        return is_array($bounds) && count($bounds) === 2
            ? $bounds
            : self::DEFAULT_BOUNDS;
    }

    private function environmentBounds(): array
    {
        $json = env('SYSTEM_MAP_BOUNDS_JSON');

        return $json ? $this->decodeBounds($json) : self::DEFAULT_BOUNDS;
    }
}

