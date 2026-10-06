<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdMobileStatusWorkflow
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $readQuery,
        private HouseholdMobilePresenter $presenter,
        private HouseholdMobileStatusWriter $writer,
    ) {}

    public function storeStatusFromSms(string $householdCode, string $statusKey, string $from, string $rawText): array
    {
        return app(HouseholdSmsStatusWorkflow::class)->storeStatusFromSms($householdCode, $statusKey, $from, $rawText);
    }

    public function storeMemberStatus(Request $request, string $memberId): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        foreach (['household_status_logs', 'household_statuses', 'household_members', 'household_disasters'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->support->missingTableResponse($table);
            }
        }

        $activeEvent = $this->readQuery->activeEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'Family member status can only be saved during an active disaster event.',
            ], 409);
        }

        $member = $this->readQuery->householdMember($householdId, $memberId);

        if (! $member) {
            return response()->json(['message' => 'Household member was not found.'], 404);
        }

        $validated = $request->validate([
            'status_key' => ['required', Rule::in(['safe', 'evacuated', 'unsafe', 'needs_help'])],
            'device_uuid' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'status_key.required' => 'Choose a family member status first.',
            'status_key.in' => 'Choose a valid family member status.',
        ]);

        $status = $this->readQuery->resolveStatus($validated['status_key']);

        if (! $status) {
            throw ValidationException::withMessages([
                'status_key' => ['Selected status is not available in the shared database.'],
            ]);
        }

        $now = now();
        $statusLogId = null;
        $deviceId = $this->writer->deviceIdForUuid($householdId, $validated['device_uuid'] ?? null);
        $memberName = $this->presenter->personName($member->name ?? null, $member->first_name ?? null, $member->last_name ?? null, 'Household member');

        DB::transaction(function () use ($request, $householdId, $user, $activeEvent, $validated, $status, $now, &$statusLogId, $deviceId, $memberId, $memberName): void {
            $notes = [
                'report_type' => 'member_status',
                'member_id' => $memberId,
                'member_name' => $memberName,
                'mobile_status_key' => $validated['status_key'],
                'mobile_status_label' => $this->presenter->mobileStatusLabel($validated['status_key'], null),
                'member_notes' => $validated['notes'] ?? null,
            ];

            $statusLogId = DB::table('household_status_logs')->insertGetId($this->support->filterColumns('household_status_logs', [
                'disaster_id' => $activeEvent['event_id'],
                'household_id' => $householdId,
                'status_id' => $status['status_id'],
                'source' => 'household_member_mobile',
                'submitted_by_user_id' => $user?->user_id,
                'device_token_id' => $deviceId,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'location_label' => $validated['location_label'] ?? null,
                'location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'notes' => json_encode($notes, JSON_UNESCAPED_SLASHES),
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]), 'status_log_id');

            $this->writer->saveMemberDisasterStatus(
                $activeEvent['event_id'],
                $householdId,
                $memberId,
                $validated,
                $user?->user_id,
                $deviceId,
                $now
            );

            $this->writer->saveLatestHouseholdStatusFromMemberStatuses(
                $activeEvent['event_id'],
                $householdId,
                $validated,
                $user?->user_id,
                $deviceId,
                $now
            );

            $this->support->writeAuditLog($request, 'mobile_member_status', 'household_status_logs', (string) $statusLogId, array_merge($validated, [
                'member_id' => $memberId,
                'member_name' => $memberName,
            ]));
        });

        return response()->json([
            'message' => 'Family member status saved.',
            'data' => [
                'status_log_id' => $statusLogId,
                'member_status' => [
                    'status_log_id' => $statusLogId,
                    'member_id' => $memberId,
                    'member_name' => $memberName,
                    'status_id' => $status['status_id'],
                    'status_key' => $validated['status_key'],
                    'status_label' => $this->presenter->mobileStatusLabel($validated['status_key'], null),
                    'notes' => $validated['notes'] ?? null,
                    'submitted_at' => $now->toDateTimeString(),
                    'submitted_label' => $this->presenter->dateLabel($now->toDateTimeString()),
                ],
            ],
        ], 201);
    }

    public function storeStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        foreach (['household_status_logs', 'household_statuses', 'household_disasters'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->support->missingTableResponse($table);
            }
        }

        $activeEvent = $this->readQuery->activeEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'Status updates can only be saved during an active disaster event.',
            ], 409);
        }

        $validated = $request->validate([
            'status_key' => ['required', Rule::in(['safe', 'evacuated', 'unsafe', 'needs_help'])],
            'device_uuid' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'status_key.required' => 'Choose your household status first.',
            'status_key.in' => 'Choose a valid household status.',
        ]);

        $status = $this->readQuery->resolveStatus($validated['status_key']);

        if (! $status) {
            throw ValidationException::withMessages([
                'status_key' => ['Selected status is not available in the shared database.'],
            ]);
        }

        $validated['notes'] = $this->presenter->statusNotesJson($validated['status_key'], $validated['notes'] ?? null);

        $now = now();
        $statusLogId = null;
        $deviceId = $this->writer->deviceIdForUuid($householdId, $validated['device_uuid'] ?? null);

        DB::transaction(function () use ($request, $householdId, $user, $activeEvent, $validated, $status, $now, &$statusLogId, $deviceId): void {
            $data = $this->support->filterColumns('household_status_logs', [
                'disaster_id' => $activeEvent['event_id'],
                'household_id' => $householdId,
                'status_id' => $status['status_id'],
                'source' => 'household_mobile',
                'submitted_by_user_id' => $user?->user_id,
                'device_token_id' => $deviceId,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'location_label' => $validated['location_label'] ?? null,
                'location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $statusLogId = DB::table('household_status_logs')->insertGetId($data, 'status_log_id');
            $this->writer->saveLatestDisasterStatus($activeEvent['event_id'], $householdId, $status['status_id'], $validated, $user?->user_id, $deviceId, $now);
            $this->writer->saveLatestDeviceForStatus($householdId, $deviceId, $validated, $now);
            $this->support->writeAuditLog($request, 'mobile_household_status', 'household_status_logs', (string) $statusLogId, $validated);
        });

        return response()->json([
            'message' => 'Household status update saved.',
            'data' => [
                'status_log_id' => $statusLogId,
            ],
        ], 201);
    }

}







