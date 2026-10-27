<?php

namespace App\Queries;

use App\Presenters\RescuerAssignmentPresenter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerAssignmentQuery
{
    private const MOBILE_TRAIL_POINT_LIMIT = 300;

    public function __construct(private RescuerAssignmentPresenter $presenter) {}

    public function list(?int $responderId, int $limit): Collection
    {
        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
            return collect();
        }

        $activeStatuses = ['accepted', 'dispatched', 'en_route', 'en-route', 'on_scene', 'on-scene'];
        $active = $this->forResponder($responderId)->whereIn('ra.status', $activeStatuses)
            ->orderBy('ra.assigned_at')->orderBy('ra.assignment_id')->get();
        $history = $this->forResponder($responderId)->whereNotIn('ra.status', $activeStatuses)
            ->orderByDesc('ra.assigned_at')->limit($limit)->get();

        $assignments = $active->concat($history)
            ->map(function (object $row) use ($responderId): object {
                $row->status = \App\Support\RescuerHouseholdQueue::status($row, $responderId);
                $row->dispatch_household_id = $row->household_id;
                $row->household_id = \App\Support\RescuerHouseholdQueue::target($row, $responderId);
                return $row;
            });
        $this->attachRoutes($assignments, $responderId);

        return $assignments;
    }

    private function attachRoutes(Collection $assignments, int $responderId): void
    {
        $routes = collect();
        if ($assignments->isNotEmpty() && Schema::hasTable('responder_routes')) {
            $ranked = DB::table('responder_routes')
                ->whereIn('assignment_id', $assignments->pluck('assignment_id')->unique())
                ->when(Schema::hasColumn('responder_routes', 'responder_id'), fn ($query) => $query->where('responder_id', $responderId))
                ->select('*')->selectRaw('ROW_NUMBER() OVER (PARTITION BY assignment_id ORDER BY created_at DESC, route_id DESC) AS mobile_rank');
            $routes = DB::query()->fromSub($ranked, 'ranked_routes')->where('mobile_rank', 1)->get()->keyBy('assignment_id');
        }
        $hasHousehold = $routes->isNotEmpty() && Schema::hasColumn('responder_routes', 'household_id');
        foreach ($assignments as $assignment) {
            $route = $routes->get($assignment->assignment_id);
            if ($route && $hasHousehold && (!$assignment->household_id
                || ($route->household_id ?? null) !== $assignment->household_id || ($route->route_status ?? null) === 'completed')) {
                $routes->forget($assignment->assignment_id);
            }
        }
        $trails = collect();
        if ($routes->isNotEmpty() && Schema::hasTable('route_coordinates')) {
            $ranked = DB::table('route_coordinates')->whereIn('route_id', $routes->pluck('route_id'))
                ->select(['route_id', 'latitude', 'longitude', 'sequence_order', 'recorded_at', 'accuracy_m'])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY route_id ORDER BY sequence_order DESC) AS mobile_rank');
            $trails = DB::query()->fromSub($ranked, 'ranked_points')
                ->where('mobile_rank', '<=', self::MOBILE_TRAIL_POINT_LIMIT)->orderBy('sequence_order')->get()->groupBy('route_id');
        }
        foreach ($assignments as $assignment) {
            $route = $routes->get($assignment->assignment_id);
            if ($route) {
                unset($route->mobile_rank);
                $route->mobile_trail_coordinates = $trails->get($route->route_id, collect())->map(function ($point) {
                    unset($point->mobile_rank, $point->route_id);
                    return $point;
                })->values();
            }
            $assignment->mobile_route = $route;
        }
    }

    public function find(int $assignmentId, ?int $responderId): ?object
    {
        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
            return null;
        }

        $assignment = $this->forResponder($responderId)->where('ra.assignment_id', $assignmentId)->first();
        if ($assignment) $assignment->status = \App\Support\RescuerHouseholdQueue::status($assignment, $responderId);
        if ($assignment) $assignment->dispatch_household_id = $assignment->household_id;
        if ($assignment) $assignment->household_id = \App\Support\RescuerHouseholdQueue::target($assignment, $responderId);
        return $assignment;
    }

    public function active(?int $responderId): ?object
    {
        return $this->list($responderId, 10)->first(fn (object $assignment): bool => in_array(
            $this->presenter->statusKey($assignment->status ?? null),
            ['accepted', 'dispatched', 'en_route', 'on_scene'],
            true,
        ));
    }

    public function routeForAssignment(int $assignmentId, ?int $responderId = null): ?object
    {
        if (! Schema::hasTable('responder_routes')) {
            return null;
        }

        $route = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->when($responderId && Schema::hasColumn('responder_routes', 'responder_id'), fn ($query) => $query->where('responder_id', $responderId))
            ->orderByDesc('created_at')
            ->orderByDesc('route_id')
            ->first();

        if (! $route) {
            return null;
        }

        if ($responderId && Schema::hasColumn('responder_routes', 'household_id')) {
            $assignment = DB::table('responder_assignments')->where('assignment_id', $assignmentId)->first();
            $target = $assignment ? \App\Support\RescuerHouseholdQueue::target($assignment, $responderId) : null;
            if (!$target || ($route->household_id ?? null) !== $target || ($route->route_status ?? null) === 'completed') return null;
        }

        $route->mobile_trail_coordinates = Schema::hasTable('route_coordinates')
            ? DB::table('route_coordinates')
                ->where('route_id', $route->route_id)
                ->orderByDesc('sequence_order')
                ->limit(self::MOBILE_TRAIL_POINT_LIMIT)
                ->get(['latitude', 'longitude', 'sequence_order', 'recorded_at', 'accuracy_m'])
                ->reverse()
                ->values()
            : collect();

        return $route;
    }

    private function forResponder(int $responderId): Builder
    {
        $query = DB::table('responder_assignments as ra')
            ->where(fn (Builder $scope) => $scope->where('ra.responder_id', $responderId)
                ->orWhereJsonContains('ra.route_notes->selected_responder_ids', $responderId));
        $columns = ['ra.*'];

        if (Schema::hasTable('rescue_teams')) {
            $query->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id');
            $columns[] = 'rt.team_name';
            $columns[] = 'rt.team_code';
        } else {
            $columns[] = DB::raw('NULL as team_name');
            $columns[] = DB::raw('NULL as team_code');
        }

        if (Schema::hasTable('disaster_events')) {
            $query->leftJoin('disaster_events as de', 'de.event_id', '=', 'ra.disaster_id');
            $columns[] = 'de.name as event_name';
        } else {
            $columns[] = DB::raw('NULL as event_name');
        }

        if (Schema::hasTable('geotagged_locations')) {
            $query->leftJoin('geotagged_locations as gl', 'gl.household_id', '=', 'ra.household_id');
            $columns[] = 'gl.latitude as household_latitude';
            $columns[] = 'gl.longitude as household_longitude';
            $columns[] = 'gl.location_label as household_location_label';
        } else {
            $columns[] = DB::raw('NULL as household_latitude');
            $columns[] = DB::raw('NULL as household_longitude');
            $columns[] = DB::raw('NULL as household_location_label');
        }

        return $query->select($columns);
    }
}

