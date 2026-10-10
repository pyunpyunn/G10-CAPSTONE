<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'household_status_logs' => [
            'rt_hsl_event_household_latest' => ['disaster_id', 'household_id', 'submitted_at', 'status_log_id'],
            'rt_hsl_field_event_latest' => ['source', 'disaster_id', 'submitted_at', 'status_log_id'],
        ],
        'member_disaster_statuses' => [
            'rt_mds_event_household_member' => ['disaster_id', 'household_id', 'member_id'],
        ],
        'responder_assignments' => [
            'rt_assignments_event_latest' => ['disaster_id', 'assigned_at', 'assignment_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (count(array_intersect($columns, Schema::getColumnListing($table))) !== count($columns)) {
                    continue;
                }
                $covered = collect(Schema::getIndexes($table))->contains(fn ($index) => array_slice($index['columns'], 0, count($columns)) === $columns);
                if (! $covered && ! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
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
