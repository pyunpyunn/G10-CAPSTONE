<?php

namespace App\Queries;

use App\Presenters\RescuerAssignmentPresenter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerAssignmentQuery
{
    public function __construct(private RescuerAssignmentPresenter $presenter) {}

    public function list(?int $responderId, int $limit): Collection
    {
        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
            return collect();
        }

        return $this->forResponder($responderId)
            ->orderByRaw("CASE WHEN ra.status IN ('accepted', 'dispatched', 'en_route', 'on_scene') THEN 0 ELSE 1 END")
            ->orderByDesc('ra.assigned_at')
            ->limit($limit)
            ->get()
            ->map(function (object $row): object {
                $row->mobile_route = $this->routeForAssignment((int) $row->assignment_id);

                return $row;
            });
    }

    public function find(int $assignmentId, ?int $responderId): ?object
    {
        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
            return null;
        }

        return $this->forResponder($responderId)
            ->where('ra.assignment_id', $assignmentId)
            ->first();
    }

    public function active(?int $responderId): ?object
    {
        return $this->list($responderId, 10)->first(fn (object $assignment): bool => in_array(
            $this->presenter->statusKey($assignment->status ?? null),
            ['accepted', 'dispatched', 'en_route', 'on_scene'],
            true,
        ));
    }

    public function routeForAssignment(int $assignmentId): ?object
    {
        if (! Schema::hasTable('responder_routes')) {
            return null;
        }

        $route = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('created_at')
            ->first();

        if (! $route) {
            return null;
        }

        $route->mobile_trail_coordinates = Schema::hasTable('route_coordinates')
            ? DB::table('route_coordinates')
                ->where('route_id', $route->route_id)
                ->orderBy('sequence_order')
                ->get(['latitude', 'longitude', 'sequence_order', 'recorded_at', 'accuracy_m'])
                ->values()
            : collect();

        return $route;
    }

    private function forResponder(int $responderId): Builder
    {
        $query = DB::table('responder_assignments as ra')->where('ra.responder_id', $responderId);
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


