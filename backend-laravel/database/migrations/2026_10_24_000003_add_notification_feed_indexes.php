<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'audit_logs' => [
            'op_audit_user_module' => ['user_id','module','audit_log_id'],
            'op_audit_dispatch_feed' => ['reference_table','module','action','created_at'],
        ],
        'notifications' => ['op_notification_created' => ['created_at','notif_id']],
        'disaster_broadcasts' => ['op_broadcast_sent' => ['sent_at','broadcast_id']],
        'official_advisory_notifications' => ['op_advisory_created' => ['created_at']],
    ];
    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) continue;
            foreach ($indexes as $name => $fields) {
                if (array_diff($fields, Schema::getColumnListing($table))) continue;
                if (collect(Schema::getIndexes($table))->contains(fn($index) => array_slice($index['columns'],0,count($fields)) === $fields)) continue;
                Schema::table($table,fn(Blueprint $blueprint) => $blueprint->index($fields,$name));
            }
        }
    }
    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) foreach (array_keys($indexes) as $name) {
            if (Schema::hasTable($table) && Schema::hasIndex($table,$name)) Schema::table($table,fn(Blueprint $blueprint) => $blueprint->dropIndex($name));
        }
    }
};
