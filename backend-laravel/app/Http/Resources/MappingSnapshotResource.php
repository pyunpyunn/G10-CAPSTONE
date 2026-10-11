<?php

namespace App\Http\Resources;

use App\Presenters\MappingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MappingSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $presenter = app(MappingPresenter::class);
        $data = [
            'active_event' => $snapshot->event ? $presenter->formatEvent($snapshot->event) : null,
            'barangay' => $snapshot->barangay,
            'summary' => $snapshot->summary,
            'filters' => ['puroks' => $snapshot->puroks, 'statuses' => $presenter->mapStatusFilters()],
        ];
        if ($snapshot->full) {
            $data['households'] = $snapshot->households;
            $data['evacuation_sites'] = $snapshot->evacuationSites;
            $data['rescue_teams'] = $snapshot->rescueTeams;
            $data['dispatch_routes'] = $snapshot->dispatchRoutes;
            $office = config('rescue_office');
            $latitude = $office['latitude'] ?? null;
            $longitude = $office['longitude'] ?? null;
            $data['rescue_offices'] = is_numeric($latitude) && is_numeric($longitude)
                && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180
                ? [['id' => 'rescue-office', 'name' => $office['name'], 'address' => $office['address'],
                    'latitude' => (float) $latitude, 'longitude' => (float) $longitude]] : [];
        }
        $data['map_rules'] = $presenter->mapRules();
        return $data;
    }
}
