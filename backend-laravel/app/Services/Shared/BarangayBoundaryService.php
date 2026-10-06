<?php

namespace App\Services\Shared;

use App\Models\BarangayBoundary;
use App\Support\RequestSchema as Schema;

class BarangayBoundaryService
{
    public function forProfile(array $profile): ?array
    {
        $code = trim((string) ($profile['barangay_code'] ?? ''));
        $barangayId = $profile['barangay_id'] ?? null;
        $psgc = trim((string) ($profile['psgc_code'] ?? ''));
        $name = preg_replace('/^barangay\s+/i', '', trim((string) ($profile['name'] ?? '')));
        $city = trim((string) ($profile['city_name'] ?? ''));
        if (strcasecmp($city, 'City of Cebu') === 0) $city = 'Cebu City';

        $record = null;
        if (($barangayId !== null || $psgc !== '' || $code !== '' || ($name !== '' && $city !== ''))
            && Schema::hasTable('barangay_boundaries')) {
            $record = BarangayBoundary::query()
                ->where(function ($query) use ($barangayId, $code, $psgc, $name, $city): void {
                    if ($barangayId !== null) $query->orWhere('barangay_id', $barangayId);
                    if ($psgc !== '') $query->orWhere('psgc_code', $psgc);
                    if ($code !== '') {
                        $query->orWhere('barangay_code', $code)->orWhere('psgc_code', $code);
                    }
                    if ($barangayId === null && $psgc === '' && $code === '' && $name !== '' && $city !== '') {
                        $query->orWhere(fn ($match) => $match
                            ->whereRaw('LOWER(barangay_name) = ?', [strtolower($name)])
                            ->whereRaw('LOWER(city_name) = ?', [strtolower($city)]));
                    }
                })->first();
        }

        if ($record) {
            $geometry = $record->geometry_json;
            $source = $record->source_note;
        } elseif ($psgc === '0730600049' || in_array($code, ['072217049', '0730600049'], true)
            || ($psgc === '' && ! preg_match('/^\d{9,10}$/', $code) && strcasecmp($name, 'Mambaling') === 0
                && strcasecmp($city, 'Cebu City') === 0)) {
            $feature = json_decode(file_get_contents(database_path('boundaries/0730600049.geojson')), true);
            $geometry = $feature['geometry'] ?? null;
            $source = 'PSA indicative barangay boundary, June 2016; verify locally before operational use.';
        } else {
            return null;
        }

        $bounds = $this->bounds($geometry);
        if (! $bounds) return null;

        return ['geometry' => $geometry, 'bounds' => $bounds, 'source' => $source];
    }

    public function contains(array $geometry, float $latitude, float $longitude): bool
    {
        $polygons = ($geometry['type'] ?? null) === 'Polygon'
            ? [$geometry['coordinates'] ?? []]
            : (($geometry['type'] ?? null) === 'MultiPolygon' ? ($geometry['coordinates'] ?? []) : []);

        foreach ($polygons as $rings) {
            if (! isset($rings[0]) || ! $this->insideRing($rings[0], $longitude, $latitude)) continue;
            foreach (array_slice($rings, 1) as $hole) {
                if ($this->insideRing($hole, $longitude, $latitude)) continue 2;
            }
            return true;
        }
        return false;
    }

    private function bounds(?array $geometry): ?array
    {
        if (! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) return null;
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $latitudes = [];
        $longitudes = [];
        foreach ($polygons as $rings) {
            foreach ($rings as $ring) {
                foreach ($ring as $point) {
                    if (! isset($point[0], $point[1]) || ! is_numeric($point[0]) || ! is_numeric($point[1])) return null;
                    $longitudes[] = (float) $point[0];
                    $latitudes[] = (float) $point[1];
                }
            }
        }
        return count($latitudes) >= 4
            ? [[min($latitudes), min($longitudes)], [max($latitudes), max($longitudes)]]
            : null;
    }

    private function insideRing(array $ring, float $x, float $y): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y)
                && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) $inside = ! $inside;
        }
        return $inside;
    }
}
