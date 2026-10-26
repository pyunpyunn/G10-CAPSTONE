<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ValhallaRoutingService
{
    public function routeBetween(array $origin, array $destination, string $profile): ?array
    {
        if (! in_array($profile, config('navigation.profiles', []), true)) {
            throw new InvalidArgumentException('Unsupported Valhalla costing profile.');
        }

        $baseUrl = rtrim((string) config('navigation.valhalla_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Valhalla URL is not configured.');
        }

        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('navigation.timeout_seconds', 30))
            ->post($baseUrl.'/route', [
                'locations' => [
                    ['lat' => (float) $origin['latitude'], 'lon' => (float) $origin['longitude']],
                    ['lat' => (float) $destination['latitude'], 'lon' => (float) $destination['longitude']],
                ],
                'costing' => $profile,
                'units' => 'kilometers',
                'shape_format' => 'polyline6',
                'directions_options' => ['units' => 'kilometers'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Valhalla returned HTTP '.$response->status().'.');
        }

        $trip = $response->json('trip');
        if (! is_array($trip) || (int) ($trip['status'] ?? -1) !== 0) {
            return null;
        }

        $leg = $trip['legs'][0] ?? null;
        $summary = $trip['summary'] ?? null;
        if (! is_array($leg) || ! is_array($summary) || ! is_string($leg['shape'] ?? null)) {
            throw new RuntimeException('Valhalla returned an incomplete route response.');
        }

        $steps = collect($leg['maneuvers'] ?? [])
            ->filter(fn ($maneuver): bool => is_array($maneuver))
            ->map(fn (array $maneuver): array => [
                'instruction' => (string) ($maneuver['instruction'] ?? ''),
                'distance_m' => max(0, (int) round(((float) ($maneuver['length'] ?? 0)) * 1000)),
                'maneuver_type' => (int) ($maneuver['type'] ?? 0),
                'polyline_index' => max(0, (int) ($maneuver['begin_shape_index'] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'profile' => $profile,
            'polyline6' => $leg['shape'],
            'steps' => $steps,
            'distance_m' => max(0, (int) round(((float) ($summary['length'] ?? 0)) * 1000)),
            'duration_s' => max(0, (int) round((float) ($summary['time'] ?? 0))),
        ];
    }
}
