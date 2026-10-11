<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdStatusPresenter;
use App\Queries\HouseholdStatusQuery;
use App\Queries\AreaCoverageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdStatusService
{
    public function __construct(
        private HouseholdStatusQuery $query,
        private HouseholdStatusPresenter $presenter,
        private HouseholdStatusWorkflow $workflow,
        private AreaCoverageQuery $coverage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $event = $this->query->getActiveEvent();
        $eventId = $event?->event_id;
        $perPage = \App\Http\Requests\ListRequest::clampPerPage($request->query('per_page'));
        $query = $this->query->householdListQuery($eventId);
        $this->query->applyListFilters($query, $request);
        $paginator = $query
            ->orderByRaw('CASE WHEN hd.needs_dispatch = 1 THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN hs.status_key IN ("not_evacuated", "displaced", "unsafe", "needs_help", "need_help", "needs_assistance", "missing", "injured") THEN 0 ELSE 1 END')
            ->orderByDesc('hd.last_reported_at')
            ->orderBy('h.household_name')
            ->paginate($perPage);
        $items = collect($paginator->items());
        $householdIds = $items->pluck('household_id')->filter()->values();
        $latestLogs = $this->query->getLatestLogs($householdIds, $eventId);
        $latestDevices = $this->query->getLatestDevices($householdIds);
        $accountUsers = $this->query->getHouseholdAccountUsers($householdIds);
        $rows = $items->map(fn (object $row): array => $this->presenter->formatHouseholdRow(
            $row,
            $latestLogs->get($row->household_id),
            $latestDevices->get($row->household_id),
            $accountUsers->get($row->household_id),
            (bool) $event,
        ))->values();

        return response()->json(['data' => [
            'area_label' => $this->coverage->label(),
            'active_event' => $event ? $this->presenter->formatActiveEvent($event) : null,
            'summary' => $this->query->getSummary($eventId),
            'filters' => ['puroks' => $this->query->getPurokOptions()],
            'households' => ['data' => $rows, 'meta' => [
                'current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(),
                'total' => $paginator->total(), 'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
            ]],
            'purok_summary' => $this->query->getPurokSummary($eventId),
            'recent_activity' => $this->query->getRecentActivity($eventId),
        ]]);
    }

    public function show(string $householdId): JsonResponse
    {
        $event = $this->query->getActiveEvent();
        $eventId = $event?->event_id;
        $household = $this->query->getHouseholdRecord($householdId, $eventId);
        if (! $household) {
            return response()->json(['message' => 'Household record was not found.'], 404);
        }

        $latestLog = $this->query->getLatestLogs(collect([$householdId]), $eventId)->get($householdId);
        $latestDevice = $this->query->getLatestDevices(collect([$householdId]))->get($householdId);
        $accountUser = $this->query->getHouseholdAccountUsers(collect([$householdId]))->get($householdId);
        $devices = $this->query->getDevices($householdId);

        return response()->json(['data' => [
            'active_event' => $event ? $this->presenter->formatActiveEvent($event) : null,
            'household' => $this->presenter->formatHouseholdDetail(
                $household, $latestLog, $latestDevice, $accountUser, $devices, (bool) $event,
                $this->query->householdRiskSummary($householdId),
            ),
            'members' => $this->query->getMembers($householdId, $eventId, $devices),
            'devices' => $devices,
        ]]);
    }

    public function statusLogs(string $householdId): JsonResponse
    {
        $event = $this->query->getActiveEvent();
        $logs = $this->query->statusLogs($householdId, $event?->event_id)
            ->map(fn (object $log): array => $this->presenter->formatStatusLog($log))->values();
        return response()->json(['data' => [
            'active_event' => $event ? $this->presenter->formatActiveEvent($event) : null,
            'logs' => $logs,
        ]]);
    }

    public function storeStatusLog(Request $request, string $householdId): JsonResponse
    {
        return $this->workflow->storeStatusLog($request, $householdId);
    }

    public function confirmStatus(Request $request, string $householdId): JsonResponse
    {
        return $this->workflow->confirmStatus($request, $householdId);
    }
}







