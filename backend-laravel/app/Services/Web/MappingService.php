<?php

namespace App\Services\Web;

use App\Data\MappingSnapshot;
use App\Queries\MappingQuery;
use App\Services\Shared\BarangayProfileService;
use Illuminate\Http\Request;

class MappingService
{
    public function __construct(private MappingQuery $query, private BarangayProfileService $barangayProfile) {}

    public function overview(Request $request): MappingSnapshot
    {
        return $this->snapshot($request, true);
    }

    public function workspace(Request $request): MappingSnapshot
    {
        return $this->snapshot($request, false);
    }

    public function householdGeotags(Request $request): array
    {
        [, $eventId, $hasEvent] = $this->eventContext($request);
        $focus = $this->barangayProfile->mapFocus();
        return $hasEvent ? $this->query->getHouseholdGeotags($request, $eventId, $focus['barangay_id']) : [];
    }

    public function evacuationSites(Request $request): array
    {
        [, $eventId, $hasEvent] = $this->eventContext($request);
        $focus = $this->barangayProfile->mapFocus();
        return $hasEvent ? $this->insideBoundary($this->query->getEvacuationSites($eventId), $focus) : [];
    }

    public function dispatchRoutes(Request $request): array
    {
        [, $eventId, $hasEvent] = $this->eventContext($request);
        $focus = $this->barangayProfile->mapFocus();
        return $hasEvent ? [
            'rescue_teams' => $this->query->getRescueTeamMarkers($eventId),
            'dispatch_routes' => $this->routesInsideBoundary($this->query->getDispatchRoutes($eventId), $focus),
        ] : [];
    }

    private function snapshot(Request $request, bool $full): MappingSnapshot
    {
        $request->validate(['purok_id' => ['sometimes', 'integer', 'min:1']]);
        [$event, $eventId, $hasEvent] = $this->eventContext($request);
        $focus = $this->barangayProfile->mapFocus();
        $sites = $hasEvent ? $this->insideBoundary($this->query->getEvacuationSites($eventId), $focus) : [];
        $siteCount = count(array_filter($sites,
            fn (array $site): bool => ($site['status'] ?? null) === 'active'));
        $summary = $this->query->getSummary($eventId, $hasEvent, $focus['barangay_id'], $siteCount);
        return new MappingSnapshot(
            $event,
            $focus,
            $summary,
            $this->query->getPuroks($focus['barangay_id']),
            $full,
            $full && $hasEvent ? $this->query->getHouseholdGeotags($request, $eventId, $focus['barangay_id']) : [],
            $full && $hasEvent ? $sites : [],
            $full && $hasEvent ? $this->query->getRescueTeamMarkers($eventId) : [],
            $full && $hasEvent ? $this->routesInsideBoundary($this->query->getDispatchRoutes($eventId), $focus) : [],
            $this->query->getPurokOptions($focus['barangay_id']),
        );
    }

    public function householdRoute(string $householdId): array
    {
        $event = $this->query->getActiveEvent();
        abort_unless($event, 409, 'Household routing requires an active disaster event.');
        $focus = $this->barangayProfile->mapFocus();
        $request = Request::create('/', 'GET', ['household_id' => $householdId]);
        $points = $this->query->getHouseholdGeotags($request, $event->event_id, $focus['barangay_id']);
        $household = collect($points)->firstWhere('household_id', $householdId);
        abort_unless($household, 404, 'This household has no available geotag in the current barangay.');
        $office = config('rescue_office');
        abort_unless(is_numeric($office['latitude'] ?? null) && is_numeric($office['longitude'] ?? null), 422, 'Rescue office coordinates are not configured.');
        $route = app(\App\Services\Shared\RoutingService::class)->drivingRoute(
            (float) $office['latitude'], (float) $office['longitude'], $household['latitude'], $household['longitude']);
        abort_unless($route, 503, 'A driving route cannot be generated right now. Please try again later.');
        return ['household_id' => $householdId, 'origin_name' => $office['name'] ?? 'Rescue office',
            'distance_km' => $route['distance_km'], 'duration_min' => $route['duration_min'],
            'coordinates' => array_map(fn ($point) => [$point['lat'], $point['lng']], $route['coordinates'])];
    }

    public function evacuationRoute(string $siteId): array
    {
        $event = $this->query->getActiveEvent();
        $focus = $this->barangayProfile->mapFocus();
        $sites = $this->insideBoundary($this->query->getEvacuationSites($event?->event_id), $focus);
        $site = collect($sites)->first(fn (array $row): bool => (string) $row['id'] === $siteId);
        abort_unless($site, 404, 'This evacuation center is not available in the current barangay.');
        $office = config('rescue_office');
        abort_unless(is_numeric($office['latitude'] ?? null) && is_numeric($office['longitude'] ?? null)
            && abs((float) $office['latitude']) <= 90 && abs((float) $office['longitude']) <= 180,
            422, 'Barangay hall coordinates are not configured.');
        $route = app(\App\Services\Shared\RoutingService::class)->drivingRoute(
            (float) $office['latitude'], (float) $office['longitude'], (float) $site['latitude'], (float) $site['longitude']);
        abort_unless($route, 503, 'A road route to this evacuation center cannot be generated right now. Please try again.');
        return ['site_id' => $site['id'], 'origin_name' => $office['name'] ?? 'Barangay hall',
            'destination_name' => $site['name'], 'distance_km' => $route['distance_km'], 'duration_min' => $route['duration_min'],
            'coordinates' => array_map(fn ($point) => [$point['lat'], $point['lng']], $route['coordinates'])];
    }

    private function insideBoundary(array $points, array $focus): array
    {
        if (! $focus['boundary']) return $focus['barangay_id'] === null ? [] : $points;
        $boundaries = app(\App\Services\Shared\BarangayBoundaryService::class);
        return array_values(array_filter($points, fn (array $point): bool =>
            isset($point['latitude'], $point['longitude'])
            && $boundaries->contains($focus['boundary'], (float) $point['latitude'], (float) $point['longitude'])));
    }

    private function routesInsideBoundary(array $routes, array $focus): array
    {
        // Rescue roads can cross the boundary between origin and destination.
        $ids = array_values(array_unique(array_filter(array_column($routes, 'household_id'))));
        $eligible = $ids ? \Illuminate\Support\Facades\DB::table('households')->whereIn('household_id',$ids)
            ->whereIn('household_id', \App\Queries\HouseholdEligibilityQuery::ids($focus['barangay_id'] ?? null))->pluck('household_id')->all() : [];
        $boundaries = app(\App\Services\Shared\BarangayBoundaryService::class);
        return array_values(array_filter($routes, function ($route) use ($focus,$eligible,$boundaries) {
            if (count($route['coordinates'] ?? []) < 2) return false;
            if (!empty($route['household_id'])) return in_array($route['household_id'],$eligible,true);
            return !empty($focus['boundary']) && collect($route['coordinates'])->contains(fn ($point) => $boundaries->contains($focus['boundary'],(float)$point[0],(float)$point[1]));
        }));
    }

    private function eventContext(Request $request): array
    {
        $event = $this->query->getActiveEvent();
        $eventId = $request->query('event_id') ?: $event?->event_id;
        return [$event, $eventId, (bool) $event && (string) $eventId === (string) $event->event_id];
    }
}
