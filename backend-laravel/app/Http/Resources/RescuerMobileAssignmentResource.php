<?php

namespace App\Http\Resources;

use App\Presenters\RescuerAssignmentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $presenter = app(RescuerAssignmentPresenter::class);
        $routeNotes = $this->decode($row->route_notes ?? null);
        $status = $presenter->statusKey($row->status ?? 'dispatched');

        return [
            'assignment_id' => $row->assignment_id,
            'assignment_code' => $row->assignment_code,
            'event_id' => $row->disaster_id,
            'event_name' => $row->event_name ?? 'Active event',
            'household_id' => $row->household_id,
            'assigned_area' => $row->assigned_area,
            'destination_label' => $row->household_location_label ?? null,
            'priority_level' => $row->priority_level ?: 'medium',
            'status_key' => $status,
            'status_label' => $presenter->statusLabel($status),
            'team_name' => $row->team_name ?: 'Assigned team',
            'team_code' => $row->team_code,
            'dispatch_notes' => $row->dispatch_notes,
            'route_notes' => $routeNotes,
            'outcomes' => $this->decode($row->outcome_notes ?? null),
            'households_to_cover' => (int) ($routeNotes['households_to_cover'] ?? 0),
            'latitude' => $row->household_latitude ?? null,
            'longitude' => $row->household_longitude ?? null,
            'assigned_at' => $row->assigned_at,
            'accepted_at' => $row->accepted_at,
            'en_route_at' => $row->en_route_at,
            'arrived_at' => $row->arrived_at,
            'completed_at' => $row->completed_at,
            'route' => $row->mobile_route
                ? (new RescuerMobileRouteResource($row->mobile_route))->resolve($request)
                : null,
        ];
    }

    private function decode(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}


