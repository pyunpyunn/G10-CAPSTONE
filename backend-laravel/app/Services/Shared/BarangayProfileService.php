<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class BarangayProfileService
{
    public function __construct(private BarangayBoundaryService $boundaries) {}
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

    public function isMambalingProfile(?array $profile = null): bool
    {
        $profile ??= $this->current();
        $code = trim((string) ($profile['barangay_code'] ?? ''));
        if ($code !== '') return in_array($code, ['072217049', '0730600049'], true);
        $name = $this->cleanBarangayName((string) ($profile['name'] ?? ''));
        return strcasecmp($name, 'Mambaling') === 0;
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
        $boundary = $this->boundaries->forProfile($profile);

        return [
            'barangay_id' => $profile['barangay_id'],
            'name' => $profile['name'],
            'center' => $profile['center'],
            'bounds' => $boundary['bounds'] ?? null,
            'boundary' => $boundary['geometry'] ?? null,
            'boundary_source' => $boundary['source'] ?? null,
            'zoom' => $profile['map_zoom'],
            'tile_provider' => 'OpenStreetMap Standard',
            'tile_url' => config('map_layers.street.tile_url'),
            'basemaps' => config('map_layers'),
        ];
    }

    public function mambalingMapFocus(): array
    {
        $record = Schema::hasTable('barangay_boundaries')
            ? DB::table('barangay_boundaries')->where('psgc_code', '0730600049')->first()
            : null;
        $geoJsonPath = database_path('boundaries/0730600049.geojson');
        $geoJson = is_file($geoJsonPath) ? json_decode(file_get_contents($geoJsonPath), true) : null;
        $geometry = is_array($geoJson) && ($geoJson['properties']['psgc_10d'] ?? null) === '0730600049'
            ? ($geoJson['geometry'] ?? null)
            : null;
        $source = 'Bundled Mambaling barangay boundary.';
        if (! is_array($geometry) && $record) {
            $geometry = json_decode($record->geometry_json, true);
            $source = $record->source_note;
        }

        $barangayId = $record->barangay_id ?? null;
        if ($barangayId === null && Schema::hasTable('barangays') && Schema::hasTable('cities')) {
            $barangayId = DB::table('barangays as b')
                ->join('cities as c', 'c.city_id', '=', 'b.city_id')
                ->whereRaw('LOWER(b.barangay_name) = ?', ['mambaling'])
                ->whereIn('c.city_name', ['Cebu City', 'City of Cebu'])
                ->value('b.barangay_id');
        }

        if (! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            $geometry = null;
        }

        return [
            'barangay_id' => $barangayId,
            'boundary' => $geometry,
            'boundary_source' => $source,
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




