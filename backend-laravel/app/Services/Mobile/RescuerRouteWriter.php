<?php

namespace App\Services\Mobile;

use App\Queries\RescuerAssignmentQuery;
use App\Services\Shared\RoutingService;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class RescuerRouteWriter
{
    public function __construct(private RoutingService $routingService, private RescuerMobileSupport $support, private RescuerAssignmentQuery $assignmentQuery) {}

    public function saveRouteCoordinate(int $assignmentId, array $validated, $now): void
    {
        if (! Schema::hasTable('responder_routes') || ! Schema::hasTable('route_coordinates')) {
            return;
        }

        DB::transaction(function () use ($assignmentId, $validated, $now): void {
        $assignment = DB::table('responder_assignments')->where('assignment_id', $assignmentId)->lockForUpdate()->first();
        $responderId = (int) ($validated['responder_id'] ?? $assignment->responder_id);
        $route = $this->routeQuery($assignmentId, $responderId)
            ->when(Schema::hasColumn('responder_routes', 'route_status'), fn ($query) => $query->where('route_status', 'active'))
            ->orderByDesc('created_at')
            ->first();

        if (! $route) {
            $routeId = $this->support->nextId('responder_routes', 'route_id');

            DB::table('responder_routes')->insert($this->support->filterColumns('responder_routes', [
                'route_id' => $routeId,
                'assignment_id' => $assignmentId,
                'route_name' => 'Responder mobile route',
                'responder_id' => $responderId,
                'route_status' => 'active',
                'household_id' => \App\Support\RescuerHouseholdQueue::target($assignment, $responderId),
                'start_latitude' => $validated['latitude'],
                'start_longitude' => $validated['longitude'],
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $route = (object) ['route_id' => $routeId];
        }

        $nextOrder = ((int) DB::table('route_coordinates')->where('route_id', $route->route_id)->max('sequence_order')) + 1;

        DB::table('route_coordinates')->insert($this->support->filterColumns('route_coordinates', [
            'coordinate_id' => $this->support->nextId('route_coordinates', 'coordinate_id'),
            'route_id' => $route->route_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'sequence_order' => $nextOrder,
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'recorded_at' => $now,
        ]));
        }, 3);
    }

    private function planRoadRoute(object $assignment, array $start): ?array
    {
        if (! Schema::hasTable('responder_routes') || ! $this->hasPoint($start)) {
            return null;
        }

        $target = $this->assignmentTarget($assignment, (int) ($start['responder_id'] ?? $assignment->responder_id), $start['household_id'] ?? null);

        if (! $target) {
            return null;
        }

        $route = $this->routingService->drivingRoute(
            (float) $start['latitude'],
            (float) $start['longitude'],
            $target['latitude'],
            $target['longitude'],
        );
        return $route ? ['route' => $route, 'target' => $target] : null;
    }

    public function refreshPlannedRoadRoute(int $assignmentId, array $start): void
    {
        $assignment = DB::table('responder_assignments')->where('assignment_id', $assignmentId)->first();
        if (! $assignment || in_array($assignment->status, ['completed', 'cancelled'], true)) return;
        $responderId = (int) ($start['responder_id'] ?? $assignment->responder_id);
        $assignment = $this->assignmentQuery->find($assignmentId, $responderId);
        if (! $assignment) return;
        $plannedRoute = $this->planRoadRoute($assignment, $start);
        if (! $plannedRoute) return;

        DB::transaction(function () use ($assignmentId, $assignment, $start, $plannedRoute): void {
            $current = DB::table('responder_assignments')->where('assignment_id', $assignmentId)->lockForUpdate()->first();
            if (! $current || in_array($current->status, ['completed', 'cancelled'], true)) return;
            $target = $this->assignmentTarget($current, (int) ($start['responder_id'] ?? $current->responder_id), $start['household_id'] ?? null);
            if ($target !== $plannedRoute['target']) return; // Destination changed while the provider was routing.
            $this->savePlannedRoadRoute($assignmentId, $assignment, $start, now(), $plannedRoute);
        }, 3);
    }

    private function savePlannedRoadRoute(int $assignmentId, object $assignment, array $start, $now, ?array $plannedRoute): void
    {
        if (! $plannedRoute) {
            return;
        }
        ['route' => $route, 'target' => $target] = $plannedRoute;

        $responderId = (int) ($start['responder_id'] ?? $assignment->responder_id);
        $existingRoute = $this->routeQuery($assignmentId, $responderId)
            ->when(Schema::hasColumn('responder_routes', 'household_id'), fn ($query) => $query->where('household_id', $target['household_id']))
            ->orderByDesc('created_at')
            ->first();

        $data = $this->support->filterColumns('responder_routes', [
            'assignment_id' => $assignmentId,
            'responder_id' => $responderId,
            'household_id' => $target['household_id'],
            'navigation_steps' => json_encode($route['steps'] ?? []),
            'route_name' => 'Road route to '.($assignment->assigned_area ?: $assignment->household_id ?: 'assigned location'),
            'route_status' => 'active',
            'start_latitude' => $start['latitude'],
            'start_longitude' => $start['longitude'],
            'end_latitude' => $target['latitude'],
            'end_longitude' => $target['longitude'],
            'estimated_distance_km' => $route['distance_km'],
            'estimated_duration_min' => $route['duration_min'],
            'route_polyline' => json_encode($route['coordinates'], JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ]);

        if ($existingRoute) {
            DB::table('responder_routes')
                ->where('route_id', $existingRoute->route_id)
                ->update($data);

            return;
        }

        DB::table('responder_routes')->insert(array_merge($data, $this->support->filterColumns('responder_routes', [
            'route_id' => $this->support->nextId('responder_routes', 'route_id'),
            'created_at' => $now,
        ])));
    }

    private function routeQuery(int $assignmentId, int $responderId)
    {
        $query = DB::table('responder_routes')->where('assignment_id', $assignmentId);
        if (Schema::hasColumn('responder_routes', 'responder_id')) {
            $query->where('responder_id', $responderId);
        }
        return $query;
    }

    private function assignmentTarget(object $assignment, int $responderId, ?string $requestedHouseholdId = null): ?array
    {
        $notes = json_decode((string) ($assignment->route_notes ?? ''), true) ?: [];
        $coverage = $notes['responder_households'] ?? [];
        // An explicitly empty coverage list must not inherit another rescuer's target.
        $ids = \App\Support\RescuerHouseholdQueue::pending($assignment, $responderId);
        $householdId = $requestedHouseholdId && in_array($requestedHouseholdId, $ids, true)
            ? $requestedHouseholdId : ($ids[0] ?? null);
        if (! $householdId) return null;
        $point = DB::table('geotagged_locations')->where('household_id', $householdId)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderByDesc('updated_at')->orderByDesc('location_id')->first();
        if (! $point) return null;
        return ['household_id' => $householdId, 'latitude' => (float) $point->latitude, 'longitude' => (float) $point->longitude];
    }

    private function hasPoint(array $data): bool
    {
        return array_key_exists('latitude', $data)
            && array_key_exists('longitude', $data)
            && $data['latitude'] !== null
            && $data['longitude'] !== null;
    }

}
