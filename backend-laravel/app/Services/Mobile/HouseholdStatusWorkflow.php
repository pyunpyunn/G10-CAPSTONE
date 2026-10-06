<?php

namespace App\Services\Mobile;

use App\Actions\UpdateHouseholdStatus;
use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Models\DeviceTrackingLog;
use App\Models\Household;
use App\Models\HouseholdDisaster;
use App\Models\HouseholdStatus;
use App\Models\HouseholdStatusLog;
use App\Queries\HouseholdStatusQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class HouseholdStatusWorkflow
{
    public function __construct(private HouseholdStatusQuery $query) {}

    public function storeStatusLog(Request $request, string $householdId): JsonResponse
    {
        $user = $request->user()?->load('role');
        $roleKey = $user?->role?->role_key;

        if ($roleKey === 'household_resident' && $user->household_id !== $householdId) {
            return response()->json([
                'message' => 'You can only submit a report for your own household account.',
            ], 403);
        }

        $activeEvent = $this->query->getActiveEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'A household status report can only be submitted during an active disaster event.',
            ], 409);
        }

        $household = Household::query()
            ->where('household_id', $householdId)
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first();

        if (! $household) {
            return response()->json([
                'message' => 'Household record was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'exists:household_statuses,status_id'],
            'device_token_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'needs_dispatch' => ['nullable', 'boolean'],
        ], [
            'status_id.required' => 'Please choose the household status.',
            'status_id.exists' => 'The selected household status is not valid.',
            'latitude.between' => 'Latitude must be a valid map coordinate.',
            'longitude.between' => 'Longitude must be a valid map coordinate.',
            'battery_level.between' => 'Battery level must be from 0 to 100.',
        ]);

        $deviceId = $validated['device_token_id'] ?? null;

        if ($deviceId && ! $this->query->deviceBelongsToHousehold($deviceId, $householdId)) {
            throw ValidationException::withMessages([
                'device_token_id' => ['This mobile device is not linked to the selected household.'],
            ]);
        }

        $source = $roleKey === 'rescuer' ? 'responder_field_report' : 'household_mobile';
        $now = now();

        $statusLogId = DB::transaction(function () use ($validated, $activeEvent, $householdId, $user, $source, $now, $deviceId, $request): int {
            $statusLogId = DB::table('household_status_logs')->insertGetId([
                'disaster_id' => $activeEvent->event_id,
                'household_id' => $householdId,
                'status_id' => $validated['status_id'],
                'source' => $source,
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

            $this->saveLatestHouseholdDisaster($activeEvent->event_id, $householdId, $validated, $user?->user_id, $source, $now, $deviceId);
            $this->saveLatestDeviceData($householdId, $validated, $now, $deviceId);
            $this->writeAuditLog($request, $statusLogId, $householdId, $activeEvent->event_id);

            return $statusLogId;
        });

        return response()->json([
            'message' => 'Household status report saved.',
            'data' => [
                'status_log_id' => $statusLogId,
            ],
        ], 201);
    }

    public function confirmStatus(Request $request, string $householdId): JsonResponse
    {
        if (! Schema::hasTable('household_status_logs')) {
            return response()->json([
                'message' => 'Household status logs are not available in the active database.',
            ], 503);
        }

        $log = DB::table('household_status_logs')
            ->where('household_id', $householdId)
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $log) {
            return response()->json([
                'message' => 'No household status report is available to confirm yet.',
            ], 404);
        }

        $now = now();
        $userId = $request->user()?->user_id;

        DB::table('household_status_logs')
            ->where('status_log_id', $log->status_log_id)
            ->update(array_filter([
                'reviewed_by_user_id' => Schema::hasColumn('household_status_logs', 'reviewed_by_user_id') ? $userId : null,
                'reviewed_at' => Schema::hasColumn('household_status_logs', 'reviewed_at') ? $now : null,
                'updated_at' => Schema::hasColumn('household_status_logs', 'updated_at') ? $now : null,
            ], fn ($value) => $value !== null));

        return response()->json([
            'message' => 'Latest household status report confirmed by HQ.',
            'data' => [
                'household_id' => $householdId,
                'status_log_id' => $log->status_log_id,
                'reviewed_at' => $now->format('M d, Y h:i A'),
            ],
        ]);
    }

    private function saveLatestHouseholdDisaster(string $eventId, string $householdId, array $validated, ?string $userId, string $source, Carbon $now, ?int $deviceId): void
    {
        $statusKey = HouseholdStatus::query()->where('status_id', $validated['status_id'])->value('status_key');
        app(UpdateHouseholdStatus::class)->apply(
            $eventId,
            $householdId,
            (int) $validated['status_id'],
            (string) $statusKey,
            $source,
            $validated['notes'] ?? null,
            $userId,
            $deviceId,
            $validated,
            array_key_exists('needs_dispatch', $validated) ? (bool) $validated['needs_dispatch'] : null,
        );
    }

    private function saveLatestDeviceData(string $householdId, array $validated, Carbon $now, ?int $deviceId): void
    {
        if (! $deviceId) {
            return;
        }

        DeviceToken::query()
            ->where('id', $deviceId)
            ->where('household_id', $householdId)
            ->update([
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'last_latitude' => $validated['latitude'] ?? null,
                'last_longitude' => $validated['longitude'] ?? null,
                'last_location_label' => $validated['location_label'] ?? null,
                'last_location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
                'last_location_at' => $now,
                'last_seen_at' => $now,
                'logged_at' => $now,
                'updated_at' => $now,
            ]);

        if (! array_key_exists('latitude', $validated) || ! array_key_exists('longitude', $validated)) {
            return;
        }

        $nextTrackingId = app(\App\Services\Shared\OperationalSequence::class)->nextNumericId('device_tracking_logs', 'tracking_id');

        DeviceTrackingLog::query()->create([
            'tracking_id' => $nextTrackingId,
            'device_token_id' => $deviceId,
            'household_id' => $householdId,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_label' => $validated['location_label'] ?? null,
            'accuracy_m' => $validated['location_accuracy_m'] ?? null,
            'location_source' => 'status_report',
            'is_allowed_location' => true,
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'logged_at' => $now,
        ]);
    }

    private function deviceBelongsToHousehold(int $deviceId, string $householdId): bool
    {
        return DeviceToken::query()
            ->where('id', $deviceId)
            ->where('household_id', $householdId)
            ->exists();
    }

    private function writeAuditLog(Request $request, int $statusLogId, string $householdId, string $eventId): void
    {
        $user = $request->user();

        AuditLog::query()->create([
            'user_id' => $user?->user_id,
            'role_key' => $user?->role?->role_key,
            'module' => 'household_status',
            'action' => 'create_status_report',
            'reference_table' => 'household_status_logs',
            'reference_id' => (string) $statusLogId,
            'old_values' => null,
            'new_values' => json_encode([
                'household_id' => $householdId,
                'disaster_id' => $eventId,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}







