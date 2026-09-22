<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affected_areas')) {
            Schema::create('affected_areas', function (Blueprint $table) {
                $table->id('affected_area_id');
                $table->string('area_name', 150)->nullable();
                $table->string('disaster_id', 255)->nullable();
                $table->string('confirmed_by_admin_id', 255)->nullable();
                $table->integer('severity_id')->nullable();
                $table->integer('purok_id')->nullable();
                $table->integer('sitio_id')->nullable();
                $table->integer('barangay_id')->nullable();
                $table->text('description')->nullable();
                $table->string('hazard_type', 80)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->json('boundary_geojson')->nullable();
                $table->string('status', 30)->default('active');
                $table->boolean('confirmed_by_hq')->default(false);
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('affected_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('affected_areas', 'area_name')) {
                $table->string('area_name', 150)->nullable()->after('affected_area_id');
            }
            if (! Schema::hasColumn('affected_areas', 'description')) {
                $table->text('description')->nullable()->after('barangay_id');
            }
            if (! Schema::hasColumn('affected_areas', 'hazard_type')) {
                $table->string('hazard_type', 80)->nullable()->after('description');
            }
            if (! Schema::hasColumn('affected_areas', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('hazard_type');
            }
            if (! Schema::hasColumn('affected_areas', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('affected_areas', 'boundary_geojson')) {
                $table->json('boundary_geojson')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('affected_areas', 'status')) {
                $table->string('status', 30)->default('active')->after('boundary_geojson');
            }
            if (! Schema::hasColumn('affected_areas', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('affected_areas')) {
            Schema::table('affected_areas', function (Blueprint $table) {
                foreach (['area_name', 'description', 'hazard_type', 'latitude', 'longitude', 'boundary_geojson', 'status'] as $column) {
                    if (Schema::hasColumn('affected_areas', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
