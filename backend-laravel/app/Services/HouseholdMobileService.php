<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdMobileService
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user()?->load('role');
        $householdId = $this->householdId($user);

        if (! $householdId) {
            return response()->json([
                'message' => 'This account is not linked to a household record.',
            ], 403);
        }

        if (! Schema::hasTable('households')) {
            return $this->missingTableResponse('households');
        }

        $household = $this->householdRecord($householdId);

        if (! $household) {
            return response()->json([
                'message' => 'Household record was not found in the shared database.',
            ], 404);
        }

        $activeEvent = $this->activeEvent();
        $devices = $this->devices($householdId);
        $members = $this->members($householdId, $devices, $user);
        $geotag = $this->geotag($householdId);

        return response()->json([
            'data' => [
                'profile' => [
                    'user' => $this->formatUser($user),
                    'household' => $this->formatHousehold($household),
                ],
                'setup' => [
                    'is_setup_complete' => $this->isSetupComplete($householdId, $user?->user_id),
                    'has_geotag' => $this->hasGeotag($householdId),
                    'has_device' => $this->hasDevice($householdId, $user?->user_id),
                ],
                'active_event' => $activeEvent,
                'current_status' => $this->currentStatus($householdId, $activeEvent['event_id'] ?? null),
                'status_options' => $this->statusOptions(),
                'status_history' => $this->statusHistoryRows($householdId, $activeEvent['event_id'] ?? null),
                'members' => $members,
                'devices' => $devices,
                'geotag' => $geotag,
                'evacuation_centers' => $this->evacuationCenters($activeEvent['event_id'] ?? null),
                'trusted' => [
                    'is_available' => Schema::hasTable('trusted_households'),
                    'households' => $this->trustedRows($householdId),
                ],
                'qr' => $this->qrPayload($household, $activeEvent),
            ],
        ]);
    }

    public function completeSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        foreach (['households', 'geotagged_locations', 'device_tokens'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->missingTableResponse($table);
            }
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'address_label' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:80'],
            'unit_number' => ['nullable', 'string', 'max:80'],
            'street' => ['nullable', 'string', 'max:150'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'member_id' => ['nullable', 'string', 'max:255'],
            'relationship_to_family' => ['required', 'string', 'max:80'],
            'device_uuid' => ['required', 'string', 'max:150'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:30'],
            'photo_uri' => ['nullable', 'string', 'max:1000'],
        ], [
            'latitude.required' => 'Enable location or pin your household on the map.',
            'longitude.required' => 'Enable location or pin your household on the map.',
            'address_label.required' => 'Confirm the household address.',
            'relationship_to_family.required' => 'Select your relationship to the family.',
            'device_uuid.required' => 'Device identifier is required.',
        ]);

        $now = now();
        $deviceId = null;

        DB::transaction(function () use ($householdId, $user, $validated, $now, &$deviceId): void {
            $this->saveGeotag($householdId, $user?->user_id, $validated, $now);
            $deviceId = $this->saveDevice($householdId, $user?->user_id, $validated, $now);
            $this->saveTrackingLog($householdId, $deviceId, $validated, 'setup_pin', $now);
            $this->writeAuditLog(request(), 'mobile_household_setup', 'households', $householdId, $validated);
        });

        return response()->json([
            'message' => 'Household mobile setup saved.',
            'data' => [
                'device_token_id' => $deviceId,
            ],
        ]);
    }

    public function updateDeviceLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        if (! Schema::hasTable('device_tokens')) {
            return $this->missingTableResponse('device_tokens');
        }

        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:150'],
            'member_id' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'location_permission_status' => ['nullable', 'string', 'max:30'],
        ]);

        $now = now();
        $deviceId = null;

        DB::transaction(function () use ($householdId, $user, $validated, $now, &$deviceId): void {
            $deviceId = $this->saveDevice($householdId, $user?->user_id, $validated, $now);
            $this->saveTrackingLog($householdId, $deviceId, $validated, 'device_heartbeat', $now);
        });

        return response()->json([
            'message' => 'Device location updated.',
            'data' => ['device_token_id' => $deviceId],
        ]);
    }

    public function updateMember(Request $request, string $memberId): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        if (! Schema::hasTable('household_members')) {
            return $this->missingTableResponse('household_members');
        }

        $memberKeyColumn = $this->memberKeyColumn();

        if (! $memberKeyColumn) {
            return response()->json(['message' => 'The household member ID column is not available.'], 503);
        }

        $member = DB::table('household_members')
            ->where('household_id', $householdId)
            ->where($memberKeyColumn, $memberId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Household member was not found.'], 404);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'relationship' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', 'string', 'max:30'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'special_needs' => ['nullable', 'string', 'max:500'],
        ], [
            'first_name.required' => 'Enter the member first name.',
            'last_name.required' => 'Enter the member last name.',
            'relationship.required' => 'Enter the member relationship.',
        ]);

        $now = now();
        $fullName = trim(collect([
            $validated['first_name'],
            $validated['middle_name'] ?? '',
            $validated['last_name'],
        ])->filter()->implode(' '));

        $age = $validated['age'] ?? null;

        if (! $age && ! empty($validated['birth_date'])) {
            $age = Carbon::parse($validated['birth_date'])->age;
        }

        DB::table('household_members')
            ->where('household_id', $householdId)
            ->where($memberKeyColumn, $memberId)
            ->update($this->filterColumns('household_members', [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'name' => $fullName,
                'relation' => $validated['relationship'],
                'relationship_id' => $this->relationshipId($validated['relationship']),
                'gender' => $validated['gender'] ?? null,
                'sex' => $this->sexFromGender($validated['gender'] ?? null),
                'gender_id' => $this->genderId($validated['gender'] ?? null),
                'age' => $age,
                'birth_date' => $validated['birth_date'] ?? null,
                'special_needs' => $validated['special_needs'] ?? null,
                'updated_at' => $now,
            ]));

        $this->writeAuditLog($request, 'mobile_update_household_member', 'household_members', $memberId, $validated);

        $devices = $this->devices($householdId);
        $members = $this->members($householdId, $devices, $user);
        $updatedMember = $members->first(fn (array $item): bool => (string) $item['member_id'] === (string) $memberId);

        return response()->json([
            'message' => 'Household member information saved.',
            'data' => [
                'member' => $updatedMember,
                'members' => $members->values(),
            ],
        ]);
    }

    public function storeStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        foreach (['household_status_logs', 'household_statuses', 'household_disasters'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->missingTableResponse($table);
            }
        }

        $activeEvent = $this->activeEvent();

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

        $status = $this->resolveStatus($validated['status_key']);

        if (! $status) {
            throw ValidationException::withMessages([
                'status_key' => ['Selected status is not available in the shared database.'],
            ]);
        }

        $now = now();
        $statusLogId = null;
        $deviceId = $this->deviceIdForUuid($householdId, $validated['device_uuid'] ?? null);

        DB::transaction(function () use ($request, $householdId, $user, $activeEvent, $validated, $status, $now, &$statusLogId, $deviceId): void {
            $data = $this->filterColumns('household_status_logs', [
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
            $this->saveLatestDisasterStatus($activeEvent['event_id'], $householdId, $status['status_id'], $validated, $user?->user_id, $deviceId, $now);
            $this->saveLatestDeviceForStatus($householdId, $deviceId, $validated, $now);
            $this->writeAuditLog($request, 'mobile_household_status', 'household_status_logs', (string) $statusLogId, $validated);
        });

        return response()->json([
            'message' => 'Household status update saved.',
            'data' => [
                'status_log_id' => $statusLogId,
            ],
        ], 201);
    }

    public function statusHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->householdId($user);
        $activeEvent = $this->activeEvent();

        return response()->json([
            'data' => [
                'logs' => $householdId ? $this->statusHistoryRows($householdId, $activeEvent['event_id'] ?? null) : [],
            ],
        ]);
    }

    public function qr(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->householdId($user);
        $household = $householdId ? $this->householdRecord($householdId) : null;

        if (! $household) {
            return response()->json(['message' => 'Household record was not found.'], 404);
        }

        return response()->json([
            'data' => [
                'qr' => $this->qrPayload($household, $this->activeEvent()),
            ],
        ]);
    }

    public function trustedHouseholds(Request $request): JsonResponse
    {
        $householdId = $this->householdId($request->user());

        return response()->json([
            'data' => [
                'is_available' => Schema::hasTable('trusted_households'),
                'households' => $householdId ? $this->trustedRows($householdId) : [],
            ],
        ]);
    }

    public function lookupTrustedHousehold(Request $request, string $householdId): JsonResponse
    {
        $currentHouseholdId = $this->householdId($request->user());
        $trustedHouseholdId = $this->resolveTrustedHouseholdId($householdId);

        if ($currentHouseholdId === $trustedHouseholdId) {
            return response()->json(['message' => 'You cannot add your own household as trusted.'], 409);
        }

        $household = $trustedHouseholdId ? $this->householdRecord($trustedHouseholdId) : null;

        if (! $household) {
            return response()->json([
                'message' => 'Household account was not found. Enter the household ID like HH-2024035503 or the account ID like 2024035503.',
            ], 404);
        }

        $devices = $this->devices($trustedHouseholdId);

        return response()->json([
            'data' => [
                'household_id' => $household->household_id,
                'family_name' => $this->familyName($household->household_name ?? $household->household_code ?? $household->household_id),
                'household_code' => $household->household_code ?? null,
                'members' => $this->members($trustedHouseholdId, $devices, null)
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
            return $this->missingTableResponse('trusted_households');
        }

        $user = $request->user();
        $householdId = $this->householdId($user);

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

        $trustedHouseholdId = $this->resolveTrustedHouseholdId($validated['trusted_household_id']);

        if ($trustedHouseholdId === $householdId) {
            return response()->json(['message' => 'You cannot add your own household as trusted.'], 409);
        }

        if (! $trustedHouseholdId || ! $this->householdRecord($trustedHouseholdId)) {
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

        DB::table('trusted_households')->insert($this->filterColumns('trusted_households', [
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

        $this->writeAuditLog($request, 'mobile_trusted_household_request', 'trusted_households', $connectionId, array_merge($validated, [
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

    private function householdId($user): ?string
    {
        return $user?->household_id ? (string) $user->household_id : null;
    }

    private function memberKeyColumn(): ?string
    {
        if (! Schema::hasTable('household_members')) {
            return null;
        }

        if (Schema::hasColumn('household_members', 'member_id')) {
            return 'member_id';
        }

        if (Schema::hasColumn('household_members', 'id')) {
            return 'id';
        }

        return null;
    }

    private function resolveTrustedHouseholdId(string $value): ?string
    {
        $input = trim($value);

        if ($input === '' || ! Schema::hasTable('households')) {
            return null;
        }

        $candidates = collect([$input]);

        if (! Str::startsWith(Str::upper($input), 'HH-')) {
            $candidates->push('HH-' . $input);
        }

        if (Schema::hasTable('users')) {
            $userHouseholdId = DB::table('users')
                ->where('username', $input)
                ->value('household_id');

            if ($userHouseholdId) {
                $candidates->push((string) $userHouseholdId);
            }
        }

        $candidateValues = $candidates
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = DB::table('households')
            ->whereIn('household_id', $candidateValues);

        if (Schema::hasColumn('households', 'household_code')) {
            $query->orWhereIn('household_code', $candidateValues);
        }

        return $query->value('household_id');
    }

    private function householdRecord(string $householdId): ?object
    {
        if (! Schema::hasTable('households')) {
            return null;
        }

        $query = DB::table('households as h')
            ->where('h.household_id', $householdId);

        if (Schema::hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        $columns = [
            $this->optionalColumnSelect('households', 'household_id', 'household_id', 'h'),
            $this->optionalColumnSelect('households', 'household_code', 'household_code', 'h'),
            $this->optionalColumnSelect('households', 'household_name', 'household_name', 'h'),
            $this->optionalColumnSelect('households', 'household_number', 'household_number', 'h'),
            $this->optionalColumnSelect('households', 'contact_number', 'contact_number', 'h'),
            $this->optionalColumnSelect('households', 'emergency_contact', 'emergency_contact', 'h'),
            $this->optionalColumnSelect('households', 'member_count', 'member_count', 'h'),
        ];

        if (Schema::hasTable('addresses') && Schema::hasColumn('households', 'address_id') && Schema::hasColumn('addresses', 'address_id')) {
            $query->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id');
            $columns[] = $this->optionalColumnSelect('addresses', 'full_address', 'full_address', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'purok_sitio', 'purok_sitio', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'barangay_name', 'barangay_name', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'city_municipality', 'city_municipality', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'province', 'province', 'a');
        } else {
            $columns[] = DB::raw('NULL as full_address');
            $columns[] = DB::raw('NULL as purok_sitio');
            $columns[] = DB::raw('NULL as barangay_name');
            $columns[] = DB::raw('NULL as city_municipality');
            $columns[] = DB::raw('NULL as province');
        }

        return $query->first($columns);
    }

    private function activeEvent(): ?array
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DB::table('disaster_events as de');
        $columns = ['de.event_id', 'de.name', 'de.started_at'];

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }

        if (Schema::hasColumn('disaster_events', 'ended_at')) {
            $query->whereNull('de.ended_at');
        }

        if (Schema::hasTable('disaster_types')) {
            $query->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id');
            $columns[] = 'dt.type_name';
        } else {
            $columns[] = DB::raw('NULL as type_name');
        }

        if (Schema::hasTable('severity_levels')) {
            $query->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id');
            $columns[] = 'sl.severity_key';
            $columns[] = 'sl.severity_label';
        } else {
            $columns[] = DB::raw('NULL as severity_key');
            $columns[] = DB::raw('NULL as severity_label');
        }

        $event = $query->orderByDesc('de.started_at')->first($columns);

        if (! $event) {
            return null;
        }

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity_key' => $event->severity_key ?? 'monitoring',
            'severity' => $event->severity_label ?? 'Monitoring',
            'started_at' => $event->started_at,
            'message' => 'Follow HQ broadcasts and update your household status when needed.',
            'additional_info' => 'Keep your evacuation QR ready and keep mobile devices charged.',
        ];
    }

    private function devices(string $householdId)
    {
        if (! Schema::hasTable('device_tokens')) {
            return collect();
        }

        $query = DB::table('device_tokens as dt')
            ->where('dt.household_id', $householdId);

        $columns = [
            $this->optionalColumnSelect('device_tokens', 'id', 'id', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'household_id', 'household_id', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'member_id', 'member_id', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'device_uuid', 'device_uuid', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'device_name', 'device_name', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'platform', 'platform', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'battery_level', 'battery_level', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'signal_strength', 'signal_strength', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_latitude', 'last_latitude', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_longitude', 'last_longitude', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_location_label', 'last_location_label', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_location_accuracy_m', 'last_location_accuracy_m', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_location_at', 'last_location_at', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_seen_at', 'last_seen_at', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'is_active', 'is_active', 'dt'),
        ];

        $memberJoinColumn = null;

        if (Schema::hasTable('household_members')) {
            $memberJoinColumn = Schema::hasColumn('household_members', 'member_id')
                ? 'member_id'
                : (Schema::hasColumn('household_members', 'id') ? 'id' : null);
        }

        if (Schema::hasTable('household_members') && Schema::hasColumn('device_tokens', 'member_id') && $memberJoinColumn) {
            $query->leftJoin('household_members as hm', "hm.{$memberJoinColumn}", '=', 'dt.member_id');
            $columns[] = $this->firstExistingColumnSelect('household_members', ['name', 'full_name'], 'member_name', 'hm');
            $columns[] = $this->optionalColumnSelect('household_members', 'first_name', 'first_name', 'hm');
            $columns[] = $this->optionalColumnSelect('household_members', 'last_name', 'last_name', 'hm');
            $columns[] = $this->firstExistingColumnSelect('household_members', ['relation', 'relationship'], 'relation', 'hm');
        } else {
            $columns[] = DB::raw('NULL as member_name');
            $columns[] = DB::raw('NULL as first_name');
            $columns[] = DB::raw('NULL as last_name');
            $columns[] = DB::raw('NULL as relation');
        }

        $orderColumn = Schema::hasColumn('device_tokens', 'last_seen_at') ? 'dt.last_seen_at' : 'dt.id';

        return $query
            ->orderByDesc($orderColumn)
            ->get($columns)
            ->map(fn (object $device): array => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'member_id' => $device->member_id,
                'member_name' => $this->personName($device->member_name, $device->first_name, $device->last_name, 'Household user'),
                'device_name' => $device->device_name ?: 'Household mobile',
                'platform' => $this->label($device->platform ?: 'mobile'),
                'battery_level' => $device->battery_level,
                'signal_strength' => $device->signal_strength,
                'last_location_label' => $device->last_location_label ?: 'No location yet',
                'latitude' => $device->last_latitude,
                'longitude' => $device->last_longitude,
                'last_seen_at' => $device->last_seen_at,
                'last_seen_label' => $this->dateLabel($device->last_seen_at),
                'is_active' => (bool) ($device->is_active ?? true),
            ])
            ->values();
    }

    private function geotag(string $householdId): ?array
    {
        if (! Schema::hasTable('geotagged_locations')) {
            return null;
        }

        $columns = [
            $this->optionalColumnSelect('geotagged_locations', 'location_id', 'location_id'),
            $this->optionalColumnSelect('geotagged_locations', 'household_id', 'household_id'),
            $this->optionalColumnSelect('geotagged_locations', 'latitude', 'latitude'),
            $this->optionalColumnSelect('geotagged_locations', 'longitude', 'longitude'),
            $this->optionalColumnSelect('geotagged_locations', 'location_label', 'location_label'),
            $this->optionalColumnSelect('geotagged_locations', 'accuracy_m', 'accuracy_m'),
            $this->optionalColumnSelect('geotagged_locations', 'geotag_source', 'geotag_source'),
            $this->optionalColumnSelect('geotagged_locations', 'is_verified', 'is_verified'),
            $this->optionalColumnSelect('geotagged_locations', 'created_at', 'created_at'),
            $this->optionalColumnSelect('geotagged_locations', 'updated_at', 'updated_at'),
        ];

        $query = DB::table('geotagged_locations')
            ->where('household_id', $householdId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (Schema::hasColumn('geotagged_locations', 'updated_at')) {
            $query->orderByDesc('updated_at');
        } elseif (Schema::hasColumn('geotagged_locations', 'created_at')) {
            $query->orderByDesc('created_at');
        } elseif (Schema::hasColumn('geotagged_locations', 'location_id')) {
            $query->orderByDesc('location_id');
        }

        $row = $query->first($columns);

        if (! $row || ! $row->latitude || ! $row->longitude) {
            return null;
        }

        return [
            'location_id' => $row->location_id,
            'household_id' => $row->household_id,
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'location_label' => $row->location_label ?: 'Household geotag',
            'accuracy_m' => $row->accuracy_m !== null ? (float) $row->accuracy_m : null,
            'geotag_source' => $row->geotag_source ?: 'household_mobile',
            'is_verified' => (bool) ($row->is_verified ?? false),
            'updated_at' => $row->updated_at ?: $row->created_at,
            'updated_label' => $this->dateLabel($row->updated_at ?: $row->created_at),
        ];
    }

    private function evacuationCenters(?string $eventId): array
    {
        if (
            ! Schema::hasTable('evacuation_centers')
            || ! Schema::hasColumn('evacuation_centers', 'latitude')
            || ! Schema::hasColumn('evacuation_centers', 'longitude')
        ) {
            return [];
        }

        $query = DB::table('evacuation_centers')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('evacuation_centers', 'current_event_id') && $eventId) {
            $query->where(function ($eventQuery) use ($eventId): void {
                $eventQuery
                    ->whereNull('current_event_id')
                    ->orWhere('current_event_id', $eventId);
            });
        }

        $columns = [
            $this->optionalColumnSelect('evacuation_centers', 'evacuation_center_id', 'evacuation_center_id'),
            $this->optionalColumnSelect('evacuation_centers', 'name', 'name'),
            $this->optionalColumnSelect('evacuation_centers', 'latitude', 'latitude'),
            $this->optionalColumnSelect('evacuation_centers', 'longitude', 'longitude'),
            $this->optionalColumnSelect('evacuation_centers', 'capacity', 'capacity'),
            $this->optionalColumnSelect('evacuation_centers', 'current_occupancy', 'current_occupancy'),
            $this->optionalColumnSelect('evacuation_centers', 'status', 'status'),
            $this->optionalColumnSelect('evacuation_centers', 'center_type', 'center_type'),
            $this->optionalColumnSelect('evacuation_centers', 'osm_address', 'osm_address'),
            $this->optionalColumnSelect('evacuation_centers', 'contact_number', 'contact_number'),
        ];

        return $query
            ->orderBy('name')
            ->limit(20)
            ->get($columns)
            ->map(function (object $center): array {
                $capacity = $center->capacity !== null ? (int) $center->capacity : null;
                $occupancy = $center->current_occupancy !== null ? (int) $center->current_occupancy : null;

                return [
                    'evacuation_center_id' => $center->evacuation_center_id,
                    'name' => $center->name ?: 'Evacuation center',
                    'latitude' => (float) $center->latitude,
                    'longitude' => (float) $center->longitude,
                    'capacity' => $capacity,
                    'current_occupancy' => $occupancy,
                    'vacancy' => $capacity !== null && $occupancy !== null ? max($capacity - $occupancy, 0) : null,
                    'status' => $center->status ?: 'active',
                    'center_type' => $center->center_type ?: 'Evacuation center',
                    'address' => $center->osm_address,
                    'contact_number' => $center->contact_number,
                ];
            })
            ->values()
            ->all();
    }

    private function members(string $householdId, $devices, $fallbackUser)
    {
        $devicesByMember = collect($devices)
            ->filter(fn (array $device): bool => ! empty($device['member_id']))
            ->groupBy('member_id')
            ->map(fn ($memberDevices) => $memberDevices->first());

        if (! Schema::hasTable('household_members')) {
            return collect([[
                'member_id' => $fallbackUser?->user_id ?? 'household-user',
                'name' => $fallbackUser?->full_name ?? $fallbackUser?->username ?? 'Household user',
                'relationship' => 'Household member',
                'device' => collect($devices)->first(),
            ]]);
        }

        $memberColumns = [
            $this->firstExistingColumnSelect('household_members', ['member_id', 'id'], 'member_id'),
            $this->firstExistingColumnSelect('household_members', ['name', 'full_name'], 'name'),
            $this->optionalColumnSelect('household_members', 'first_name', 'first_name'),
            $this->optionalColumnSelect('household_members', 'middle_name', 'middle_name'),
            $this->optionalColumnSelect('household_members', 'last_name', 'last_name'),
            $this->firstExistingColumnSelect('household_members', ['relation', 'relationship'], 'relation'),
            $this->optionalColumnSelect('household_members', 'relationship_id', 'relationship_id'),
            $this->optionalColumnSelect('household_members', 'age', 'age'),
            $this->optionalColumnSelect('household_members', 'birth_date', 'birth_date'),
            $this->firstExistingColumnSelect('household_members', ['gender', 'sex'], 'gender'),
            $this->optionalColumnSelect('household_members', 'special_needs', 'special_needs'),
        ];

        $membersQuery = DB::table('household_members')
            ->where('household_id', $householdId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));

        if (Schema::hasColumn('household_members', 'name')) {
            $membersQuery->orderBy('name');
        } elseif (Schema::hasColumn('household_members', 'full_name')) {
            $membersQuery->orderBy('full_name');
        }

        $members = $membersQuery
            ->get($memberColumns)
            ->map(function (object $member) use ($devicesByMember): array {
                $device = $devicesByMember->get($member->member_id);

                return [
                    'member_id' => $member->member_id,
                    'name' => $member->name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                    'first_name' => $member->first_name ?? null,
                    'middle_name' => $member->middle_name ?? null,
                    'last_name' => $member->last_name ?? null,
                    'relationship' => $member->relation ?: 'Member',
                    'relationship_id' => $member->relationship_id ?? null,
                    'age' => $member->age ?? null,
                    'birth_date' => $member->birth_date ?? null,
                    'gender' => $member->gender ?? null,
                    'special_needs' => $member->special_needs ?? null,
                    'device' => $device ?: null,
                ];
            })
            ->values();

        if ($members->isNotEmpty()) {
            return $members;
        }

        return collect([[
            'member_id' => $fallbackUser?->user_id ?? 'household-user',
            'name' => $fallbackUser?->full_name ?? $fallbackUser?->username ?? 'Household user',
            'relationship' => 'Household member',
            'device' => collect($devices)->first(),
        ]]);
    }

    private function currentStatus(string $householdId, ?string $eventId): ?array
    {
        if (! $eventId || ! Schema::hasTable('household_disasters')) {
            return null;
        }

        if (Schema::hasColumn('household_disasters', 'current_status_id')) {
            $statusColumn = 'hd.current_status_id';
        } elseif (Schema::hasColumn('household_disasters', 'initial_status_id')) {
            $statusColumn = 'hd.initial_status_id';
        } else {
            return null;
        }

        $query = DB::table('household_disasters as hd')
            ->where('hd.disaster_id', $eventId)
            ->where('hd.household_id', $householdId);

        $columns = [
            $this->optionalColumnSelect('household_disasters', 'household_id', 'household_id', 'hd'),
            $this->optionalColumnSelect('household_disasters', 'last_status_notes', 'last_status_notes', 'hd'),
            $this->optionalColumnSelect('household_disasters', 'last_battery_level', 'last_battery_level', 'hd'),
            $this->optionalColumnSelect('household_disasters', 'last_reported_at', 'last_reported_at', 'hd'),
        ];

        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', $statusColumn);
            $columns = array_merge($columns, [
                'hs.status_id',
                'hs.status_key',
                $this->householdStatusLabelSelect('hs', 'status_label'),
            ]);
        } else {
            $columns[] = DB::raw('NULL as status_id');
            $columns[] = DB::raw('NULL as status_key');
            $columns[] = DB::raw('NULL as status_label');
        }

        $row = $query->first($columns);

        if (! $row || ! $row->status_id) {
            return null;
        }

        return [
            'status_id' => $row->status_id,
            'status_key' => $this->mobileStatusKey($row->status_key),
            'status_label' => $this->mobileStatusLabel($row->status_key, $row->status_label),
            'notes' => $row->last_status_notes,
            'battery_level' => $row->last_battery_level,
            'last_saved_at' => $row->last_reported_at,
            'last_saved_label' => $this->dateLabel($row->last_reported_at),
        ];
    }

    private function statusHistoryRows(string $householdId, ?string $eventId): array
    {
        if (! Schema::hasTable('household_status_logs')) {
            return [];
        }

        $query = DB::table('household_status_logs as hsl')
            ->where('hsl.household_id', $householdId)
            ->when($eventId && Schema::hasColumn('household_status_logs', 'disaster_id'), fn ($query) => $query->where('hsl.disaster_id', $eventId));

        $columns = [
            $this->optionalColumnSelect('household_status_logs', 'status_log_id', 'status_log_id', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'location_label', 'location_label', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'battery_level', 'battery_level', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'notes', 'notes', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'submitted_at', 'submitted_at', 'hsl'),
        ];

        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id');
            $columns[] = 'hs.status_key';
            $columns[] = $this->householdStatusLabelSelect('hs', 'status_label');
        } else {
            $columns[] = DB::raw('NULL as status_key');
            $columns[] = DB::raw('NULL as status_label');
        }

        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
            ? 'hsl.submitted_at'
            : 'hsl.status_log_id';

        return $query
            ->orderByDesc($orderColumn)
            ->limit(30)
            ->get($columns)
            ->map(fn (object $log): array => [
                'status_log_id' => $log->status_log_id,
                'status_key' => $this->mobileStatusKey($log->status_key),
                'status_label' => $this->mobileStatusLabel($log->status_key, $log->status_label),
                'location_label' => $log->location_label,
                'battery_level' => $log->battery_level,
                'notes' => $log->notes,
                'submitted_at' => $log->submitted_at,
                'submitted_label' => $this->dateLabel($log->submitted_at),
            ])
            ->values()
            ->all();
    }

    private function trustedRows(string $householdId): array
    {
        if (! Schema::hasTable('trusted_households')) {
            return [];
        }

        $activeEvent = $this->activeEvent();

        return DB::table('trusted_households as th')
            ->leftJoin('households as h', 'h.household_id', '=', 'th.trusted_household_id')
            ->where('th.requesting_household_id', $householdId)
            ->orderByDesc('th.created_at')
            ->get([
                'th.connection_id',
                'th.trusted_household_id',
                'th.reason',
                'th.validation_status',
                'th.member_relationships',
                'th.created_at',
                'h.household_name',
                'h.household_code',
            ])
            ->map(function (object $row) use ($activeEvent): array {
                $trustedHouseholdId = (string) $row->trusted_household_id;
                $isValidated = in_array(strtolower((string) ($row->validation_status ?? 'pending')), ['validated', 'approved'], true);
                $devices = $isValidated ? $this->devices($trustedHouseholdId) : collect();

                return [
                    'connection_id' => $row->connection_id,
                    'household_id' => $trustedHouseholdId,
                    'family_name' => $this->familyName($row->household_name ?? $row->household_code ?? $row->trusted_household_id),
                    'reason' => $row->reason,
                    'validation_status' => $row->validation_status ?? 'pending',
                    'member_relationships' => $this->decodeJson($row->member_relationships),
                    'current_status' => $isValidated ? $this->currentStatus($trustedHouseholdId, $activeEvent['event_id'] ?? null) : null,
                    'members' => $isValidated ? $this->members($trustedHouseholdId, $devices, null)->values() : [],
                    'devices' => $devices->values(),
                    'created_at' => $row->created_at,
                ];
            })
            ->values()
            ->all();
    }

    private function statusOptions(): array
    {
        return collect([
            ['key' => 'safe', 'label' => 'Safe'],
            ['key' => 'evacuated', 'label' => 'Evacuated'],
            ['key' => 'unsafe', 'label' => 'Unsafe'],
            ['key' => 'needs_help', 'label' => 'Needs help'],
        ])->map(function (array $option): array {
            $status = $this->resolveStatus($option['key']);
            $option['status_id'] = $status['status_id'] ?? null;

            return $option;
        })->values()->all();
    }

    private function resolveStatus(string $mobileKey): ?array
    {
        if (! Schema::hasTable('household_statuses')) {
            return null;
        }

        $map = [
            'safe' => ['safe', 'active', 'returned'],
            'evacuated' => ['evacuated', 'relocated'],
            'unsafe' => ['unsafe', 'not_evacuated', 'displaced'],
            'needs_help' => ['injured', 'missing', 'not_evacuated', 'unsafe'],
        ];

        $row = DB::table('household_statuses')
            ->whereIn('status_key', $map[$mobileKey] ?? [$mobileKey])
            ->orderBy('status_id')
            ->first([
                'status_id',
                'status_key',
                $this->householdStatusLabelSelect('household_statuses', 'status_label'),
            ]);

        if (! $row) {
            return null;
        }

        return [
            'status_id' => $row->status_id,
            'status_key' => $row->status_key,
            'status_label' => $row->status_label,
        ];
    }

    private function saveGeotag(string $householdId, ?string $userId, array $validated, $now): void
    {
        $data = $this->filterColumns('geotagged_locations', [
            'household_id' => $householdId,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'location_label' => $validated['address_label'],
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'geotag_source' => 'household_mobile',
            'is_verified' => 0,
            'created_by_user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $existing = DB::table('geotagged_locations')->where('household_id', $householdId)->first();

        if ($existing) {
            DB::table('geotagged_locations')->where('household_id', $householdId)->update($data);
            return;
        }

        DB::table('geotagged_locations')->insert($this->filterColumns('geotagged_locations', array_merge($data, [
            'location_id' => $this->nextId('geotagged_locations', 'location_id'),
        ])));
    }

    private function saveDevice(string $householdId, ?string $userId, array $validated, $now): int
    {
        $deviceUuid = $validated['device_uuid'];
        $existing = DB::table('device_tokens')
            ->where('household_id', $householdId)
            ->where('device_uuid', $deviceUuid)
            ->first();

        $deviceId = $existing?->id ?: $this->nextId('device_tokens', 'id');

        $data = [
            'id' => $deviceId,
            'household_id' => $householdId,
            'user_id' => $userId,
            'device_uuid' => $deviceUuid,
            'device_name' => $validated['device_name'] ?? 'Household mobile',
            'platform' => $validated['platform'] ?? 'mobile',
            'app_role' => 'household',
            'location_permission_status' => $validated['location_permission_status'] ?? 'granted',
            'last_seen_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (array_key_exists('member_id', $validated)) {
            $data['member_id'] = $validated['member_id'];
        }

        foreach (['battery_level', 'signal_strength'] as $column) {
            if (array_key_exists($column, $validated)) {
                $data[$column] = $validated[$column];
            }
        }

        if (array_key_exists('latitude', $validated)) {
            $data['last_latitude'] = $validated['latitude'];
        }

        if (array_key_exists('longitude', $validated)) {
            $data['last_longitude'] = $validated['longitude'];
        }

        if (array_key_exists('address_label', $validated) || array_key_exists('location_label', $validated)) {
            $data['last_location_label'] = $validated['address_label'] ?? $validated['location_label'] ?? null;
        }

        if (array_key_exists('accuracy_m', $validated) || array_key_exists('location_accuracy_m', $validated)) {
            $data['last_location_accuracy_m'] = $validated['accuracy_m'] ?? $validated['location_accuracy_m'] ?? null;
        }

        if (array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated)) {
            $data['last_location_at'] = $now;
        }

        $data = $this->filterColumns('device_tokens', $data);

        if ($existing) {
            DB::table('device_tokens')->where('id', $existing->id)->update($data);
            return (int) $existing->id;
        }

        DB::table('device_tokens')->insert($data);

        return (int) $deviceId;
    }

    private function saveTrackingLog(string $householdId, ?int $deviceId, array $validated, string $source, $now): void
    {
        if (! $deviceId || ! Schema::hasTable('device_tracking_logs')) {
            return;
        }

        if (! array_key_exists('latitude', $validated) || ! array_key_exists('longitude', $validated)) {
            return;
        }

        DB::table('device_tracking_logs')->insert($this->filterColumns('device_tracking_logs', [
            'tracking_id' => $this->nextId('device_tracking_logs', 'tracking_id'),
            'device_token_id' => $deviceId,
            'household_id' => $householdId,
            'member_id' => $validated['member_id'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'location_label' => $validated['address_label'] ?? $validated['location_label'] ?? null,
            'accuracy_m' => $validated['accuracy_m'] ?? $validated['location_accuracy_m'] ?? null,
            'location_source' => $source,
            'is_allowed_location' => true,
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'logged_at' => $now,
        ]));
    }

    private function saveLatestDisasterStatus(string $eventId, string $householdId, int $statusId, array $validated, ?string $userId, ?int $deviceId, $now): void
    {
        $existing = DB::table('household_disasters')
            ->where('disaster_id', $eventId)
            ->where('household_id', $householdId)
            ->first();

        $data = $this->filterColumns('household_disasters', [
            'current_status_id' => $statusId,
            'last_status_source' => 'household_mobile',
            'last_status_notes' => $validated['notes'] ?? null,
            'last_reported_by_user_id' => $userId,
            'last_device_token_id' => $deviceId,
            'last_latitude' => $validated['latitude'] ?? null,
            'last_longitude' => $validated['longitude'] ?? null,
            'last_battery_level' => $validated['battery_level'] ?? null,
            'last_reported_at' => $now,
            'priority_level' => $validated['status_key'] === 'needs_help' ? 'urgent' : 'monitor',
            'needs_dispatch' => $validated['status_key'] === 'needs_help',
            'updated_at' => $now,
        ]);

        if ($existing) {
            DB::table('household_disasters')
                ->where('household_disaster_id', $existing->household_disaster_id)
                ->update($data);

            return;
        }

        DB::table('household_disasters')->insert($this->filterColumns('household_disasters', array_merge($data, [
            'household_disaster_id' => $this->nextId('household_disasters', 'household_disaster_id'),
            'household_id' => $householdId,
            'disaster_id' => $eventId,
            'initial_status_id' => $statusId,
            'created_at' => $now,
        ])));
    }

    private function saveLatestDeviceForStatus(string $householdId, ?int $deviceId, array $validated, $now): void
    {
        if (! $deviceId || ! Schema::hasTable('device_tokens')) {
            return;
        }

        DB::table('device_tokens')
            ->where('id', $deviceId)
            ->where('household_id', $householdId)
            ->update($this->filterColumns('device_tokens', [
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'last_latitude' => $validated['latitude'] ?? null,
                'last_longitude' => $validated['longitude'] ?? null,
                'last_location_label' => $validated['location_label'] ?? null,
                'last_location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
                'last_location_at' => $now,
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]));
    }

    private function deviceIdForUuid(string $householdId, ?string $deviceUuid): ?int
    {
        if (! $deviceUuid || ! Schema::hasTable('device_tokens')) {
            return null;
        }

        return DB::table('device_tokens')
            ->where('household_id', $householdId)
            ->where('device_uuid', $deviceUuid)
            ->value('id');
    }

    private function isSetupComplete(string $householdId, ?string $userId): bool
    {
        return $this->hasGeotag($householdId) && $this->hasDevice($householdId, $userId);
    }

    private function hasGeotag(string $householdId): bool
    {
        return Schema::hasTable('geotagged_locations')
            && DB::table('geotagged_locations')->where('household_id', $householdId)->whereNotNull('latitude')->whereNotNull('longitude')->exists();
    }

    private function hasDevice(string $householdId, ?string $userId): bool
    {
        if (! Schema::hasTable('device_tokens')) {
            return false;
        }

        $query = DB::table('device_tokens')->where('household_id', $householdId);

        if ($userId && Schema::hasColumn('device_tokens', 'user_id')) {
            $query->where('user_id', $userId);
        }

        return $query->exists();
    }

    private function qrPayload(object $household, ?array $activeEvent): array
    {
        $payload = [
            'household_id' => $household->household_id,
            'household_name' => $household->household_name ?? $household->household_code,
            'event_id' => $activeEvent['event_id'] ?? null,
            'purpose' => 'evacuation_check_in',
            'issued_at' => now()->toIso8601String(),
        ];

        return [
            'label' => 'Evacuation QR',
            'value' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'household_id' => $household->household_id,
            'household_name' => $household->household_name ?? $household->household_code,
        ];
    }

    private function formatUser($user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'user_id' => $user->user_id,
            'full_name' => $user->full_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
            'username' => $user->username,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'role' => [
                'role_key' => $user->role?->role_key,
                'role_name' => $user->role?->role_name,
            ],
        ];
    }

    private function formatHousehold(object $household): array
    {
        return [
            'household_id' => $household->household_id,
            'household_code' => $household->household_code ?? null,
            'household_name' => $household->household_name ?? 'Household account',
            'family_name' => $this->familyName($household->household_name ?? $household->household_id),
            'contact_number' => $household->contact_number ?? null,
            'emergency_contact' => $household->emergency_contact ?? null,
            'member_count' => (int) ($household->member_count ?? 0),
            'address' => $household->full_address ?? 'No address recorded',
            'purok' => $household->purok_sitio ?? 'Not recorded',
            'barangay' => $household->barangay_name ?? 'Not recorded',
            'city' => $household->city_municipality ?? 'Not recorded',
            'province' => $household->province ?? 'Not recorded',
        ];
    }

    private function mobileStatusKey(?string $statusKey): string
    {
        if (in_array($statusKey, ['safe', 'active', 'returned'], true)) {
            return 'safe';
        }

        if (in_array($statusKey, ['evacuated', 'relocated'], true)) {
            return 'evacuated';
        }

        if (in_array($statusKey, ['injured', 'missing'], true)) {
            return 'needs_help';
        }

        if (in_array($statusKey, ['unsafe', 'not_evacuated', 'displaced'], true)) {
            return 'unsafe';
        }

        return $statusKey ?: 'unchecked';
    }

    private function mobileStatusLabel(?string $statusKey, ?string $statusLabel): string
    {
        return match ($this->mobileStatusKey($statusKey)) {
            'safe' => 'Safe',
            'evacuated' => 'Evacuated',
            'unsafe' => 'Unsafe',
            'needs_help' => 'Needs help',
            default => $statusLabel ?: 'Unchecked',
        };
    }

    private function familyName(string $name): string
    {
        $parts = collect(explode(' ', trim($name)))->filter()->values();

        return $parts->last() ?: $name;
    }

    private function personName($name, $firstName, $lastName, string $fallback): string
    {
        $fullName = trim((string) ($name ?: trim(($firstName ?? '') . ' ' . ($lastName ?? ''))));

        return $fullName !== '' ? $fullName : $fallback;
    }

    private function relationshipId(?string $relationship): ?int
    {
        if (! $relationship || ! Schema::hasTable('relationships')) {
            return null;
        }

        $value = trim($relationship);
        $key = Str::of($value)->lower()->replace(['/', '-'], ' ')->snake()->toString();
        $keys = collect([$key]);

        if (in_array($key, ['son', 'daughter', 'son_daughter'], true)) {
            $keys->push('child');
        }

        if (in_array($key, ['father', 'mother', 'father_mother'], true)) {
            $keys->push('parent');
        }

        if (in_array($key, ['brother', 'sister', 'brother_sister'], true)) {
            $keys->push('sibling');
        }

        if (Str::contains($key, 'relative')) {
            $keys->push('other');
        }

        $id = DB::table('relationships')
            ->whereRaw('LOWER(relationship_label) = ?', [Str::lower($value)])
            ->orWhereIn('relationship_key', $keys->unique()->values()->all())
            ->orderBy('relationship_id')
            ->value('relationship_id');

        return $id ? (int) $id : null;
    }

    private function genderId(?string $gender): ?int
    {
        if (! $gender || ! Schema::hasTable('genders')) {
            return null;
        }

        $value = trim($gender);
        $key = Str::of($value)->lower()->replace(['/', '-'], ' ')->snake()->toString();

        $id = DB::table('genders')
            ->whereRaw('LOWER(gender_label) = ?', [Str::lower($value)])
            ->orWhere('gender_key', $key)
            ->value('gender_id');

        return $id ? (int) $id : null;
    }

    private function sexFromGender(?string $gender): ?string
    {
        $value = Str::lower(trim((string) $gender));

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, 'm')) {
            return 'M';
        }

        if (Str::startsWith($value, 'f')) {
            return 'F';
        }

        return 'O';
    }

    private function label(?string $value): string
    {
        return Str::of($value ?: '')
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function dateLabel(?string $value): string
    {
        if (! $value) {
            return 'Not recorded';
        }

        return Carbon::parse($value)->timezone('Asia/Manila')->format('M d, Y g:i A');
    }

    private function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nextId(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 1;
        }

        return ((int) DB::table($table)->max($column)) + 1;
    }

    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, string $key): bool => in_array($key, $columns, true))
            ->all();
    }

    private function optionalColumnSelect(string $table, string $column, string $alias, ?string $tableAlias = null)
    {
        if (Schema::hasColumn($table, $column)) {
            $prefix = $tableAlias ?: $table;

            return "{$prefix}.{$column} as {$alias}";
        }

        return DB::raw("NULL as {$alias}");
    }

    private function firstExistingColumnSelect(string $table, array $columns, string $alias, ?string $tableAlias = null)
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $prefix = $tableAlias ?: $table;

                return "{$prefix}.{$column} as {$alias}";
            }
        }

        return DB::raw("NULL as {$alias}");
    }

    private function householdStatusLabelSelect(string $tableAlias, string $alias)
    {
        if (Schema::hasColumn('household_statuses', 'status_label')) {
            return "{$tableAlias}.status_label as {$alias}";
        }

        if (Schema::hasColumn('household_statuses', 'status_name')) {
            return "{$tableAlias}.status_name as {$alias}";
        }

        return DB::raw("NULL as {$alias}");
    }

    private function writeAuditLog(Request $request, string $action, string $table, string $referenceId, array $values): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert($this->filterColumns('audit_logs', [
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'household_mobile',
            'action' => $action,
            'reference_table' => $table,
            'reference_id' => $referenceId,
            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]));
    }

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => "The {$table} table is not available yet. Ask the DB member to apply the approved shared schema before this mobile action can save data.",
        ], 503);
    }
}
