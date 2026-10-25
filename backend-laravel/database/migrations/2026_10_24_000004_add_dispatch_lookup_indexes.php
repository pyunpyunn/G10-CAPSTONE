<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'member_vulnerable_groups' => ['dispatch_vulnerable_member' => ['member_id']],
        'responders' => [
            'dispatch_responder_user' => ['user_id'],
            'dispatch_responder_id' => ['responder_id'],
            'dispatch_team_availability' => ['team_id','is_deployed','duty_status'],
        ],
        'sitios' => ['dispatch_sitio_barangay' => ['barangay_id','sitio_id']],
        'puroks' => ['dispatch_purok_sitio' => ['sitio_id','purok_id']],
        'disaster_events' => ['dispatch_event_active' => ['deleted_at','ended_at','started_at']],
    ];
    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) continue;
            foreach ($indexes as $name => $fields) {
                if (array_diff($fields, Schema::getColumnListing($table))) continue;
                if (collect(Schema::getIndexes($table))->contains(fn($index)=>array_slice($index['columns'],0,count($fields))===$fields)) continue;
                Schema::table($table,fn(Blueprint $blueprint)=>$blueprint->index($fields,$name));
            }
        }
    }
    public function down(): void
    {
        foreach (self::INDEXES as $table=>$indexes) foreach(array_keys($indexes) as $name) {
            if(Schema::hasTable($table)&&Schema::hasIndex($table,$name)) Schema::table($table,fn(Blueprint $blueprint)=>$blueprint->dropIndex($name));
        }
    }
};
