<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarangayProfileService
{
    private const DEFAULT_BARANGAY = 'Mambaling';
    private const DEFAULT_CENTER = [10.2922, 123.8763];
    private const DEFAULT_BOUNDS = [
        [10.2820, 123.8700],
        [10.2972, 123.8842],
    ];

    public function current(): array
    {
        $profile = $this->activeProfile();

        if ($profile) {
            return $this->fromDatabase($profile);
        }

        $safeTrackBarangay = $this->safeTrackBarangay();

        if ($safeTrackBarangay) {
            return $this->fromSafeTrack($safeTrackBarangay);
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

    private function safeTrackBarangay(): ?object
    {
        if (! Schema::hasTable('barangays')) {
            return null;
        }

        $barangayName = $this->cleanBarangayName(
            env('SAFETRACK_BARANGAY_NAME')
                ?: env('SYSTEM_BARANGAY_NAME')
                ?: env('MAP_BARANGAY_NAME')
                ?: self::DEFAULT_BARANGAY
        );

        $query = DB::table('barangays as b')
            ->leftJoin('cities as c', 'c.city_id', '=', 'b.city_id')
            ->leftJoin('provinces as p', 'p.province_id', '=', 'c.province_id')
            ->whereRaw('LOWER(b.barangay_name) = ?', [strtolower($barangayName)])
            ->select([
                'b.barangay_id',
                'b.barangay_code',
                'b.barangay_name',
                'c.city_name',
                'p.province_name',
            ]);

        return $query->first();
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
            'office_address' => $profile->office_address,
            'contact_number' => $profile->contact_number,
            'email' => $profile->email,
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

    private function fromSafeTrack(object $barangay): array
    {
        $name = 'Barangay '.$barangay->barangay_name;
        $center = $this->knownCenter($barangay->barangay_name);

        return [
            'profile_id' => null,
            'barangay_id' => $barangay->barangay_id,
            'barangay_code' => $barangay->barangay_code,
            'name' => $name,
            'city_name' => $barangay->city_name,
            'province_name' => $barangay->province_name,
            'office_address' => null,
            'contact_number' => null,
            'email' => null,
            'center' => [
                'latitude' => $center[0],
                'longitude' => $center[1],
            ],
            'bounds' => $this->knownBounds($barangay->barangay_name),
            'map_zoom' => (int) env('SYSTEM_MAP_ZOOM', env('MAP_BARANGAY_ZOOM', 17)),
            'weather_name' => env('WEATHER_LOCATION_NAME', $name),
            'weather' => [
                'latitude' => (float) env('WEATHER_LATITUDE', $center[0]),
                'longitude' => (float) env('WEATHER_LONGITUDE', $center[1]),
            ],
            'source' => 'SafeTrack/shared barangays table',
        ];
    }

    private function fromEnvironment(): array
    {
        $name = env('SYSTEM_BARANGAY_NAME')
            ?: env('MAP_BARANGAY_NAME', 'Barangay '.self::DEFAULT_BARANGAY);

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
            'office_address' => env('SYSTEM_OFFICE_ADDRESS'),
            'contact_number' => env('SYSTEM_CONTACT_NUMBER'),
            'email' => env('SYSTEM_EMAIL'),
            'center' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'bounds' => $this->environmentBounds(),
            'map_zoom' => (int) env('SYSTEM_MAP_ZOOM', env('MAP_BARANGAY_ZOOM', 17)),
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

    private function cleanBarangayName(string $name): string
    {
        return trim(preg_replace('/^barangay\s+/i', '', $name));
    }

    private function knownCenter(?string $barangayName): array
    {
        return [
            (float) env('SYSTEM_BARANGAY_LATITUDE', env('MAP_BARANGAY_LATITUDE', self::DEFAULT_CENTER[0])),
            (float) env('SYSTEM_BARANGAY_LONGITUDE', env('MAP_BARANGAY_LONGITUDE', self::DEFAULT_CENTER[1])),
        ];
    }

    private function knownBounds(?string $barangayName): array
    {
        $json = env('SYSTEM_MAP_BOUNDS_JSON');

        if ($json) {
            return $this->decodeBounds($json);
        }

        return self::DEFAULT_BOUNDS;
    }

    private function environmentBounds(): array
    {
        $json = env('SYSTEM_MAP_BOUNDS_JSON');

        return $json ? $this->decodeBounds($json) : self::DEFAULT_BOUNDS;
    }
}
