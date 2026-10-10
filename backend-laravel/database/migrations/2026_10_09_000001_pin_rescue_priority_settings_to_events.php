<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('disaster_events') && ! Schema::hasColumn('disaster_events', 'rescue_priority_version')) {
            Schema::table('disaster_events', function (Blueprint $table): void {
                $table->unsignedBigInteger('rescue_priority_version')->nullable();
            });
        }

        foreach (['rescue_criteria_snapshots', 'rescue_criteria_line_snapshots'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'rescue_priority_version')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->unsignedBigInteger('rescue_priority_version')->nullable();
                });
            }
        }

        if (Schema::hasTable('rescue_priority_settings') && ! Schema::hasColumn('rescue_priority_settings', 'change_reason')) {
            Schema::table('rescue_priority_settings', function (Blueprint $table): void {
                $table->string('change_reason', 255)->nullable();
            });
        }

        if (Schema::hasTable('disaster_events') && Schema::hasColumn('disaster_events', 'rescue_priority_version')
            && Schema::hasTable('rescue_priority_settings')) {
            $latestVersion = DB::table('rescue_priority_settings')->max('version');
            if ($latestVersion !== null) {
                $activeEvents = DB::table('disaster_events')->whereNull('ended_at')->get(['event_id', 'rescue_priority_version']);
                foreach ($activeEvents as $event) {
                    $version = $event->rescue_priority_version ?? $latestVersion;
                    DB::table('disaster_events')->where('event_id', $event->event_id)
                        ->whereNull('rescue_priority_version')->update(['rescue_priority_version' => $version]);
                    foreach (['rescue_criteria_snapshots', 'rescue_criteria_line_snapshots'] as $tableName) {
                        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'rescue_priority_version')) {
                            DB::table($tableName)->where('event_id', $event->event_id)
                                ->whereNull('rescue_priority_version')->update(['rescue_priority_version' => $version]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['disaster_events', 'rescue_criteria_snapshots', 'rescue_criteria_line_snapshots'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'rescue_priority_version')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('rescue_priority_version');
                });
            }
        }

        if (Schema::hasTable('rescue_priority_settings') && Schema::hasColumn('rescue_priority_settings', 'change_reason')) {
            Schema::table('rescue_priority_settings', function (Blueprint $table): void {
                $table->dropColumn('change_reason');
            });
        }
    }
};