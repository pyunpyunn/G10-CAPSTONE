<?php

namespace App\Services\Mobile;

use App\Actions\UpdateHouseholdStatus;
use App\Models\StatusReminder;
use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class HouseholdMobileStatusWriter
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $readQuery,
        private HouseholdMobilePresenter $presenter,
    ) {}

    public function saveLatestDisasterStatus(string $eventId, string $householdId, int $statusId, array $validated, ?string $userId, ?int $deviceId, $now): void
    {
        app(UpdateHouseholdStatus::class)->apply(
            $eventId,
            $householdId,
            $statusId,
            $validated['status_key'],
            $validated['status_source'] ?? 'household_mobile',
            $validated['notes'] ?? null,
            $userId,
            $deviceId,
            $validated,
        );
    }

    public function saveLatestHouseholdStatusFromMemberStatuses(string $eventId, string $householdId, array $validated, ?string $userId, ?int $deviceId, $now): void
    {
        $statusKey = $this->householdRollupStatusKey($householdId, $eventId);
        $status = $this->readQuery->resolveStatus($statusKey);

        if (! $status) {
            return;
        }

        $notes = json_encode([
            'report_type' => 'household_member_rollup',
            'mobile_status_key' => $statusKey,
            'mobile_status_label' => $this->presenter->mobileStatusLabel($statusKey, null),
            'user_notes' => $this->householdRollupNote($statusKey),
            'latest_member_status_key' => $validated['status_key'],
        ], JSON_UNESCAPED_SLASHES);

        $this->saveLatestDisasterStatus($eventId, $householdId, $status['status_id'], array_merge($validated, [
            'status_key' => $statusKey,
            'status_source' => 'household_member_mobile',
            'notes' => $notes,
        ]), $userId, $deviceId, $now);
    }

    public function saveMemberDisasterStatus(string $eventId, string $householdId, string $memberId, array $validated, ?string $userId, ?int $deviceId, $now): void
    {
        if (! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
            return;
        }

        $statusId = DB::table('member_statuses')
            ->where('status_key', $validated['status_key'])
            ->where('is_active', true)
            ->value('status_id');

        if (! $statusId) {
            $statusId = DB::table('member_statuses')
                ->whereIn('status_key', $validated['status_key'] === 'needs_help'
                    ? ['needs_assistance', 'unsafe', 'trapped']
                    : [$validated['status_key']])
                ->where('is_active', true)
                ->orderBy('status_id')
                ->value('status_id');
        }

        if (! $statusId) {
            return;
        }

        $payload = [
            'disaster_id' => $eventId,
            'household_id' => $householdId,
            'member_id' => $memberId,
            'status_id' => $statusId,
            'report_source' => 'self',
            'reported_by_user_id' => $userId,
            'device_token_id' => $deviceId,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_label' => $validated['location_label'] ?? null,
            'location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'reported_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('member_disaster_statuses')->updateOrInsert(
            ['disaster_id' => $eventId, 'member_id' => $memberId],
            $payload
        );
        StatusReminder::query()->where('event_id', $eventId)->where('member_id', $memberId)
            ->whereIn('status', ['pending', 'sending'])
            ->update(['status' => 'cancelled', 'stopped_at' => $now]);
    }

    private function householdRollupStatusKey(string $householdId, string $eventId): string
    {
        if (! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
            return 'unknown';
        }

        $memberStatuses = DB::table('member_disaster_statuses as mds')
            ->join('household_members as hm', 'hm.member_id', '=', 'mds.member_id')
            ->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
            ->where('mds.disaster_id', $eventId)
            ->where('mds.household_id', $householdId)
            ->whereNull('hm.deleted_at')
            ->pluck('ms.status_key');

        $unsafeKeys = [
            'unsafe',
            'needs_help',
            'needs_assistance',
            'injured',
            'trapped',
            'missing',
            'unreachable',
            'deceased',
        ];

        if ($memberStatuses->intersect($unsafeKeys)->isNotEmpty()) {
            return 'unsafe';
        }

        if ($memberStatuses->contains(fn (string $status): bool => in_array($status, ['evacuated', 'relocated'], true))) {
            return 'evacuated';
        }

        if ($memberStatuses->isNotEmpty() && $memberStatuses->every(fn (string $status): bool => in_array($status, ['safe', 'safe_at_home', 'active', 'returned'], true))) {
            return 'safe';
        }

        return 'unknown';
    }

    private function householdRollupNote(string $statusKey): string
    {
        if (in_array($statusKey, ['unsafe', 'needs_help'], true)) {
            return 'At least one family member needs checking or rescue.';
        }

        if ($statusKey === 'evacuated') {
            return 'Latest family member reports include evacuation.';
        }

        return 'Latest family member reports are safe.';
    }

    public function saveLatestDeviceForStatus(string $householdId, ?int $deviceId, array $validated, $now): void
    {
        if (! $deviceId || ! Schema::hasTable('device_tokens')) {
            return;
        }

        DB::table('device_tokens')
            ->where('id', $deviceId)
            ->where('household_id', $householdId)
            ->update($this->support->filterColumns('device_tokens', [
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

    public function deviceIdForUuid(string $householdId, ?string $deviceUuid): ?int
    {
        if (! $deviceUuid || ! Schema::hasTable('device_tokens')) {
            return null;
        }

        return DB::table('device_tokens')
            ->where('household_id', $householdId)
            ->where('device_uuid', $deviceUuid)
            ->value('id');
    }
}







