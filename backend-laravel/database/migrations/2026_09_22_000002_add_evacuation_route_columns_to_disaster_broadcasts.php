<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disaster_broadcasts')) {
            return;
        }

        Schema::table('disaster_broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('disaster_broadcasts', 'attached_evacuation_center_id')) {
                $table->string('attached_evacuation_center_id', 255)->nullable()->after('push_status');
            }
            if (! Schema::hasColumn('disaster_broadcasts', 'attached_affected_area_ids_json')) {
                $table->json('attached_affected_area_ids_json')->nullable()->after('attached_evacuation_center_id');
            }
            if (! Schema::hasColumn('disaster_broadcasts', 'evacuation_route_json')) {
                $table->json('evacuation_route_json')->nullable()->after('attached_affected_area_ids_json');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('disaster_broadcasts')) {
            Schema::table('disaster_broadcasts', function (Blueprint $table) {
                foreach (['attached_evacuation_center_id', 'attached_affected_area_ids_json', 'evacuation_route_json'] as $column) {
                    if (Schema::hasColumn('disaster_broadcasts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
