<?php

namespace App\Http\Resources;

use App\Presenters\RescuerRoutePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $route = $this->resource;

        return [
            'route_id' => $route->route_id,
            'route_status' => $route->route_status ?? 'active',
            'route_name' => $route->route_name ?? 'Road route',
            'distance_km' => $route->estimated_distance_km ?? null,
            'duration_min' => $route->estimated_duration_min ?? null,
            'coordinates' => app(RescuerRoutePresenter::class)->plannedCoordinates($route->route_polyline ?? null),
            'trail_coordinates' => collect($route->mobile_trail_coordinates ?? [])->map(fn (object|array $point): array => [
                'latitude' => data_get($point, 'latitude'),
                'longitude' => data_get($point, 'longitude'),
                'sequence_order' => data_get($point, 'sequence_order'),
                'recorded_at' => data_get($point, 'recorded_at'),
                'accuracy_m' => data_get($point, 'accuracy_m'),
            ])->values()->all(),
        ];
    }
}


