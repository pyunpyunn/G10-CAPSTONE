<?php

namespace App\Services\Web;

use App\Data\DashboardSnapshot;
use App\Queries\DashboardQuery;
use App\Services\Shared\BarangayProfileService;
use Illuminate\Http\Request;

class DashboardService
{
    public function __construct(
        private DashboardQuery $query,
        private DashboardEventClosureWorkflow $closureWorkflow,
        private BarangayProfileService $barangayProfile,
    ) {}

    public function index(): DashboardSnapshot
    {
        $this->closureWorkflow->releaseEndedEventReferences();
        return $this->snapshot(true);
    }

    public function summary(): DashboardSnapshot
    {
        $this->closureWorkflow->releaseEndedEventReferences();
        return $this->snapshot(false);
    }

    public function dispatch(): array
    {
        return $this->query->getDispatchSummary($this->query->getActiveEvent()?->event_id);
    }

    public function weather(): ?array
    {
        return $this->query->getWeatherSnapshot($this->query->getActiveEvent()?->event_id);
    }

    public function requests(): array
    {
        return $this->query->getRequestSummary();
    }

    public function activity(): array
    {
        return $this->query->getRecentActivity($this->query->getActiveEvent()?->event_id);
    }

    public function closeActiveEvent(Request $request): ?array
    {
        $closedEvent = $this->closureWorkflow->close($request);
        if (! $closedEvent) return null;
        $this->closureWorkflow->releaseEndedEventReferences();
        return ['closed_event' => $closedEvent, 'dashboard' => $this->snapshot(true)];
    }

    private function snapshot(bool $full): DashboardSnapshot
    {
        $event = $this->query->getActiveEvent();
        $eventId = $event?->event_id;
        $households = $this->query->getHouseholdSummary($eventId);
        $latest = $event ? $this->query->latestBroadcast((string) $eventId) : null;
        $profile = $this->barangayProfile->current();

        if (! $full) return new DashboardSnapshot($event, $latest, $profile, $households);

        return new DashboardSnapshot($event, $latest, $profile, $households,
            $this->query->getDispatchSummary($eventId),
            $this->query->getWeatherSnapshot($eventId),
            $this->query->getRequestSummary(),
            $this->query->getMapSummary($eventId, $households),
            $this->query->getRecentActivity($eventId));
    }
}
