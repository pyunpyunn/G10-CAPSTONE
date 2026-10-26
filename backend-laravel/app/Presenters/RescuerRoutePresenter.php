<?php

namespace App\Presenters;

class RescuerRoutePresenter
{
    public function plannedCoordinates(?string $polyline): array
    {
        if (! $polyline) {
            return [];
        }

        $decoded = json_decode($polyline, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn (array $point): array => [
                'latitude' => (float) ($point['lat'] ?? $point['latitude'] ?? $point[0] ?? 0),
                'longitude' => (float) ($point['lng'] ?? $point['longitude'] ?? $point[1] ?? 0),
            ])
            ->filter(fn (array $point): bool => $point['latitude'] !== 0.0 && $point['longitude'] !== 0.0)
            ->values()
            ->all();
    }
}


