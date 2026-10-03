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
        return $hasEvent ? $this->query->getHouseholdGeotags($request, $eventId) : [];
    }

    public function evacuationSites(Request $request): array
    {
        [, $eventId, $hasEvent] = $this->eventContext($request);
        return $hasEvent ? $this->query->getEvacuationSites($eventId) : [];
    }

    public function dispatchRoutes(Request $request): array
    {
        [, $eventId, $hasEvent] = $this->eventContext($request);
        return $hasEvent ? [
            'rescue_teams' => $this->query->getRescueTeamMarkers($eventId),
            'dispatch_routes' => $this->query->getDispatchRoutes($eventId),
        ] : [];
    }

    private function snapshot(Request $request, bool $full): MappingSnapshot
    {
        [$event, $eventId, $hasEvent] = $this->eventContext($request);
        return new MappingSnapshot(
            $event,
            $this->barangayProfile->mapFocus(),
            $this->query->getSummary($eventId, $hasEvent),
            $this->query->getPuroks(),
            $full,
            $full && $hasEvent ? $this->query->getHouseholdGeotags($request, $eventId) : [],
            $full && $hasEvent ? $this->query->getEvacuationSites($eventId) : [],
            $full && $hasEvent ? $this->query->getRescueTeamMarkers($eventId) : [],
            $full && $hasEvent ? $this->query->getDispatchRoutes($eventId) : [],
        );
    }

    private function eventContext(Request $request): array
    {
        $event = $this->query->getActiveEvent();
        $eventId = $request->query('event_id') ?: $event?->event_id;
        return [$event, $eventId, (bool) $event && (string) $eventId === (string) $event->event_id];
    }
}
