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
        return $hasEvent ? $this->insideBoundary(
            $this->query->getHouseholdGeotags($request, $eventId, $focus['barangay_id']), $focus,
        ) : [];
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
            'rescue_teams' => $this->insideBoundary($this->query->getRescueTeamMarkers($eventId), $focus),
            'dispatch_routes' => $this->routesInsideBoundary($this->query->getDispatchRoutes($eventId), $focus),
        ] : [];
    }

    private function snapshot(Request $request, bool $full): MappingSnapshot
    {
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
            $full && $hasEvent ? $this->insideBoundary(
                $this->query->getHouseholdGeotags($request, $eventId, $focus['barangay_id']), $focus) : [],
            $full && $hasEvent ? $sites : [],
            $full && $hasEvent ? $this->insideBoundary($this->query->getRescueTeamMarkers($eventId), $focus) : [],
            $full && $hasEvent ? $this->routesInsideBoundary($this->query->getDispatchRoutes($eventId), $focus) : [],
        );
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
        if (! $focus['boundary']) return [];
        $boundaries = app(\App\Services\Shared\BarangayBoundaryService::class);
        return array_values(array_filter($routes, fn (array $route): bool =>
            count($route['coordinates'] ?? []) >= 2
            && collect($route['coordinates'])->every(fn (array $point): bool =>
                $boundaries->contains($focus['boundary'], (float) $point[0], (float) $point[1]))));
    }

    private function eventContext(Request $request): array
    {
        $event = $this->query->getActiveEvent();
        $eventId = $request->query('event_id') ?: $event?->event_id;
        return [$event, $eventId, (bool) $event && (string) $eventId === (string) $event->event_id];
    }
}
