<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Http;
use Throwable;

class RoutingService
{
    public function drivingRoute(float $startLatitude, float $startLongitude, float $endLatitude, float $endLongitude): ?array
    {
        $baseUrl = rtrim((string) config('services.osrm.base_url'), '/');

        if ($baseUrl === '') {
            return null;
        }

        $coordinates = $startLongitude.','.$startLatitude.';'.$endLongitude.','.$endLatitude;

        try {
            $response = Http::withOptions([
                'verify' => (bool) config('services.osrm.ssl_verify'),
            ])->timeout(10)
                ->get($baseUrl.'/route/v1/driving/'.$coordinates, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'true',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $route = $response->json('routes.0');

            if (! is_array($route)) {
                return null;
            }

            $points = collect($route['geometry']['coordinates'] ?? [])
                ->map(fn (array $point): array => [
                    'lat' => (float) ($point[1] ?? 0),
                    'lng' => (float) ($point[0] ?? 0),
                ])
                ->filter(fn (array $point): bool => $point['lat'] !== 0.0 && $point['lng'] !== 0.0)
                ->values()
                ->all();

            if (count($points) < 2) {
                return null;
            }

            $steps = [];
            $cursor = 0;
            foreach ($route['legs'] ?? [] as $leg) {
                foreach ($leg['steps'] ?? [] as $step) {
                    $location = $step['maneuver']['location'] ?? null;
                    if (! is_array($location)) continue;
                    $best = INF;
                    for ($i = $cursor; $i < count($points); $i++) {
                        $delta = ($points[$i]['lat'] - $location[1]) ** 2 + ($points[$i]['lng'] - $location[0]) ** 2;
                        if ($delta < $best) { $best = $delta; $index = $i; }
                    }
                    $cursor = $index ?? $cursor;
                    $maneuver = $step['maneuver'];
                    $instruction = ucfirst(str_replace('_', ' ', $maneuver['type'] ?? 'continue'));
                    if (! empty($maneuver['modifier'])) $instruction .= ' '.$maneuver['modifier'];
                    if (! empty($step['name'])) $instruction .= ' onto '.$step['name'];
                    $steps[] = ['instruction' => $instruction, 'distance_m' => (int) round($step['distance'] ?? 0),
                        'maneuver_type' => 0, 'polyline_index' => $cursor];
                }
            }

            return [
                'steps' => $steps,
                'provider' => 'OSRM / OpenStreetMap',
                'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 2),
                'duration_min' => max(1, (int) round(((float) ($route['duration'] ?? 0)) / 60)),
                'coordinates' => $points,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}







