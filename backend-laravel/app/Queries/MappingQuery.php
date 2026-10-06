<?php

namespace App\Queries;

use App\Models\DisasterEvent;
use App\Models\EvacuationCenter;
use App\Models\GeotaggedLocation;
use App\Models\Household;
use App\Models\ResponderLocationLog;
use App\Models\ResponderRoute;
use App\Presenters\MappingPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
class MappingQuery
{
    public function __construct(private MappingPresenter $presenter) {}

    public function getHouseholdGeotags(Request $request, ?string $eventId, int|string|null $barangayId = null): array
    {
        if (! Schema::hasTable('geotagged_locations') || ! Schema::hasTable('households')) {
            return [];
        }

        $statusColumn = $this->hasColumn('household_disasters', 'current_status_id')
            ? 'hd.current_status_id'
            : 'hd.initial_status_id';

        $query = GeotaggedLocation::query()
            ->from('geotagged_locations as gl')
                ->join('households as h', 'h.household_id', '=', 'gl.household_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('household_disasters as hd', function ($join) use ($eventId): void {
                $join->on('hd.household_id', '=', 'h.household_id')
                    ->where('hd.disaster_id', '=', $eventId);
            })
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', $statusColumn)
            ->whereNotNull('gl.latitude')
            ->whereNotNull('gl.longitude')
            ->when($barangayId !== null, fn ($builder) => $builder->where('a.barangay_id', $barangayId));

        if ($this->hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        $purokExpression = $this->hasColumn('addresses', 'purok_sitio')
            ? "COALESCE(NULLIF(a.purok_sitio, ''), p.purok_name)"
            : 'p.purok_name';
        $purok = $request->query('purok', 'all');

        if ($purok !== 'all') {
            $query->whereRaw($purokExpression.' = ?', [$purok]);
        }

        $geotags = $query
            ->select($this->householdSelectColumns())
            ->selectRaw($purokExpression.' as purok_name')
            ->orderBy('purok_name')
            ->orderBy('h.household_name')
            ->limit(1500)
            ->get();
        $fallbackIds = $geotags->filter(fn (object $row): bool => ! $row->status_key || in_array($row->status_key, ['unknown', 'unchecked'], true))
            ->pluck('household_id')->map(fn ($id): string => (string) $id)->unique()->values()->all();
        $fallbacks = $this->fallbackHouseholdStatusesFromMembers($fallbackIds, $eventId);
        $rows = $geotags
            ->map(function (object $row) use ($fallbacks): array {
                $point = $this->presenter->formatHouseholdPoint($row);

                if (! $row->status_key || $row->status_key === 'unknown' || $row->status_key === 'unchecked') {
                    $fallback = $fallbacks[(string) $row->household_id] ?? null;

                    if ($fallback) {
                        $point['status_key'] = $fallback['status_key'];
                        $point['status_label'] = $fallback['status_label'];
                        $point['marker_group'] = $this->presenter->statusGroup($fallback['status_key']);
                    }
                }

                return $point;
            })
            ->values();

        $status = $request->query('status', 'all');

        if ($status !== 'all') {
            $rows = $rows->filter(fn (array $row): bool => $row['marker_group'] === $status)->values();
        }

        return $rows->all();
    }

    public function getEvacuationSites(?string $eventId): array
    {
        if (! Schema::hasTable('evacuation_centers')) {
            return [];
        }

        $query = EvacuationCenter::query()
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
            ->map(fn (object $site, int $index): array => $this->presenter->formatEvacuationSite($site, $index))
            ->values()
            ->all();
    }

    public function getRescueTeamMarkers(?string $eventId): array
    {
        if (! Schema::hasTable('responder_location_logs')) {
            return [];
        }

        $latestLogs = ResponderLocationLog::query()
            ->select('responder_id', DB::raw('MAX(logged_at) as latest_logged_at'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('responder_id');

        $query = ResponderLocationLog::query()
            ->from('responder_location_logs as rll')
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
            ->map(fn (object $row): array => $this->presenter->formatTeamMarker($row))
            ->values()
            ->all();
    }

    public function getDispatchRoutes(?string $eventId): array
    {
        if (! Schema::hasTable('responder_routes')) {
            return [];
        }

        $query = ResponderRoute::query()
            ->from('responder_routes as rr')
            ->leftJoin('responder_assignments as ra', 'ra.assignment_id', '=', 'rr.assignment_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id');

        if ($eventId) {
            $query->where('ra.disaster_id', $eventId);
        }

        $routes = $query
            ->select($this->routeSelectColumns())
            ->orderByDesc('rr.created_at')
            ->limit(50)
            ->get()
            ->map(fn (object $route): array => $this->presenter->formatRoute($route, $this->routeCoordinates($route)))
            ->filter(fn (array $route): bool => $this->isActiveDispatchRoute($route['status']) && count($route['coordinates']) >= 2)
            ->values()
            ->all();

        return $routes;
    }

    public function isActiveDispatchRoute(?string $status): bool
    {
        $value = strtolower((string) ($status ?? ''));

        if ($value === '') {
            return true;
        }

        $inactive = ['completed', 'cancelled', 'returned', 'ended', 'closed', 'failed'];

        foreach ($inactive as $flag) {
            if ($value === $flag || str_contains($value, $flag)) {
                return false;
            }
        }

        return true;
    }

    public function getSummary(?string $eventId, bool $hasActiveEvent, int|string|null $barangayId = null, ?int $siteCount = null): array
    {
        $hasAddresses = Schema::hasTable('addresses');
        $totalHouseholds = Schema::hasTable('households')
            ? DB::table('households as h')
                ->when($hasAddresses && $barangayId !== null, fn ($query) => $query->join('addresses as a', 'a.address_id', '=', 'h.address_id')->where('a.barangay_id', $barangayId))
                ->when($this->hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))->count()
            : 0;

            $gpsTagged = Schema::hasTable('geotagged_locations') && Schema::hasTable('households')
                ? DB::table('geotagged_locations as gl')
                    ->join('households as h', 'h.household_id', '=', 'gl.household_id')
                    ->when($hasAddresses && $barangayId !== null, fn ($query) => $query->join('addresses as a', 'a.address_id', '=', 'h.address_id')->where('a.barangay_id', $barangayId))
                    ->when($this->hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
                    ->whereNotNull('gl.latitude')
                    ->whereNotNull('gl.longitude')
                    ->distinct()
                    ->count('gl.household_id')
                : 0;

        $averageAccuracy = null;

        if (Schema::hasTable('geotagged_locations') && $this->hasColumn('geotagged_locations', 'accuracy_m')) {
            $averageAccuracy = GeotaggedLocation::query()
                ->when($hasAddresses && $barangayId !== null, fn ($query) => $query
                    ->join('households as h', 'h.household_id', '=', 'geotagged_locations.household_id')
                    ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
                    ->where('a.barangay_id', $barangayId))
                ->whereNotNull('accuracy_m')
                ->avg('accuracy_m');
        }

        return [
            'gps_tagged_households' => (int) $gpsTagged,
            'no_verified_geotag' => max((int) $totalHouseholds - (int) $gpsTagged, 0),
            'average_accuracy_m' => $averageAccuracy ? round((float) $averageAccuracy, 1) : null,
            'evacuation_sites' => $siteCount ?? $this->countActiveEvacuationSites($eventId, $hasActiveEvent),
        ];
    }

    public function countActiveEvacuationSites(?string $eventId, bool $hasActiveEvent): int
    {
        if (! $hasActiveEvent
            || ! Schema::hasTable('evacuation_centers')
            || ! $this->hasColumn('evacuation_centers', 'status')) {
            return 0;
        }

        $query = EvacuationCenter::query()->where('status', 'active');

        if ($this->hasColumn('evacuation_centers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($this->hasColumn('evacuation_centers', 'current_event_id')) {
            $query->where(function ($where) use ($eventId): void {
                $where->where('current_event_id', $eventId)
                    ->orWhereNull('current_event_id');
            });
        }

        return $query->count();
    }

    public function getPuroks(int|string|null $barangayId = null): array
    {
        if ($barangayId !== null && Schema::hasTable('puroks') && Schema::hasTable('sitios')) {
            $names = DB::table('puroks as p')->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
                ->where('s.barangay_id', $barangayId)->whereNotNull('p.purok_name')
                ->distinct()->orderBy('p.purok_name')->pluck('p.purok_name')->all();
            if ($names !== []) return $names;
        }
        return app(HouseholdPurokQuery::class)->names($barangayId);
    }

    public function householdSelectColumns(): array
    {
        $columns = [
            'gl.location_id',
            'gl.household_id',
            'gl.latitude',
            'gl.longitude',
            'gl.updated_at',
            'h.household_code',
            'h.household_name',
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

    public function evacuationSelectColumns(): array
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

    public function teamSelectColumns(): array
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

    public function routeSelectColumns(): array
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

    public function routeCoordinates(object $route): array
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

    public function getActiveEvent(): ?object
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DisasterEvent::query()
            ->from('disaster_events as de')
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

    public function fallbackHouseholdStatusFromMembers(string $householdId, ?string $eventId): ?array
    {
        return $this->fallbackHouseholdStatusesFromMembers([$householdId], $eventId)[$householdId] ?? null;
    }

    private function fallbackHouseholdStatusesFromMembers(array $householdIds, ?string $eventId): array
    {
        if (! $eventId || $householdIds === [] || ! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) return [];
        $statuses = DB::table('member_disaster_statuses as mds')
            ->join('household_members as hm', 'hm.member_id', '=', 'mds.member_id')
            ->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
            ->where('mds.disaster_id', $eventId)
            ->whereIn('mds.household_id', $householdIds)
            ->whereNull('hm.deleted_at')
            ->get(['mds.household_id', 'ms.status_key'])
            ->groupBy('household_id');

        $fallbacks = [];
        foreach ($statuses as $householdId => $householdStatuses) {
            $fallbacks[(string) $householdId] = $this->classifyMemberStatuses($householdStatuses->pluck('status_key'));
        }
        return $fallbacks;
    }

    private function classifyMemberStatuses($statuses): array
    {
        $list = $statuses->map(fn ($value) => str_replace('-', '_', strtolower((string) $value)))->all();
        $unsafeKeys = ['unsafe', 'needs_help', 'needs_assistance', 'injured', 'trapped', 'missing', 'unreachable', 'deceased', 'not_safe'];
        $safeKeys = ['safe', 'safe_at_home', 'active', 'returned', 'evacuated', 'relocated'];

        if (collect($list)->contains(fn ($value) => in_array($value, $unsafeKeys, true))) {
            return ['status_key' => 'unsafe', 'status_label' => 'Unsafe'];
        }

        if (collect($list)->every(fn ($value) => in_array($value, $safeKeys, true))) {
            return ['status_key' => 'safe', 'status_label' => 'Safe'];
        }

        if (collect($list)->contains(fn ($value) => in_array($value, ['evacuated', 'relocated'], true))) {
            return ['status_key' => 'evacuated', 'status_label' => 'Evacuated'];
        }

        return ['status_key' => 'unchecked', 'status_label' => 'Unchecked'];
    }

    public function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }
}


