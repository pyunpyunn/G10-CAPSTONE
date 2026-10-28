<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['disaster_broadcasts' => ['broadcast_id', 'push_provider_ids_json'], 'notifications' => ['notif_id', 'onesignal_ids_json']] as $table => [$key, $list]) {
            if (! Schema::hasTable($table)) continue;
            if (! Schema::hasColumn($table, 'onesignal_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('onesignal_id', 36)->nullable());
            }
            if (! Schema::hasColumn($table, $list)) continue;
            DB::table($table)->whereNull('onesignal_id')->whereNotNull($list)->select([$key, $list])->chunkById(200, function ($rows) use ($table, $key, $list): void {
                foreach ($rows as $row) {
                    $ids = json_decode($row->$list, true);
                    if (is_array($ids) && is_string($ids[0] ?? null) && trim($ids[0]) !== '') {
                        DB::table($table)->where($key, $row->$key)->whereNull('onesignal_id')->update(['onesignal_id' => $ids[0]]);
                    }
                }
            }, $key);
        }
    }

    public function down(): void
    {
        // Preserve provider message IDs on rollback.
    }
};
