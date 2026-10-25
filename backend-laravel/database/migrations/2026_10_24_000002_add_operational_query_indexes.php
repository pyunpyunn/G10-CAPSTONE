<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const INDEXES = [
        'households' => ['op_household_address' => ['address_id', 'household_id']],
        'addresses' => ['op_address_purok' => ['purok_id', 'address_id'], 'op_address_sitio' => ['sitio_id', 'address_id']],
        'household_members' => ['op_member_household_active' => ['household_id', 'deleted_at', 'member_id']],
        'household_status_logs' => [
            'op_status_event_latest' => ['disaster_id', 'household_id', 'submitted_at', 'status_log_id'],
            'op_field_event_latest' => ['source', 'disaster_id', 'submitted_at', 'status_log_id'],
            'op_reporter_field_latest' => ['responder_id', 'source', 'submitted_at', 'status_log_id'],
        ],
        'responder_assignments' => [
            'op_dispatch_event_status' => ['disaster_id', 'status', 'assigned_at', 'assignment_id'],
            'op_dispatch_responder_status' => ['responder_id', 'status', 'assigned_at', 'assignment_id'],
            'op_dispatch_team_event' => ['team_id', 'disaster_id', 'status'],
        ],
        'responder_location_logs' => ['op_gps_responder_latest' => ['responder_id', 'log_id'], 'op_gps_capture_latest' => ['responder_id', 'logged_at']],
        'responder_routes' => ['op_route_assignment_latest' => ['assignment_id', 'responder_id', 'created_at', 'route_id']],
        'route_coordinates' => ['op_route_coordinates' => ['route_id', 'sequence_order']],
        'responder_field_reports' => ['op_field_status_log' => ['status_log_id']],
        'device_tokens' => ['op_device_household_member' => ['household_id', 'member_id', 'is_active']],
        'notifications' => ['op_notification_user_latest' => ['user_id', 'is_read', 'created_at', 'notif_id']],
        'resource_requests' => ['op_request_source_latest' => ['request_source', 'created_at', 'request_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) continue;
            $columns = Schema::getColumnListing($table);
            foreach ($indexes as $name => $fields) {
                if (array_diff($fields, $columns)) continue;
                $covered = collect(Schema::getIndexes($table))->contains(
                    fn ($index) => array_slice($index['columns'], 0, count($fields)) === $fields
                );
                if (!$covered && !Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($fields, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
