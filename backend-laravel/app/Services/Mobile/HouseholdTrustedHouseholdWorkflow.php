<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdTrustedHouseholdWorkflow
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $readQuery,
        private HouseholdMobilePresenter $presenter,
        private HouseholdMobileStatusWriter $statusWriter,
        private \App\Services\Shared\OneSignalNotificationService $oneSignal,
    ) {}

    public function trustedHouseholds(Request $request): JsonResponse
    {
        $householdId = $this->support->householdId($request->user());

        return response()->json([
            'data' => [
                'is_available' => Schema::hasTable('trusted_households'),
                'pin_configured' => $householdId ? $this->readQuery->hasTrustedPin($householdId) : false,
                'households' => $householdId ? $this->readQuery->trustedRows($householdId) : [],
                'incoming_requests' => $householdId ? $this->readQuery->incomingTrustedRows($householdId) : [],
            ],
        ]);
    }

    public function saveTrustedPin(Request $request): JsonResponse
    {
        if (! Schema::hasTable('household_trusted_pins')) {
            return $this->support->missingTableResponse('household_trusted_pins');
        }

        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        $existing = DB::table('household_trusted_pins')
            ->where('household_id', $householdId)
            ->first(['pin_hash']);
        $validated = $request->validate([
            'pin' => ['required', 'digits:4', 'confirmed'],
            'current_pin' => [$existing ? 'required' : 'nullable', 'digits:4'],
        ]);

        if ($existing && ! Hash::check($validated['current_pin'], $existing->pin_hash)) {
            return response()->json(['message' => 'Current household PIN did not match.'], 422);
        }

        if ($existing && Hash::check($validated['pin'], $existing->pin_hash)) {
            return response()->json(['message' => 'New household PIN must be different from the current PIN.'], 422);
        }

        $now = now();
        $pinData = [
            'pin_hash' => Hash::make($validated['pin']),
            'updated_by_user_id' => $user?->user_id,
            'updated_at' => $now,
        ];

        DB::transaction(function () use ($existing, $householdId, $pinData, $now): void {
            if ($existing) {
                DB::table('household_trusted_pins')
                    ->where('household_id', $householdId)
                    ->update($pinData);

                return;
            }

            DB::table('household_trusted_pins')->insert(array_merge($pinData, [
                'household_id' => $householdId,
                'created_at' => $now,
            ]));
        });

        return response()->json([
            'message' => $existing ? 'Household PIN changed.' : 'Household PIN saved.',
            'data' => ['pin_configured' => true],
        ]);
    }

    public function verifyTrustedPin(Request $request): JsonResponse
    {
        if (! Schema::hasTable('household_trusted_pins')) {
            return $this->support->missingTableResponse('household_trusted_pins');
        }

        $householdId = $this->support->householdId($request->user());

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        $validated = $request->validate(['pin' => ['required', 'digits:4']]);
        $storedPin = DB::table('household_trusted_pins')
            ->where('household_id', $householdId)
            ->first(['pin_hash']);

        if (! $storedPin) {
            return response()->json(['message' => 'Set a household PIN before opening trusted households.'], 409);
        }

        if (! Hash::check($validated['pin'], $storedPin->pin_hash)) {
            return response()->json(['message' => 'Household PIN did not match.'], 422);
        }

        return response()->json(['message' => 'Household PIN verified.']);
    }

    public function storeTrustedMemberStatus(Request $request, string $connectionId, string $memberId): JsonResponse
    {
        if (! Schema::hasTable('trusted_households')) {
            return $this->support->missingTableResponse('trusted_households');
        }

        $reportingHouseholdId = $this->support->householdId($request->user());

        if (! $reportingHouseholdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        $connection = DB::table('trusted_households')
            ->where('connection_id', $connectionId)
            ->whereIn('validation_status', ['validated', 'approved'])
            ->where(function ($query) use ($reportingHouseholdId): void {
                $query->where('requesting_household_id', $reportingHouseholdId)
                    ->orWhere('trusted_household_id', $reportingHouseholdId);
            })
            ->first(['requesting_household_id', 'trusted_household_id']);

        if (! $connection) {
            return response()->json(['message' => 'Trusted household connection was not found.'], 404);
        }

        $targetHouseholdId = (string) ((string) $connection->requesting_household_id === $reportingHouseholdId
            ? $connection->trusted_household_id
            : $connection->requesting_household_id);

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

        $member = $this->readQuery->householdMember($targetHouseholdId, $memberId);

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

        $user = $request->user();
        $now = now();
        $deviceId = $this->statusWriter->deviceIdForUuid($reportingHouseholdId, $validated['device_uuid'] ?? null);
        $memberName = $this->presenter->personName($member->name ?? null, $member->first_name ?? null, $member->last_name ?? null, 'Household member');
        $statusLogId = null;

        DB::transaction(function () use ($request, $targetHouseholdId, $reportingHouseholdId, $memberId, $memberName, $user, $activeEvent, $validated, $status, $now, $deviceId, &$statusLogId): void {
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
                'household_id' => $targetHouseholdId,
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

            $this->statusWriter->saveMemberDisasterStatus(
                $activeEvent['event_id'],
                $targetHouseholdId,
                $memberId,
                $validated,
                $user?->user_id,
                $deviceId,
                $now
            );
            $this->statusWriter->saveLatestHouseholdStatusFromMemberStatuses(
                $activeEvent['event_id'],
                $targetHouseholdId,
                $validated,
                $user?->user_id,
                $deviceId,
                $now
            );
            $this->support->writeAuditLog($request, 'mobile_trusted_member_status', 'household_status_logs', (string) $statusLogId, array_merge($validated, [
                'household_id' => $targetHouseholdId,
                'reporting_household_id' => $reportingHouseholdId,
                'member_id' => $memberId,
                'member_name' => $memberName,
            ]));
        });

        return response()->json([
            'message' => 'Trusted household member status saved.',
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

    public function lookupTrustedHousehold(Request $request, string $householdId): JsonResponse
    {
        $currentHouseholdId = $this->support->householdId($request->user());
        $trustedHouseholdId = $this->readQuery->resolveTrustedHouseholdId($householdId);

        if ($currentHouseholdId === $trustedHouseholdId) {
            return response()->json(['message' => 'You cannot add your own household as trusted.'], 409);
        }

        $household = $trustedHouseholdId ? $this->readQuery->householdRecord($trustedHouseholdId) : null;

        if (! $household) {
            return response()->json([
                'message' => 'Household account was not found. Enter the household ID like HH-2024035503 or the account ID like 2024035503.',
            ], 404);
        }

        $devices = $this->readQuery->devices($trustedHouseholdId);

        return response()->json([
            'data' => [
                'household_id' => $household->household_id,
                'family_name' => $this->presenter->familyName($household->household_name ?? $household->household_code ?? $household->household_id),
                'household_code' => $household->household_code ?? null,
                'members' => $this->readQuery->members($trustedHouseholdId, $devices, null, $this->readQuery->activeEvent()['event_id'] ?? null)
                    ->map(fn (array $member): array => [
                        'member_id' => $member['member_id'],
                        'name' => $member['name'],
                        'household_relationship' => $member['relationship'] ?? 'Member',
                        'relationship_to_family' => '',
                    ])
                    ->values(),
            ],
        ]);
    }

    public function storeTrustedHousehold(Request $request): JsonResponse
    {
        if (! Schema::hasTable('trusted_households')) {
            return $this->support->missingTableResponse('trusted_households');
        }

        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        $validated = $request->validate([
            'trusted_household_id' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
            'member_relationships' => ['nullable', 'array'],
        ], [
            'trusted_household_id.required' => 'Enter the household ID you want to connect with.',
            'reason.required' => 'Give a short reason for this trusted household request.',
        ]);

        $trustedHouseholdId = $this->readQuery->resolveTrustedHouseholdId($validated['trusted_household_id']);

        if ($trustedHouseholdId === $householdId) {
            return response()->json(['message' => 'You cannot add your own household as trusted.'], 409);
        }

        if (! $trustedHouseholdId || ! $this->readQuery->householdRecord($trustedHouseholdId)) {
            return response()->json([
                'message' => 'Household account was not found. Enter the household ID like HH-2024035503 or the account ID like 2024035503.',
            ], 404);
        }

        $existing = DB::table('trusted_households')
            ->where('requesting_household_id', $householdId)
            ->where('trusted_household_id', $trustedHouseholdId)
            ->whereIn('validation_status', ['pending', 'validated'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Trusted household request already exists.',
                'data' => [
                    'connection_id' => $existing->connection_id,
                    'validation_status' => $existing->validation_status,
                ],
            ]);
        }

        $now = now();
        $connectionId = 'TH-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        DB::table('trusted_households')->insert($this->support->filterColumns('trusted_households', [
            'connection_id' => $connectionId,
            'requesting_household_id' => $householdId,
            'trusted_household_id' => $trustedHouseholdId,
            'reason' => $validated['reason'],
            'validation_status' => 'pending',
            'member_relationships' => json_encode($validated['member_relationships'] ?? [], JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => $user?->user_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->support->writeAuditLog($request, 'mobile_trusted_household_request', 'trusted_households', $connectionId, array_merge($validated, [
            'resolved_trusted_household_id' => $trustedHouseholdId,
        ]));

        return response()->json([
            'message' => 'Trusted household request submitted for validation.',
            'data' => [
                'connection_id' => $connectionId,
                'validation_status' => 'pending',
            ],
        ], 201);
    }

    public function respondToTrustedHousehold(Request $request, string $connectionId): JsonResponse
    {
        if (! Schema::hasTable('trusted_households')) {
            return $this->support->missingTableResponse('trusted_households');
        }

        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['accept', 'reject'])],
        ]);
        $accepting = $validated['decision'] === 'accept';
        $validationStatus = $accepting ? 'validated' : 'rejected';
        $now = now();
        $updated = 0;
        $rejectedRequest = null;

        DB::transaction(function () use ($request, $connectionId, $householdId, $user, $accepting, $validationStatus, $validated, $now, &$updated, &$rejectedRequest): void {
            $connection = DB::table('trusted_households')
                ->where('connection_id', $connectionId)
                ->where('trusted_household_id', $householdId)
                ->where('validation_status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $connection) {
                return;
            }

            if ($accepting) {
                $updated = DB::table('trusted_households')
                    ->where('connection_id', $connectionId)
                    ->where('validation_status', 'pending')
                    ->update($this->support->filterColumns('trusted_households', [
                        'validation_status' => $validationStatus,
                        'validated_by_user_id' => $user?->user_id,
                        'validated_at' => $now,
                        'updated_at' => $now,
                    ]));
            } else {
                $updated = DB::table('trusted_households')
                    ->where('connection_id', $connectionId)
                    ->where('validation_status', 'pending')
                    ->delete();
                $rejectedRequest = $connection;
            }

            if ($updated === 1) {
                $this->support->writeAuditLog($request, 'mobile_trusted_household_'.$validated['decision'], 'trusted_households', $connectionId, [
                    'decision' => $validated['decision'],
                    'validation_status' => $validationStatus,
                    'trusted_household_id' => $householdId,
                ]);
            }
        });

        if ($updated !== 1) {
            return response()->json([
                'message' => 'This pending trusted household request was not found or was already answered.',
            ], 404);
        }

        if (! $accepting && $rejectedRequest?->created_by_user_id) {
            $this->oneSignal->sendToMobileDevices(
                'Trusted household request declined',
                'Your trusted household connection request was declined.',
                [
                    'roles' => ['household'],
                    'user_ids' => [$rejectedRequest->created_by_user_id],
                    'data' => [
                        'type' => 'trusted_household_request_declined',
                        'connection_id' => $connectionId,
                    ],
                ]
            );
        }

        return response()->json([
            'message' => $accepting ? 'Trusted household request accepted.' : 'Trusted household request declined.',
            'data' => [
                'connection_id' => $connectionId,
                'validation_status' => $validationStatus,
                'validated_at' => $now->toDateTimeString(),
            ],
        ]);
    }
}







