<?php

namespace App\Services\Mobile;

use App\Jobs\RecomputeRoutesForHousehold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;

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
            'sitio_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);
        $mambalingFocus = app(\App\Services\Shared\BarangayProfileService::class)->mambalingMapFocus();
        if (! $mambalingFocus['boundary']) {
            return response()->json(['message' => 'The Mambaling boundary is unavailable, so this location cannot be validated.'], 503);
        }
        $barangayId = (int) ($mambalingFocus['barangay_id'] ?? 0);
        if (isset($validated['sitio_id']) && $barangayId < 1) {
            return response()->json(['message' => 'The registered Mambaling barangay ID is unavailable.'], 503);
        }
        if (isset($validated['sitio_id'])) {
            foreach (['sitios' => ['sitio_id', 'sitio_name', 'barangay_id'], 'addresses' => ['address_id', 'sitio_id', 'barangay_id'], 'households' => ['address_id']] as $table => $columns) {
                if (! Schema::hasTable($table) || collect($columns)->contains(fn (string $column): bool => ! Schema::hasColumn($table, $column))) {
                    return response()->json(['message' => 'The registered Mambaling sitio and household address schema is unavailable.'], 503);
                }
            }
            $validated = array_merge($validated, $request->validate([
                'sitio_id' => [Rule::exists('sitios', 'sitio_id')->where(fn ($query) => $query->where('barangay_id', $barangayId))],
            ]));
        }
        $classifier = app(\App\Services\Shared\HouseholdGeographicClassifier::class);
        $classification = $classifier->resolve((float) $validated['latitude'], (float) $validated['longitude'], $mambalingFocus);
        if ($classification['status'] === 'barangay_boundary_missing') {
            return response()->json(['message' => 'The Mambaling boundary is unavailable, so this location cannot be validated.'], 503);
        }
        if ($classification['status'] === 'outside_barangay') {
            return response()->json(['message' => 'Choose a household location inside the Mambaling boundary.'], 422);
        }
        $confirmedSitio = isset($validated['sitio_id'])
            ? DB::table('sitios')->where('sitio_id', (int) $validated['sitio_id'])->where('barangay_id', $barangayId)->first(['sitio_id', 'sitio_name'])
            : null;
        if (isset($validated['sitio_id']) && ! $confirmedSitio) {
            return response()->json(['message' => 'Choose a sitio registered in Mambaling.'], 422);
        }
        if ($confirmedSitio && $classification['status'] === 'matched' && (int) $classification['sitio_id'] !== (int) $confirmedSitio->sitio_id) {
            return response()->json(['message' => 'The selected sitio does not match the verified boundary at this pin.'], 422);
        }
        DB::transaction(function () use ($request, $householdId, $validated, $confirmedSitio, $barangayId, $mambalingFocus, &$classification): void {
            $classification = $this->saveGeotag($householdId, $request->user()?->user_id, $validated, now(), $confirmedSitio, $barangayId, $mambalingFocus);
            $this->support->writeAuditLog($request, 'mobile_household_geotag', 'households', $householdId, $validated);
        });
        $this->queueRouteRecompute($householdId);

        return response()->json(['message' => 'Household geotag saved.', 'data' => ['geographic_classification' => $classification]]);
    }

    public function previewSetupLocation(Request $request): JsonResponse
    {
        $householdId = $this->support->householdId($request->user());
        abort_unless($householdId, 403, 'This account is not linked to a household.');
        $this->support->authenticatedMemberId($request->user());
        $geotag = app(\App\Queries\HouseholdMobileReadQuery::class)->geotag($householdId);
        if ($geotag) return response()->json(['data' => ['reused_geotag' => true, 'geotag' => $geotag]]);
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        return response()->json(['data' => [
            'reused_geotag' => false,
            'address' => app(HouseholdSetupAddress::class)->resolve($householdId, (float) $coordinates['latitude'], (float) $coordinates['longitude']),
        ]]);
    }

    public function completeSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->support->householdId($user);
        abort_unless($householdId, 403, 'This account is not linked to a household record.');
        foreach (['households', 'geotagged_locations', 'device_tokens'] as $table) {
            if (! Schema::hasTable($table)) return $this->support->missingTableResponse($table);
        }
        $device = $request->validate([
            'device_uuid' => ['required', 'string', 'max:150'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:30'],
        ]);
        $device['member_id'] = $this->support->authenticatedMemberId($user);
        $result = DB::transaction(function () use ($request, $user, $householdId, $device): array {
            $household = DB::table('households')->where('household_id', $householdId)->lockForUpdate()->first();
            abort_unless($household && empty($household->deleted_at), 404, 'Household record was not found.');
            $existing = app(\App\Queries\HouseholdMobileReadQuery::class)->geotag($householdId);
            if ($existing) {
                $deviceId = $this->saveDevice($householdId, $user->user_id, $device, now());
                return ['device_token_id' => $deviceId, 'reused_geotag' => true, 'geotag' => $existing];
            }
            $apartment = $request->boolean('is_apartment');
            $validated = $request->validate([
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'accuracy_m' => ['nullable', 'numeric', 'min:0'],
                'is_apartment' => ['required', 'boolean'],
                'house_number' => ['nullable', 'string', 'max:80'],
                'building_name' => [Rule::excludeIf(! $apartment), 'nullable', 'string', 'max:120'],
                'floor_number' => [Rule::excludeIf(! $apartment), 'nullable', 'string', 'max:30'],
                'unit_number' => [Rule::excludeIf(! $apartment), Rule::requiredIf($apartment), 'string', 'max:80'],
                'room_number' => [Rule::excludeIf(! $apartment), 'nullable', 'string', 'max:80'],
                'landmark' => ['nullable', 'string', 'max:255'],
            ], ['unit_number.required' => 'Enter your apartment unit number.']);
            $addresses = app(HouseholdSetupAddress::class);
            $area = $addresses->resolve($householdId, (float) $validated['latitude'], (float) $validated['longitude']);
            $validated['address_label'] = mb_substr($addresses->save($householdId, $area, $validated), 0, 255);
            $now = now();
            $locationData = $this->support->filterColumns('geotagged_locations', [
                'household_id' => $householdId, 'latitude' => $validated['latitude'], 'longitude' => $validated['longitude'],
                'location_label' => $validated['address_label'], 'accuracy_m' => $validated['accuracy_m'] ?? null,
                'geotag_source' => 'household_mobile', 'is_verified' => false,
                'created_by_user_id' => $user->user_id, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $invalidLocation = DB::table('geotagged_locations')->where('household_id', $householdId)->first();
            if ($invalidLocation) {
                unset($locationData['created_at']);
                if (Schema::hasColumn('geotagged_locations', 'deleted_at')) $locationData['deleted_at'] = null;
                DB::table('geotagged_locations')->where('location_id', $invalidLocation->location_id)->update($locationData);
            } else {
                DB::table('geotagged_locations')->insert($this->support->withGeneratedKey('geotagged_locations', 'location_id', $locationData,
                    $this->support->nextId('geotagged_locations', 'location_id')));
            }
            $classification = [
                'status' => $area['source'] === 'boundary' ? 'matched' : 'registered_address',
                'sitio_id' => $area['sitio_id'], 'purok_id' => $area['purok_id'],
            ];
            if (Schema::hasTable('household_geographic_classifications')) {
                DB::table('household_geographic_classifications')->updateOrInsert(['household_id' => $householdId], array_merge($classification, [
                    'latitude' => $validated['latitude'], 'longitude' => $validated['longitude'], 'updated_at' => $now, 'created_at' => $now,
                ]));
            }
            $deviceId = $this->saveDevice($householdId, $user->user_id, array_merge($device, $validated), $now);
            $this->saveTrackingLog($householdId, $deviceId, array_merge($device, $validated), 'setup_pin', $now);
            $this->support->writeAuditLog($request, 'mobile_household_setup', 'households', $householdId, $validated);
            return ['device_token_id' => $deviceId, 'reused_geotag' => false, 'geographic_classification' => $classification];
        }, 3);
        if (! $result['reused_geotag']) $this->queueRouteRecompute($householdId);
        return response()->json(['message' => 'Household mobile setup saved.', 'data' => $result]);
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

        $validated['member_id'] = $this->support->authenticatedMemberId($user);
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

    private function saveGeotag(string $householdId, ?string $userId, array $validated, $now, ?object $confirmedSitio = null, ?int $barangayId = null, ?array $focus = null): array
    {
        $classification = app(\App\Services\Shared\HouseholdGeographicClassifier::class)->classify(
            $householdId,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $confirmedSitio ? (int) $confirmedSitio->sitio_id : null,
            $barangayId,
            $focus
        );
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
            return $classification;
        }

        DB::table('geotagged_locations')->insert($this->support->withGeneratedKey(
            'geotagged_locations',
            'location_id',
            $data,
            $this->support->nextId('geotagged_locations', 'location_id')
        ));
        return $classification;
    }

    private function queueRouteRecompute(string $householdId): void
    {
        if (Schema::hasTable('cached_routes')) {
            RecomputeRoutesForHousehold::dispatch($householdId)->afterCommit();
        }
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

