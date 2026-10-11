<?php

namespace App\Services\Mobile;

use App\Queries\RescuerMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerCheckInWorkflow
{
    public function __construct(private \App\Services\Mobile\RescuerMobileSupport $support, private RescuerMobileReadQuery $query) {}

    public function list(Request $request)
    {
        $responder = $this->support->responderForUser($request->user());
        return $this->query->checkIns($responder?->responder_id, 30);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_check_ins')) {
            return response()->json(['message' => 'The required responder_check_ins table is not available in the current database.'], 503);
        }
        $activeEvent = $this->support->activeEvent();
        if (! $activeEvent) return response()->json(['message' => 'Check-ins can only be recorded during an active disaster event.'], 409);

        $validated = $request->validate([
            'household_id' => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status_key' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'check_in_method' => ['nullable', 'string', 'max:80'],
        ]);
        $user = $request->user();
        $responder = $this->support->responderForUser($user);
        $checkInId = $this->support->nextId('responder_check_ins', 'check_in_id');
        DB::table('responder_check_ins')->insert($this->support->filterColumns('responder_check_ins', [
            'check_in_id' => $checkInId,
            'responder_id' => $responder?->responder_id,
            'disaster_id' => $activeEvent->event_id,
            'team_id' => $responder?->team_id ?? null,
            'household_id' => $validated['household_id'],
            'member_id' => $validated['member_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'check_in_method' => $validated['check_in_method'] ?? 'field_visit',
            'status_id' => $this->support->resolveHouseholdStatusId($validated['status_key'] ?? 'safe'),
            'notes' => $validated['notes'] ?? null,
            'checked_in_at' => now(),
            'verified_by' => $user?->user_id,
        ]));

        return response()->json(['message' => 'Household check-in recorded.', 'data' => ['check_in_id' => $checkInId]], 201);
    }
}







