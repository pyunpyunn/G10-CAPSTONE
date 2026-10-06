<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use LogicException;

class OperationalSequence
{
    public function nextEvacuationId(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Evacuation IDs must be allocated inside a transaction.');
        }

        $key = 'formatted_evacuation_records';
        if (! DB::table('operational_sequences')->where('sequence_key', $key)->exists()) {
            $latest = (string) (DB::table('evacuation_records')->orderByDesc('evacuation_id')->value('evacuation_id') ?? '');
            if ($latest === '') {
                $prefix = '';
                $next = 1;
            } elseif (preg_match('/(\d+)$/', $latest, $matches)) {
                $prefix = substr($latest, 0, -strlen($matches[1]));
                $next = (int) $matches[1] + 1;
            } else {
                $prefix = $latest.'-';
                $next = 1;
            }

            while (DB::table('evacuation_records')->where('evacuation_id', $prefix.$next)->exists()) {
                $next++;
            }

            DB::table('operational_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'sequence_prefix' => $prefix,
                'next_value' => $next,
            ]);
        }

        $row = DB::table('operational_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
        $next = (int) $row->next_value;
        while (DB::table('evacuation_records')->where('evacuation_id', (string) $row->sequence_prefix.$next)->exists()) {
            $next++;
        }
        $id = (string) $row->sequence_prefix.$next;
        DB::table('operational_sequences')->where('sequence_key', $key)->update(['next_value' => $next + 1]);

        return $id;
    }

    private const NUMERIC_IDS = [
        'device_tokens' => 'id',
        'device_tracking_logs' => 'tracking_id',
        'disaster_broadcasts' => 'broadcast_id',
        'geotagged_locations' => 'location_id',
        'household_disasters' => 'household_disaster_id',
        'household_status_logs' => 'status_log_id',
        'rescue_teams' => 'team_id',
        'responders' => 'responder_id',
        'responder_assignments' => 'assignment_id',
        'responder_check_ins' => 'check_in_id',
        'responder_communication_logs' => 'communication_id',
        'responder_field_reports' => 'report_id',
        'responder_location_logs' => 'log_id',
        'responder_routes' => 'route_id',
        'route_coordinates' => 'coordinate_id',
        'situation_reports' => 'sit_rep_id',
    ];

    public function nextNumericId(string $table, string $column): int
    {
        if ((self::NUMERIC_IDS[$table] ?? null) !== $column) {
            throw new \InvalidArgumentException('Unsupported operational sequence.');
        }

        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): int => $this->nextNumericId($table, $column), 3);
        }

        $key = 'numeric_'.$table;
        if (! DB::table('operational_sequences')->where('sequence_key', $key)->exists()) {
            DB::table('operational_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'next_value' => ((int) DB::table($table)->max($column)) + 1,
            ]);
        }

        $row = DB::table('operational_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
        $id = (int) $row->next_value;
        DB::table('operational_sequences')->where('sequence_key', $key)->update(['next_value' => $id + 1]);

        return $id;
    }

    public function nextTrackingReference(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Tracking references must be allocated inside a transaction.');
        }

        $date = now()->format('Ymd');
        $key = 'tracking_ref_'.$date;
        if (! DB::table('operational_sequences')->where('sequence_key', $key)->exists()) {
            DB::table('operational_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'next_value' => DB::table('resource_requests')
                    ->whereDate('released_for_tracking_at', now()->toDateString())
                    ->count() + 1,
            ]);
        }

        $row = DB::table('operational_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
        $number = (int) $row->next_value;
        DB::table('operational_sequences')->where('sequence_key', $key)->update(['next_value' => $number + 1]);

        return 'TA-'.$date.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function nextIncidentArchiveId(): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Archive IDs must be allocated inside a transaction.');
        }

        $row = DB::table('operational_sequences')
            ->where('sequence_key', 'incident_archives')
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new LogicException('The incident archive sequence is not initialized. Run migrations.');
        }

        $id = (int) $row->next_value;
        DB::table('operational_sequences')
            ->where('sequence_key', 'incident_archives')
            ->update(['next_value' => $id + 1]);

        return $id;
    }
}
