<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDisasterStatusSeeder extends Seeder
{
    public function run(): void
    {
        $eventId = 'DEV-TEST-FLOOD-20260602';
        $householdId = 'HH-2024035501';
        $householdUserId = 'USR-HH-2024035501';
        $adminUserId = 'USR-HQ-2024035500';
        $now = now();

        $floodTypeId = DB::table('disaster_types')->where('type_code', 'FL')->value('type_id');
        $highSeverityId = DB::table('severity_levels')->where('severity_key', 'high')->value('severity_id');
        $notEvacuatedStatusId = DB::table('household_statuses')->where('status_key', 'not_evacuated')->value('status_id');
        $evacuatedStatusId = DB::table('household_statuses')->where('status_key', 'evacuated')->value('status_id');

        if (! $floodTypeId || ! $highSeverityId || ! $notEvacuatedStatusId || ! $evacuatedStatusId) {
            $this->command?->warn('Sample disaster/status data was not seeded because lookup rows are missing.');

            return;
        }

        if (! DB::table('households')->where('household_id', $householdId)->exists()) {
            $this->command?->warn('Sample disaster/status data was not seeded because the temporary household is missing.');

            return;
        }

        DB::transaction(function () use (
            $eventId,
            $householdId,
            $householdUserId,
            $adminUserId,
            $now,
            $floodTypeId,
            $highSeverityId,
            $notEvacuatedStatusId,
            $evacuatedStatusId
        ): void {
            if (DB::table('disaster_events')->where('event_id', $eventId)->exists()) {
                DB::table('disaster_events')
                    ->where('event_id', $eventId)
                    ->update([
                        'name' => 'DEV TEST - Active Flood Monitoring',
                        'type_id' => $floodTypeId,
                        'severity_level_id' => $highSeverityId,
                        'ended_at' => null,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
            } else {
                DB::table('disaster_events')->insert([
                    'event_id' => $eventId,
                    'name' => 'DEV TEST - Active Flood Monitoring',
                    'type_id' => $floodTypeId,
                    'severity_level_id' => $highSeverityId,
                    'started_at' => $now->copy()->subHours(2),
                    'ended_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
            }

            if (DB::table('household_disasters')->where('household_disaster_id', 2026060201)->exists()) {
                DB::table('household_disasters')
                    ->where('household_disaster_id', 2026060201)
                    ->update([
                        'household_id' => $householdId,
                        'disaster_id' => $eventId,
                        'initial_status_id' => $notEvacuatedStatusId,
                        'current_status_id' => $evacuatedStatusId,
                        'last_status_source' => 'household_mobile',
                        'last_status_notes' => 'DEV TEST ONLY: household reached the evacuation area.',
                        'last_reported_by_user_id' => $householdUserId,
                        'last_responder_id' => null,
                        'last_device_token_id' => null,
                        'last_latitude' => 14.5995120,
                        'last_longitude' => 120.9842220,
                        'last_battery_level' => 72,
                        'last_reported_at' => $now->copy()->subMinutes(10),
                        'priority_level' => 'monitor',
                        'needs_dispatch' => 0,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('household_disasters')->insert([
                    'household_disaster_id' => 2026060201,
                    'household_id' => $householdId,
                    'disaster_id' => $eventId,
                    'initial_status_id' => $notEvacuatedStatusId,
                    'current_status_id' => $evacuatedStatusId,
                    'last_status_source' => 'household_mobile',
                    'last_status_notes' => 'DEV TEST ONLY: household reached the evacuation area.',
                    'last_reported_by_user_id' => $householdUserId,
                    'last_responder_id' => null,
                    'last_device_token_id' => null,
                    'last_latitude' => 14.5995120,
                    'last_longitude' => 120.9842220,
                    'last_battery_level' => 72,
                    'last_reported_at' => $now->copy()->subMinutes(10),
                    'priority_level' => 'monitor',
                    'needs_dispatch' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->insertStatusLogIfMissing([
                'disaster_id' => $eventId,
                'household_id' => $householdId,
                'status_id' => $notEvacuatedStatusId,
                'source' => 'household_mobile',
                'submitted_by_user_id' => $householdUserId,
                'responder_id' => null,
                'device_token_id' => null,
                'latitude' => 14.6021000,
                'longitude' => 120.9821000,
                'location_label' => 'DEV TEST - Home area',
                'location_accuracy_m' => 18.50,
                'battery_level' => 82,
                'signal_strength' => 4,
                'notes' => 'DEV TEST ONLY: household reported still at home.',
                'submitted_at' => $now->copy()->subMinutes(45),
                'reviewed_by_user_id' => $adminUserId,
                'reviewed_at' => $now->copy()->subMinutes(40),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertStatusLogIfMissing([
                'disaster_id' => $eventId,
                'household_id' => $householdId,
                'status_id' => $evacuatedStatusId,
                'source' => 'household_mobile',
                'submitted_by_user_id' => $householdUserId,
                'responder_id' => null,
                'device_token_id' => null,
                'latitude' => 14.5995120,
                'longitude' => 120.9842220,
                'location_label' => 'DEV TEST - Barangay evacuation area',
                'location_accuracy_m' => 12.25,
                'battery_level' => 72,
                'signal_strength' => 4,
                'notes' => 'DEV TEST ONLY: household reached the evacuation area.',
                'submitted_at' => $now->copy()->subMinutes(10),
                'reviewed_by_user_id' => $adminUserId,
                'reviewed_at' => $now->copy()->subMinutes(8),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Keep sample status history idempotent without deleting shared DB rows.
     *
     * @param  array<string, mixed>  $data
     */
    private function insertStatusLogIfMissing(array $data): void
    {
        $alreadyExists = DB::table('household_status_logs')
            ->where('disaster_id', $data['disaster_id'])
            ->where('household_id', $data['household_id'])
            ->where('status_id', $data['status_id'])
            ->where('source', $data['source'])
            ->where('notes', $data['notes'])
            ->exists();

        if (! $alreadyExists) {
            DB::table('household_status_logs')->insert($data);
        }
    }
}
