<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Throwable;

class RoutingService
{
    private const NEAREST_ROAD_CANDIDATE_LIMIT = 5;
    private const MAX_ROAD_SNAP_DISTANCE_METERS = 500;

    public function drivingRoute(float $startLatitude, float $startLongitude, float $endLatitude, float $endLongitude): ?array
    {
        $baseUrl = rtrim((string) config('services.osrm.base_url'), '/');

        if ($baseUrl === '') {
            return null;
        }

        try {
            $response = $this->requestDrivingRoute($baseUrl, $startLatitude, $startLongitude, $endLatitude, $endLongitude);

            $snapRequired = in_array($response->json('code'), ['NoRoute', 'NoSegment'], true);
            if (! $response->successful() && ! $snapRequired) {
                return null;
            }

            $route = $response->json('routes.0');
            if (! is_array($route) && $snapRequired) {
                $nearestRoad = $this->nearestRoad($baseUrl, $startLatitude, $startLongitude);
                if ($nearestRoad) {
                    $response = $this->requestDrivingRoute(
                        $baseUrl,
                        $nearestRoad['latitude'],
                        $nearestRoad['longitude'],
                        $endLatitude,
                        $endLongitude,
                    );
                    if (! $response->successful()) {
                        return null;
                    }
                    $route = $response->json('routes.0');
                }
            }

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

    private function requestDrivingRoute(
        string $baseUrl,
        float $startLatitude,
        float $startLongitude,
        float $endLatitude,
        float $endLongitude,
    ): Response {
        $coordinates = $startLongitude.','.$startLatitude.';'.$endLongitude.','.$endLatitude;

        return Http::withOptions([
            'verify' => (bool) config('services.osrm.ssl_verify'),
        ])->timeout(10)
            ->get($baseUrl.'/route/v1/driving/'.$coordinates, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
            ]);
    }

    /**
     * Ask the routing provider's spatial index for nearby road segments, then
     * sort only those bounded candidates by their distance from the GPS fix.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function nearestRoad(string $baseUrl, float $latitude, float $longitude): ?array
    {
        $response = Http::withOptions([
            'verify' => (bool) config('services.osrm.ssl_verify'),
        ])->timeout(5)
            ->get($baseUrl.'/nearest/v1/driving/'.$longitude.','.$latitude, [
                'number' => self::NEAREST_ROAD_CANDIDATE_LIMIT,
                'radiuses' => self::MAX_ROAD_SNAP_DISTANCE_METERS,
            ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            return null;
        }

        $candidates = array_filter($response->json('waypoints', []), function (mixed $candidate): bool {
            if (! is_array($candidate)) {
                return false;
            }
            $location = $candidate['location'] ?? null;
            return is_array($location)
                && count($location) >= 2
                && is_numeric($location[0])
                && is_numeric($location[1])
                && is_numeric($candidate['distance'] ?? null)
                && (float) $candidate['distance'] <= self::MAX_ROAD_SNAP_DISTANCE_METERS;
        });
        usort($candidates, fn (array $left, array $right): int => (float) $left['distance'] <=> (float) $right['distance']);

        if (! $candidates) {
            return null;
        }

        return [
            'latitude' => (float) $candidates[0]['location'][1],
            'longitude' => (float) $candidates[0]['location'][0],
        ];
    }
}





