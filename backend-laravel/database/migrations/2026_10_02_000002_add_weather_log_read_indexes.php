<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('weather_logs', function (Blueprint $table): void {
            $table->index(['observed_at', 'created_at'], 'weather_logs_latest_idx');
            $table->index(['disaster_id', 'observed_at', 'created_at'], 'weather_logs_event_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::table('weather_logs', function (Blueprint $table): void {
            $table->dropIndex('weather_logs_event_latest_idx');
            $table->dropIndex('weather_logs_latest_idx');
        });
    }
};
