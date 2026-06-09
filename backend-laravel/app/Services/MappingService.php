<?php

namespace App\Services;

use App\Services\BarangayProfileService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MappingService
{
    private BarangayProfileService $barangayProfile;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->safeResponse(function () use ($request): JsonResponse {
            $activeEvent = $this->getActiveEvent();
            $eventId = $request->query('event_id') ?: $activeEvent?->event_id;
            $hasActiveEvent = (bool) $activeEvent && (string) $eventId === (string) $activeEvent->event_id;

            return response()->json([
                'data' => [
                    'active_event' => $activeEvent ? $this->formatEvent($activeEvent) : null,
                    'barangay' => $this->barangayFocus(),
                    'summary' => $this->getSummary($eventId, $hasActiveEvent),
                    'filters' => [
                        'puroks' => $this->getPuroks(),
                        'statuses' => $this->mapStatusFilters(),
                    ],
                    'households' => $hasActiveEvent ? $this->getHouseholdGeotags($request, $eventId) : [],
                    'evacuation_sites' => $hasActiveEvent ? $this->getEvacuationSites($eventId) : [],
                    'rescue_teams' => $hasActiveEvent ? $this->getRescueTeamMarkers($eventId) : [],
                    'dispatch_routes' => $hasActiveEvent ? $this->getDispatchRoutes($eventId) : [],
                    'map_rules' => $this->mapRules(),
                ],
            ]);
        });
    }

    public function householdGeotags(Request $request): JsonResponse
    {
        return $this->safeResponse(function () use ($request): JsonResponse {
            $activeEvent = $this->getActiveEvent();
            $eventId = $request->query('event_id') ?: $activeEvent?->event_id;

            if (! $activeEvent || (string) $eventId !== (string) $activeEvent->event_id) {
                return response()->json(['data' => []]);
            }

            return response()->json([
                'data' => $this->getHouseholdGeotags($request, $eventId),
            ]);
        });
    }

    public function evacuationSites(Request $request): JsonResponse
    {
        return $this->safeResponse(function () use ($request): JsonResponse {
            $activeEvent = $this->getActiveEvent();
            $eventId = $request->query('event_id') ?: $activeEvent?->event_id;

            if (! $activeEvent || (string) $eventId !== (string) $activeEvent->event_id) {
                return response()->json(['data' => []]);
            }

            return response()->json([
                'data' => $this->getEvacuationSites($eventId),
            ]);
        });
    }

    public function dispatchRoutes(Request $request): JsonResponse
    {
        return $this->safeResponse(function () use ($request): JsonResponse {
            $activeEvent = $this->getActiveEvent();
            $eventId = $request->query('event_id') ?: $activeEvent?->event_id;

            if (! $activeEvent || (string) $eventId !== (string) $activeEvent->event_id) {
                return response()->json(['data' => []]);
            }

            return response()->json([
                'data' => [
                    'rescue_teams' => $this->getRescueTeamMarkers($eventId),
                    'dispatch_routes' => $this->getDispatchRoutes($eventId),
                ],
            ]);
        });
    }

    private function getHouseholdGeotags(Request $request, ?string $eventId): array
    {
        if (! Schema::hasTable('geotagged_locations') || ! Schema::hasTable('households')) {
            return [];
        }

        $statusColumn = $this->hasColumn('household_disasters', 'current_status_id')
            ? 'hd.current_status_id'
            : 'hd.initial_status_id';

        $query = DB::table('geotagged_locations as gl')
            ->leftJoin('households as h', 'h.household_id', '=', 'gl.household_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('household_disasters as hd', function ($join) use ($eventId): void {
                $join->on('hd.household_id', '=', 'h.household_id')
                    ->where('hd.disaster_id', '=', $eventId);
            })
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', $statusColumn)
            ->whereNotNull('gl.latitude')
            ->whereNotNull('gl.longitude');

        if ($this->hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        $purok = $request->query('purok', 'all');

        if ($purok !== 'all') {
            $query->where('p.purok_name', $purok);
        }

        $rows = $query
            ->select($this->householdSelectColumns())
            ->orderBy('p.purok_name')
            ->orderBy('h.household_name')
            ->limit(1500)
            ->get()
            ->map(fn (object $row): array => $this->formatHouseholdPoint($row))
            ->values();

        $status = $request->query('status', 'all');

        if ($status !== 'all') {
            $rows = $rows->filter(fn (array $row): bool => $row['marker_group'] === $status)->values();
        }

        return $rows->all();
    }

    private function getEvacuationSites(?string $eventId): array
    {
        if (! Schema::hasTable('evacuation_centers')) {
            return [];
        }

        $query = DB::table('evacuation_centers')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($this->hasColumn('evacuation_centers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($this->hasColumn('evacuation_centers', 'current_event_id') && $eventId) {
            $query->where(function ($where) use ($eventId): void {
                $where->where('current_event_id', $eventId)
                    ->orWhereNull('current_event_id');
            });
        }

        return $query
            ->select($this->evacuationSelectColumns())
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (object $site, int $index): array => $this->formatEvacuationSite($site, $index))
            ->values()
            ->all();
    }

    private function getRescueTeamMarkers(?string $eventId): array
    {
        if (! Schema::hasTable('responder_location_logs')) {
            return [];
        }

        $latestLogs = DB::table('responder_location_logs')
            ->select('responder_id', DB::raw('MAX(logged_at) as latest_logged_at'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('responder_id');

        $query = DB::table('responder_location_logs as rll')
            ->joinSub($latestLogs, 'latest', function ($join): void {
                $join->on('latest.responder_id', '=', 'rll.responder_id')
                    ->on('latest.latest_logged_at', '=', 'rll.logged_at');
            })
            ->leftJoin('responders as r', 'r.responder_id', '=', 'rll.responder_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id')
            ->leftJoin('responder_assignments as ra', function ($join) use ($eventId): void {
                $join->on('ra.responder_id', '=', 'rll.responder_id')
                    ->where('ra.disaster_id', '=', $eventId);
            })
            ->whereNotNull('rll.latitude')
            ->whereNotNull('rll.longitude');

        if ($eventId) {
            $query->where(function ($where): void {
                $where->whereNull('ra.status')
                    ->orWhereNotIn('ra.status', ['completed', 'cancelled', 'returned']);
            });
        }

        return $query
            ->select($this->teamSelectColumns())
            ->orderByDesc('rll.logged_at')
            ->limit(80)
            ->get()
            ->map(fn (object $row): array => $this->formatTeamMarker($row))
            ->values()
            ->all();
    }

    private function getDispatchRoutes(?string $eventId): array
    {
        if (! Schema::hasTable('responder_routes')) {
            return [];
        }

        $query = DB::table('responder_routes as rr')
            ->leftJoin('responder_assignments as ra', 'ra.assignment_id', '=', 'rr.assignment_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id');

        if ($eventId) {
            $query->where('ra.disaster_id', $eventId);
        }

        return $query
            ->select($this->routeSelectColumns())
            ->orderByDesc('rr.created_at')
            ->limit(50)
            ->get()
            ->map(fn (object $route): array => $this->formatRoute($route))
            ->filter(fn (array $route): bool => count($route['coordinates']) >= 2)
            ->values()
            ->all();
    }

    private function getSummary(?string $eventId, bool $hasActiveEvent): array
    {
        $totalHouseholds = Schema::hasTable('households')
            ? DB::table('households')->when($this->hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))->count()
            : 0;

        $gpsTagged = Schema::hasTable('geotagged_locations')
            ? DB::table('geotagged_locations')->whereNotNull('latitude')->whereNotNull('longitude')->distinct()->count('household_id')
            : 0;

        $averageAccuracy = null;

        if (Schema::hasTable('geotagged_locations') && $this->hasColumn('geotagged_locations', 'accuracy_m')) {
            $averageAccuracy = DB::table('geotagged_locations')
                ->whereNotNull('accuracy_m')
                ->avg('accuracy_m');
        }

        return [
            'gps_tagged_households' => (int) $gpsTagged,
            'no_verified_geotag' => max((int) $totalHouseholds - (int) $gpsTagged, 0),
            'average_accuracy_m' => $averageAccuracy ? round((float) $averageAccuracy, 1) : null,
            'evacuation_sites' => $hasActiveEvent ? count($this->getEvacuationSites($eventId)) : 0,
        ];
    }

    private function getPuroks(): array
    {
        if (! Schema::hasTable('puroks')) {
            return [];
        }

        return DB::table('puroks')
            ->whereNotNull('purok_name')
            ->orderBy('purok_name')
            ->pluck('purok_name')
            ->filter()
            ->values()
            ->all();
    }

    private function householdSelectColumns(): array
    {
        $columns = [
            'gl.location_id',
            'gl.household_id',
            'gl.latitude',
            'gl.longitude',
            'gl.updated_at',
            'h.household_code',
            'h.household_name',
            'p.purok_name',
            'hs.status_key',
            'hs.status_label',
        ];

        foreach (['location_label', 'accuracy_m', 'geotag_source', 'is_verified', 'created_at'] as $column) {
            if ($this->hasColumn('geotagged_locations', $column)) {
                $columns[] = "gl.$column";
            }
        }

        foreach (['last_reported_at', 'last_battery_level', 'priority_level'] as $column) {
            if ($this->hasColumn('household_disasters', $column)) {
                $columns[] = "hd.$column";
            }
        }

        return $columns;
    }

    private function evacuationSelectColumns(): array
    {
        $columns = [
            'evacuation_center_id',
            'name',
            'latitude',
            'longitude',
            'capacity',
            'osm_address',
            'current_event_id',
        ];

        foreach (['center_type', 'status', 'current_occupancy', 'contact_person', 'contact_number', 'notes', 'updated_at'] as $column) {
            if ($this->hasColumn('evacuation_centers', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function teamSelectColumns(): array
    {
        $columns = [
            'rll.log_id',
            'rll.responder_id',
            'rll.latitude',
            'rll.longitude',
            'rll.battery_level',
            'rll.signal_strength',
            'rll.logged_at',
            'r.full_name',
            'rt.team_name',
            'ra.assignment_id',
            'ra.assigned_area',
            'ra.status',
        ];

        foreach (['team_code', 'team_type', 'duty_status'] as $column) {
            if ($this->hasColumn('rescue_teams', $column)) {
                $columns[] = "rt.$column";
            }
        }

        return $columns;
    }

    private function routeSelectColumns(): array
    {
        $columns = [
            'rr.route_id',
            'rr.assignment_id',
            'rr.route_name',
            'rr.created_at',
            'ra.assigned_area',
            'ra.status',
            'r.full_name',
            'rt.team_name',
        ];

        foreach (['route_status', 'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude', 'estimated_distance_km', 'estimated_duration_min', 'route_polyline', 'updated_at'] as $column) {
            if ($this->hasColumn('responder_routes', $column)) {
                $columns[] = "rr.$column";
            }
        }

        return $columns;
    }

    private function formatHouseholdPoint(object $row): array
    {
        $statusKey = $row->status_key ?? null;
        $group = $this->statusGroup($statusKey);

        return [
            'id' => $row->location_id,
            'household_id' => $row->household_id,
            'label' => $row->household_name ?: $row->household_code ?: 'Household',
            'household_code' => $row->household_code,
            'purok' => $row->purok_name ?: 'Unassigned',
            'status_key' => $statusKey ?: 'unchecked',
            'status_label' => $row->status_label ?: 'Unchecked',
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

    private function formatEvacuationSite(object $site, int $index): array
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

    private function formatTeamMarker(object $row): array
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
            'status' => $row->status ?: $row->duty_status ?? 'standby',
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'battery_level' => $row->battery_level,
            'signal_strength' => $row->signal_strength,
            'logged_at' => $this->formatDateTime($row->logged_at),
        ];
    }

    private function formatRoute(object $route): array
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
            'coordinates' => $this->routeCoordinates($route),
            'created_at' => $this->formatDateTime($route->created_at),
        ];
    }

    private function routeCoordinates(object $route): array
    {
        $coordinates = [];

        if (isset($route->route_polyline) && $route->route_polyline) {
            $decoded = json_decode($route->route_polyline, true);

            if (is_array($decoded)) {
                $coordinates = collect($decoded)
                    ->map(fn (array $point): array => [
                        (float) ($point['lat'] ?? $point[0] ?? 0),
                        (float) ($point['lng'] ?? $point[1] ?? 0),
                    ])
                    ->filter(fn (array $point): bool => $point[0] !== 0.0 && $point[1] !== 0.0)
                    ->values()
                    ->all();
            }
        }

        if (empty($coordinates) && Schema::hasTable('route_coordinates')) {
            $coordinates = DB::table('route_coordinates')
                ->where('route_id', $route->route_id)
                ->orderBy('sequence_order')
                ->get(['latitude', 'longitude'])
                ->map(fn (object $point): array => [(float) $point->latitude, (float) $point->longitude])
                ->filter(fn (array $point): bool => $point[0] !== 0.0 && $point[1] !== 0.0)
                ->values()
                ->all();
        }

        if (empty($coordinates)
            && isset($route->start_latitude, $route->start_longitude, $route->end_latitude, $route->end_longitude)) {
            $coordinates = [
                [(float) $route->start_latitude, (float) $route->start_longitude],
                [(float) $route->end_latitude, (float) $route->end_longitude],
            ];
        }

        return $coordinates;
    }

    private function getActiveEvent(): ?object
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.ended_at');

        if ($this->hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }

        return $query
            ->orderByDesc('de.started_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'de.ended_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function formatEvent(object $event): array
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

    private function barangayFocus(): array
    {
        return $this->barangayProfile->mapFocus();
    }

    private function mapStatusFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All GPS-verified statuses'],
            ['key' => 'green', 'label' => 'Green - safe / evacuated / checked'],
            ['key' => 'red', 'label' => 'Red - unsafe / missing / injured'],
            ['key' => 'gray', 'label' => 'Grey - unchecked'],
        ];
    }

    private function mapRules(): array
    {
        return [
            ['color' => 'green', 'title' => 'Green marker', 'text' => 'Safe, evacuated, or checked household with GPS coordinates.'],
            ['color' => 'red', 'title' => 'Red marker', 'text' => 'Unsafe, missing, injured, or needs-help household with GPS coordinates.'],
            ['color' => 'gray', 'title' => 'Grey marker', 'text' => 'Unchecked household with GPS coordinates.'],
            ['color' => 'hidden', 'title' => 'No coordinates, no marker', 'text' => 'Households without latitude and longitude are kept out of the map layer.'],
        ];
    }

    private function statusGroup(?string $statusKey): string
    {
        $key = str_replace('-', '_', strtolower((string) $statusKey));

        if (in_array($key, ['safe', 'evacuated', 'checked', 'active', 'returned', 'relocated'], true)) {
            return 'green';
        }

        if (in_array($key, ['unsafe', 'missing', 'injured', 'need_help', 'needs_help', 'not_evacuated', 'displaced'], true)) {
            return 'red';
        }

        return 'gray';
    }

    private function statusColor(string $group): string
    {
        return match ($group) {
            'green' => '#16a34a',
            'red' => '#dc2626',
            default => '#94a3b8',
        };
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    private function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }

    private function safeResponse(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (QueryException) {
            return response()->json([
                'message' => 'Map data cannot be loaded because the database is not available right now.',
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Map data cannot be loaded right now. Please check the backend logs.',
            ], 500);
        }
    }
}
