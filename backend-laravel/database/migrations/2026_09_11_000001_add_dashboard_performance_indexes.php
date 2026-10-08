<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_disasters')) {
            Schema::table('household_disasters', function (Blueprint $table) {
                $table->index(['disaster_id', 'current_status_id'], 'idx_hd_disaster_status');
            });
        }

        if (Schema::hasTable('responder_assignments')) {
            Schema::table('responder_assignments', function (Blueprint $table) {
                $table->index(['disaster_id', 'status'], 'idx_ra_disaster_status');
                $table->index('assigned_at', 'idx_ra_assigned_at');
            });
        }

        if (Schema::hasTable('weather_logs')) {
            Schema::table('weather_logs', function (Blueprint $table) {
                $table->index(['disaster_id', 'observed_at'], 'idx_wl_disaster_observed');
            });
        }

        if (Schema::hasTable('resource_requests')) {
            Schema::table('resource_requests', function (Blueprint $table) {
                $table->index('validation_status', 'idx_rr_validation_status');
                $table->index('created_at', 'idx_rr_created_at');
            });
        }

        if (Schema::hasTable('household_status_logs')) {
            Schema::table('household_status_logs', function (Blueprint $table) {
                $table->index(['disaster_id', 'submitted_at'], 'idx_hsl_disaster_submitted');
            });
        }

        if (Schema::hasTable('evacuation_centers')) {
            Schema::table('evacuation_centers', function (Blueprint $table) {
                $table->index('current_event_id', 'idx_ec_current_event');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('household_disasters')) {
            Schema::table('household_disasters', function (Blueprint $table) {
                $table->dropIndex('idx_hd_disaster_status');
            });
        }

        if (Schema::hasTable('responder_assignments')) {
            Schema::table('responder_assignments', function (Blueprint $table) {
                $table->dropIndex('idx_ra_disaster_status');
                $table->dropIndex('idx_ra_assigned_at');
            });
        }

        if (Schema::hasTable('weather_logs')) {
            Schema::table('weather_logs', function (Blueprint $table) {
                $table->dropIndex('idx_wl_disaster_observed');
            });
        }

        if (Schema::hasTable('resource_requests')) {
            Schema::table('resource_requests', function (Blueprint $table) {
                $table->dropIndex('idx_rr_validation_status');
                $table->dropIndex('idx_rr_created_at');
            });
        }

        if (Schema::hasTable('household_status_logs')) {
            Schema::table('household_status_logs', function (Blueprint $table) {
                $table->dropIndex('idx_hsl_disaster_submitted');
            });
        }

        if (Schema::hasTable('evacuation_centers')) {
            Schema::table('evacuation_centers', function (Blueprint $table) {
                $table->dropIndex('idx_ec_current_event');
            });
        }
    }
};
