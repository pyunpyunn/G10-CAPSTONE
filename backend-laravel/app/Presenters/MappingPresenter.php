<?php

namespace App\Presenters;

use Carbon\Carbon;

class MappingPresenter
{
    public function formatHouseholdPoint(object $row): array
    {
        $statusKey = $this->normalizeHouseholdStatusKey($row->status_key ?? null);
        $statusLabel = $row->status_label ?: 'Unchecked';
        $group = $this->statusGroup($statusKey);

        return [
            'id' => $row->location_id,
            'household_id' => $row->household_id,
            'label' => $row->household_name ?: $row->household_code ?: 'Household',
            'household_code' => $row->household_code,
            'purok' => $row->purok_name ?: 'Unassigned',
            'status_key' => $statusKey ?: 'unchecked',
            'status_label' => $statusLabel ?: 'Unchecked',
            'marker_group' => $group,
            'marker_color' => $this->statusColor($group),
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'accuracy_m' => isset($row->accuracy_m) ? (float) $row->accuracy_m : null,
            'location_label' => $row->location_label ?? null,
            'geotag_source' => $row->geotag_source ?? 'household_mobile',
            'is_verified' => (bool) ($row->is_verified ?? false),
            'last_reported_at' => $this->formatDateTime($row->last_reported_at ?? null),
            'last_battery_level' => $row->last_battery_level ?? null,
            'priority_level' => $row->priority_level ?? null,
            'captured_at' => $this->formatDateTime($row->created_at ?? $row->updated_at ?? null),
        ];
    }

    public function formatEvacuationSite(object $site, int $index): array
    {
        $capacity = $site->capacity ? (int) $site->capacity : null;
        $occupancy = isset($site->current_occupancy) ? (int) $site->current_occupancy : null;
        $vacancy = $capacity !== null && $occupancy !== null ? max($capacity - $occupancy, 0) : null;

        return [
            'id' => $site->evacuation_center_id,
            'pin_label' => chr(65 + ($index % 26)),
            'name' => $site->name ?: 'Evacuation site',
            'center_type' => $site->center_type ?? 'Evacuation center',
            'latitude' => (float) $site->latitude,
            'longitude' => (float) $site->longitude,
            'capacity' => $capacity,
            'occupancy' => $occupancy,
            'vacancy' => $vacancy,
            'status' => $site->status ?? 'active',
            'address' => $site->osm_address ?? null,
            'contact_person' => $site->contact_person ?? null,
            'contact_number' => $site->contact_number ?? null,
        ];
    }

    public function formatTeamMarker(object $row): array
    {
        return [
            'id' => $row->log_id,
            'responder_id' => $row->responder_id,
            'team_name' => $row->team_name ?: 'Unassigned responder',
            'team_code' => $row->team_code ?? null,
            'team_type' => $row->team_type ?? 'Response team',
            'responder_name' => $row->full_name ?: 'Responder',
            'assignment_id' => $row->assignment_id,
            'assigned_area' => $row->assigned_area ?: 'No active area',
            'status' => $row->status ?: $row->duty_status ?? 'available',
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'battery_level' => $row->battery_level,
            'signal_strength' => $row->signal_strength,
            'logged_at' => $this->formatDateTime($row->logged_at),
        ];
    }

    public function formatRoute(object $route, array $coordinates): array
    {
        return [
            'route_id' => $route->route_id,
            'assignment_id' => $route->assignment_id,
            'route_name' => $route->route_name ?: $route->team_name ?: 'Dispatch route',
            'team_name' => $route->team_name ?: $route->full_name ?: 'Responder team',
            'assigned_area' => $route->assigned_area ?: 'No assigned area',
            'status' => $route->route_status ?? $route->status ?? 'planned',
            'distance_km' => isset($route->estimated_distance_km) ? (float) $route->estimated_distance_km : null,
            'duration_min' => $route->estimated_duration_min ?? null,
            'coordinates' => $coordinates,
            'created_at' => $this->formatDateTime($route->created_at),
        ];
    }

    public function formatEvent(object $event): array
    {
        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type_name' => $event->type_name ?? 'Disaster event',
            'severity_key' => $event->severity_key ?? 'medium',
            'severity_label' => $event->severity_label ?? 'Unspecified',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
        ];
    }

    public function mapStatusFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All GPS-verified statuses'],
            ['key' => 'green', 'label' => 'Green - safe / evacuated / checked'],
            ['key' => 'red', 'label' => 'Red - unsafe / missing / injured'],
            ['key' => 'gray', 'label' => 'Grey - unchecked'],
        ];
    }

    public function mapRules(): array
    {
        return [
            ['color' => 'green', 'title' => 'Green marker', 'text' => 'Safe, evacuated, or checked household with GPS coordinates.'],
            ['color' => 'red', 'title' => 'Red marker', 'text' => 'Unsafe, missing, injured, or needs-help household with GPS coordinates.'],
            ['color' => 'gray', 'title' => 'Grey marker', 'text' => 'Unchecked household with GPS coordinates.'],
            ['color' => 'hidden', 'title' => 'No coordinates, no marker', 'text' => 'Households without latitude and longitude are kept out of the map layer.'],
        ];
    }

    public function statusGroup(?string $statusKey): string
    {
        $key = str_replace('-', '_', strtolower((string) $statusKey));

        if (in_array($key, ['safe', 'safe_at_home', 'evacuated', 'checked', 'active', 'returned', 'relocated', 'all_members_safe'], true)) {
            return 'green';
        }

        if (in_array($key, ['unsafe', 'missing', 'injured', 'need_help', 'needs_help', 'needs_assistance', 'not_evacuated', 'displaced', 'trapped', 'unreachable', 'deceased', 'not_safe'], true)) {
            return 'red';
        }

        return 'gray';
    }

    public function normalizeHouseholdStatusKey(?string $statusKey): ?string
    {
        if (! $statusKey) {
            return null;
        }

        $key = str_replace('-', '_', strtolower(trim((string) $statusKey)));

        return match ($key) {
            'safe_at_home', 'all_members_safe' => 'safe',
            'needs_assistance' => 'unsafe',
            'need_help', 'needs_help' => 'unsafe',
            'not_safe' => 'unsafe',
            'not_evacuated' => 'unsafe',
            default => $key,
        };
    }

    public function statusColor(string $group): string
    {
        return match ($group) {
            'green' => '#16a34a',
            'red' => '#dc2626',
            default => '#94a3b8',
        };
    }

    public function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    public function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }
}


