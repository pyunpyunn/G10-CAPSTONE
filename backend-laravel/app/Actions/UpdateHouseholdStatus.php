<?php

namespace App\Actions;

use App\Jobs\SendDispatchChangePush;
use App\Models\ResponderAssignment;
use App\Services\Shared\OperationalSequence;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

/** The shared current-status and dispatch transition for every reporting channel. */
class UpdateHouseholdStatus
{
    private const ACTIVE_ASSIGNMENTS = ['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning'];

    public function apply(
        string $eventId,
        string $householdId,
        int $statusId,
        string $statusKey,
        string $source,
        ?string $notes,
        ?string $userId,
        ?int $deviceId,
        array $location = [],
        ?bool $dispatchOverride = null,
    ): void {
        DB::transaction(function () use ($eventId, $householdId, $statusId, $statusKey, $source, $notes, $userId, $deviceId, $location, $dispatchOverride): void {
            $now = now();
            $existing = DB::table('household_disasters')
                ->where('disaster_id', $eventId)->where('household_id', $householdId)
                ->lockForUpdate()->first();

            $urgent = in_array($statusKey, ['unsafe', 'needs_help', 'need_help', 'needs_assistance', 'injured', 'missing', 'not_evacuated', 'displaced'], true);
            $knownSafe = in_array($statusKey, ['safe', 'active', 'returned', 'evacuated', 'relocated'], true);
            // A confirmed safe report withdraws the live task even if an older
            // administrative override requested dispatch.
            $reportedSafe = in_array($statusKey, ['safe', 'active', 'returned'], true);
            $needsDispatch = $reportedSafe ? false : ($dispatchOverride ?? ($urgent ? true : ($knownSafe ? false : (bool) ($existing->needs_dispatch ?? false))));
            $priority = $needsDispatch ? 'urgent' : ($knownSafe ? 'monitor' : ($existing->priority_level ?? 'monitor'));

            $data = $this->columns('household_disasters', [
                'current_status_id' => $statusId,
                'last_status_source' => $source,
                'last_status_notes' => $notes,
                'last_reported_by_user_id' => $userId,
                'last_device_token_id' => $deviceId,
                'last_latitude' => $location['latitude'] ?? null,
                'last_longitude' => $location['longitude'] ?? null,
                'last_battery_level' => $location['battery_level'] ?? null,
                'last_reported_at' => $now,
                'priority_level' => $priority,
                'needs_dispatch' => $needsDispatch,
                'updated_at' => $now,
            ]);

            if ($existing) {
                DB::table('household_disasters')->where('household_disaster_id', $existing->household_disaster_id)->update($data);
            } else {
                DB::table('household_disasters')->insert($this->columns('household_disasters', array_merge($data, [
                    'household_disaster_id' => app(OperationalSequence::class)->nextNumericId('household_disasters', 'household_disaster_id'),
                    'household_id' => $householdId,
                    'disaster_id' => $eventId,
                    'initial_status_id' => $statusId,
                    'created_at' => $now,
                ])));
            }

            if ($reportedSafe) {
                $this->withdrawAssignments($eventId, $householdId, $now);
            }
        }, 3);
    }

    private function withdrawAssignments(string $eventId, string $householdId, $now): void
    {
        $assignments = ResponderAssignment::query()->where('disaster_id', $eventId)
            ->where('household_id', $householdId)->whereIn('status', self::ACTIVE_ASSIGNMENTS)
            ->orderBy('assignment_id')->lockForUpdate()->get();

        foreach ($assignments as $assignment) {
            $route = json_decode((string) $assignment->route_notes, true);
            $ids = array_values(array_unique(array_map('intval', $route['selected_responder_ids'] ?? [$assignment->responder_id])));
            $outcome = json_decode((string) $assignment->outcome_notes, true);
            $outcome = is_array($outcome) ? $outcome : [];
            $outcome['withdrawal_reason'] = 'household_reported_safe';
            $outcome['withdrawn_at'] = $now->toIso8601String();
            $assignment->update(['status' => 'cancelled', 'outcome_notes' => json_encode($outcome), 'updated_at' => $now]);

            // Only release responders who have no other live assignment.
            foreach ($ids as $id) {
                if ($id <= 0) continue;
                $busy = ResponderAssignment::query()->where('disaster_id', $eventId)
                    ->whereIn('status', self::ACTIVE_ASSIGNMENTS)
                    ->where(function ($query) use ($id): void {
                        $query->where('responder_id', $id)
                            ->orWhereJsonContains('route_notes->selected_responder_ids', $id);
                    })->exists();
                if (! $busy) {
                    DB::table('responders')->where('responder_id', $id)->update([
                        'is_deployed' => false, 'duty_status' => 'available', 'updated_at' => $now,
                    ]);
                }
            }

            if ($assignment->team_id && ! ResponderAssignment::query()->where('team_id', $assignment->team_id)
                ->where('disaster_id', $eventId)->whereIn('status', self::ACTIVE_ASSIGNMENTS)->exists()) {
                DB::table('rescue_teams')->where('team_id', $assignment->team_id)
                    ->update(['duty_status' => 'available', 'updated_at' => $now]);
            }

            SendDispatchChangePush::dispatch($ids, (int) $assignment->assignment_id,
                (string) $assignment->assignment_code, $eventId, 'household_reported_safe')
                ->onConnection('operations_outbox')->onQueue('operations')->afterCommit();
        }
    }

    private function columns(string $table, array $data): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        return array_intersect_key($data, $columns);
    }
}
