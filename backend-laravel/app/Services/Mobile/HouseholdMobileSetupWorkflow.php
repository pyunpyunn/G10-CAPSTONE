<?php

namespace App\Services\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class HouseholdMobileSetupWorkflow
{
    public function __construct(private HouseholdMobileSupport $support) {}

    public function updateGeotag(Request $request): JsonResponse
    {
        $householdId = $this->support->householdId($request->user());
        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'address_label' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($request, $householdId, $validated): void {
            $this->saveGeotag($householdId, $request->user()?->user_id, $validated, now());
            $this->support->writeAuditLog($request, 'mobile_household_geotag', 'households', $householdId, $validated);
        });

        return response()->json(['message' => 'Household geotag saved.']);
    }

    public function completeSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        foreach (['households', 'geotagged_locations', 'device_tokens'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->support->missingTableResponse($table);
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
            $this->support->writeAuditLog(request(), 'mobile_household_setup', 'households', $householdId, $validated);
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
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        if (! Schema::hasTable('device_tokens')) {
            return $this->support->missingTableResponse('device_tokens');
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

    private function saveGeotag(string $householdId, ?string $userId, array $validated, $now): void
    {
        $data = $this->support->filterColumns('geotagged_locations', [
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

        DB::table('geotagged_locations')->insert($this->support->withGeneratedKey(
            'geotagged_locations',
            'location_id',
            $data,
            $this->support->nextId('geotagged_locations', 'location_id')
        ));
    }

    private function saveDevice(string $householdId, ?string $userId, array $validated, $now): int
    {
        $deviceUuid = $validated['device_uuid'];
        $existing = DB::table('device_tokens')
            ->where('household_id', $householdId)
            ->where('device_uuid', $deviceUuid)
            ->first();

        $deviceId = $existing?->id ?: $this->support->nextId('device_tokens', 'id');

        $data = [
            'id' => $deviceId,
            'household_id' => $householdId,
            'user_id' => $userId,
            'device_uuid' => $deviceUuid,
            'device_name' => $validated['device_name'] ?? 'Household mobile',
            'platform' => $validated['platform'] ?? 'mobile',
            'app_role' => 'household',
            'push_provider' => 'onesignal',
            'location_permission_status' => $validated['location_permission_status'] ?? 'granted',
            'last_seen_at' => $now,
            'logged_at' => $now,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if (array_key_exists('member_id', $validated)) {
            $data['member_id'] = $validated['member_id'];
        }

        foreach (['battery_level', 'signal_strength'] as $column) {
            if (array_key_exists($column, $validated) && $validated[$column] !== null) {
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

        $data = $this->support->filterColumns('device_tokens', $data);

        if ($existing) {
            DB::table('device_tokens')->where('id', $existing->id)->update($data);
            return (int) $existing->id;
        }

        DB::table('device_tokens')->insert($this->support->withGeneratedKey(
            'device_tokens',
            'id',
            array_merge($data, ['created_at' => $now]),
            $deviceId
        ));

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

        $trackingId = $this->support->nextId('device_tracking_logs', 'tracking_id');

        DB::table('device_tracking_logs')->insert($this->support->withGeneratedKey('device_tracking_logs', 'tracking_id', [
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
        ], $trackingId));
    }
}







