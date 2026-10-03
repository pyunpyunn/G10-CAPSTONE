<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\RequestSchema as Schema;

class HouseholdMobileReadWorkflow
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $query,
        private HouseholdMobilePresenter $presenter,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user()?->load('role');
        $householdId = $this->support->householdId($user);
        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }
        if (! Schema::hasTable('households')) return $this->support->missingTableResponse('households');

        $household = $this->query->householdRecord($householdId);
        if (! $household) {
            return response()->json(['message' => 'Household record was not found in the shared database.'], 404);
        }
        $activeEvent = $this->query->activeEvent();
        $devices = $this->query->devices($householdId);
        $members = $this->query->members($householdId, $devices, $activeEvent['event_id'] ?? null);
        $geotag = $this->query->geotag($householdId);

        return response()->json(['data' => [
            'profile' => [
                'user' => $this->presenter->userProfile($user, $this->query->memberForUser($user)),
                'household' => $this->presenter->formatHousehold($household, $members->count()),
            ],
            'setup' => [
                'is_setup_complete' => $this->query->hasGeotag($householdId) && $this->query->hasDevice($householdId, $user?->user_id),
                'has_geotag' => $this->query->hasGeotag($householdId),
                'has_device' => $this->query->hasDevice($householdId, $user?->user_id),
            ],
            'active_event' => $activeEvent,
            'current_status' => $this->query->currentStatus($householdId, $activeEvent['event_id'] ?? null),
            'status_options' => $this->query->statusOptions(),
            'status_history' => $this->query->statusHistoryRows($householdId, $activeEvent['event_id'] ?? null),
            'members' => $members,
            'devices' => $devices,
            'geotag' => $geotag,
            'evacuation_centers' => $this->query->evacuationCenters($activeEvent['event_id'] ?? null, $geotag['latitude'] ?? null, $geotag['longitude'] ?? null),
            'recent_alerts' => $this->query->recentAlerts($activeEvent['event_id'] ?? null),
            'trusted' => [
                'is_available' => Schema::hasTable('trusted_households'),
                'households' => $this->query->trustedRows($householdId),
            ],
            'qr' => $this->presenter->qrPayload($household, $activeEvent),
        ]]);
    }

    public function statusHistory(Request $request): JsonResponse
    {
        $householdId = $this->support->householdId($request->user());
        $activeEvent = $this->query->activeEvent();
        return response()->json(['data' => [
            'logs' => $householdId ? $this->query->statusHistoryRows($householdId, $activeEvent['event_id'] ?? null) : [],
        ]]);
    }

    public function qr(Request $request): JsonResponse
    {
        $householdId = $this->support->householdId($request->user());
        $household = $householdId ? $this->query->householdRecord($householdId) : null;
        if (! $household) return response()->json(['message' => 'Household record was not found.'], 404);
        return response()->json(['data' => ['qr' => $this->presenter->qrPayload($household, $this->query->activeEvent())]]);
    }
}







