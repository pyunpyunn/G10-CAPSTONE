<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class BarangayProfileService
{
    private ?array $sharedGeometry = null;
    private bool $geometryLoaded = false;

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

        $configuredName =
            env('SAFETRACK_BARANGAY_NAME')
                ?: env('SYSTEM_BARANGAY_NAME')
                ?: env('MAP_BARANGAY_NAME');
        $barangayName = $configuredName ? $this->cleanBarangayName($configuredName) : null;

        if (! $barangayName && Schema::hasTable('households') && Schema::hasTable('addresses')) {
            $barangayName = DB::table('households as h')
                ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
                ->join('barangays as b', 'b.barangay_id', '=', 'a.barangay_id')
                ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
                ->select('b.barangay_name')->selectRaw('COUNT(*) as household_count')
                ->groupBy('b.barangay_id', 'b.barangay_name')
                ->orderByDesc('household_count')->value('b.barangay_name');
        }
        if (! $barangayName) return null;

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
        $name = env('SYSTEM_BARANGAY_NAME') ?: env('MAP_BARANGAY_NAME') ?: 'Shared database area';
        [$latitude, $longitude] = $this->knownCenter(null);

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

    private function decodeBounds(?string $json): ?array
    {
        $bounds = json_decode((string) $json, true);

        return is_array($bounds) && count($bounds) === 2
            ? $bounds
            : ($this->sharedGeometry()['bounds'] ?? null);
    }

    private function cleanBarangayName(string $name): string
    {
        return trim(preg_replace('/^barangay\s+/i', '', $name));
    }

    private function knownCenter(?string $barangayName): array
    {
        $latitude = env('SYSTEM_BARANGAY_LATITUDE', env('MAP_BARANGAY_LATITUDE'));
        $longitude = env('SYSTEM_BARANGAY_LONGITUDE', env('MAP_BARANGAY_LONGITUDE'));
        return is_numeric($latitude) && is_numeric($longitude)
            ? [(float) $latitude, (float) $longitude]
            : ($this->sharedGeometry()['center'] ?? [null, null]);
    }

    private function knownBounds(?string $barangayName): ?array
    {
        $json = env('SYSTEM_MAP_BOUNDS_JSON');

        if ($json) {
            return $this->decodeBounds($json);
        }

        return $this->sharedGeometry()['bounds'] ?? null;
    }

    private function environmentBounds(): ?array
    {
        $json = env('SYSTEM_MAP_BOUNDS_JSON');

        return $json ? $this->decodeBounds($json) : ($this->sharedGeometry()['bounds'] ?? null);
    }

    private function sharedGeometry(): ?array
    {
        if ($this->geometryLoaded) return $this->sharedGeometry;
        $this->geometryLoaded = true;
        if (! Schema::hasTable('evacuation_centers')) return null;

        $points = DB::table('evacuation_centers')
            ->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])
            ->limit(500)->get(['latitude', 'longitude']);
        if ($points->isEmpty()) return null;

        $latitudes = $points->pluck('latitude')->map(fn ($value) => (float) $value)->sort()->values();
        $longitudes = $points->pluck('longitude')->map(fn ($value) => (float) $value)->sort()->values();
        $middle = intdiv($points->count(), 2);
        $center = [$latitudes[$middle], $longitudes[$middle]];
        $nearby = $points->filter(fn ($point) => abs((float) $point->latitude - $center[0]) <= 0.1
            && abs((float) $point->longitude - $center[1]) <= 0.1);
        $this->sharedGeometry = [
            'center' => $center,
            'bounds' => [
                [$nearby->min('latitude') - 0.002, $nearby->min('longitude') - 0.002],
                [$nearby->max('latitude') + 0.002, $nearby->max('longitude') + 0.002],
            ],
        ];
        return $this->sharedGeometry;
    }
}







