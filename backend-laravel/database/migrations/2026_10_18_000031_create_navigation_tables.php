<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foreign-key strings must match the operational parent collation.
        // Restored MySQL schemas may use a different collation from Laravel's default.
        $householdCollation = collect(Schema::getColumns('households'))->firstWhere('name', 'household_id')['collation'] ?? null;
        $centerCollation = collect(Schema::getColumns('evacuation_centers'))->firstWhere('name', 'evacuation_center_id')['collation'] ?? null;
        $userCollation = collect(Schema::getColumns('users'))->firstWhere('name', 'user_id')['collation'] ?? null;
<<<<<<< HEAD
        foreach (['base_name' => fn ($t) => $t->string('base_name', 120)->nullable(),
            'base_latitude' => fn ($t) => $t->decimal('base_latitude', 10, 7)->nullable(),
            'base_longitude' => fn ($t) => $t->decimal('base_longitude', 10, 7)->nullable()] as $column => $add) {
            if (! Schema::hasColumn('rescue_teams', $column)) Schema::table('rescue_teams', $add);
=======
        if (! Schema::hasTable('team_bases')) {
            Schema::create('team_bases', function (Blueprint $table): void {
                $table->integer('team_id')->primary();
                $table->string('name', 120);
                $table->decimal('lat', 10, 7);
                $table->decimal('lng', 10, 7);
                $table->foreign('team_id')->references('team_id')->on('rescue_teams')->cascadeOnDelete();
            });
>>>>>>> sub-main
        }

        if (! Schema::hasTable('cached_routes')) {
            Schema::create('cached_routes', function (Blueprint $table) use ($householdCollation, $centerCollation): void {
                $table->bigIncrements('id');
                $table->integer('team_id');
                $table->string('household_id', 255)->nullable()->collation($householdCollation);
                $table->string('evac_center_id', 255)->nullable()->collation($centerCollation);
                $table->string('target_key', 300);
                $table->enum('profile', ['auto', 'pedestrian']);
                $table->longText('polyline6')->nullable();
                $table->json('steps')->nullable();
                $table->unsignedInteger('distance_m')->nullable();
                $table->unsignedInteger('duration_s')->nullable();
                $table->enum('status', ['ready', 'failed'])->default('failed');
                $table->string('failure_reason', 255)->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();
                $table->foreign('team_id')->references('team_id')->on('rescue_teams')->cascadeOnDelete();
                $table->foreign('household_id')->references('household_id')->on('households')->cascadeOnDelete();
                $table->foreign('evac_center_id')->references('evacuation_center_id')->on('evacuation_centers')->cascadeOnDelete();
                $table->unique(['team_id', 'target_key', 'profile'], 'cached_routes_team_target_profile_unique');
                $table->index(['team_id', 'status', 'profile'], 'cached_routes_team_status_profile_index');
            });
        }

<<<<<<< HEAD
        foreach (['user_id' => fn ($t) => $t->string('user_id')->nullable(),
            'team_id' => fn ($t) => $t->integer('team_id')->nullable(),
            'device_uuid' => fn ($t) => $t->string('device_uuid', 150)->nullable()->index(),
            'last_sync_at' => fn ($t) => $t->timestamp('last_sync_at')->nullable()] as $column => $add) {
            if (! Schema::hasColumn('device_tokens', $column)) Schema::table('device_tokens', $add);
        }

        Schema::create('dispatch_trips', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('dispatch_id');
            $table->integer('team_id');
            $table->bigInteger('device_id');
            $table->enum('profile', ['auto', 'pedestrian']);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('planned_distance_m')->nullable();
            $table->unsignedInteger('moving_s')->default(0);
            $table->unsignedInteger('stopped_s')->default(0);
            $table->decimal('avg_speed_ms', 8, 3)->nullable();
            $table->enum('status', ['recording', 'completed', 'abandoned'])->default('recording');
            $table->timestamps();
            $table->foreign('dispatch_id')->references('assignment_id')->on('responder_assignments');
            $table->foreign('team_id')->references('team_id')->on('rescue_teams');
            $table->foreign('device_id')->references('id')->on('device_tokens');
            $table->index(['dispatch_id', 'started_at'], 'dispatch_trips_dispatch_started_index');
            $table->index(['team_id', 'started_at'], 'dispatch_trips_team_started_index');
        });
=======
        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function (Blueprint $table) use ($userCollation): void {
                $table->bigIncrements('id');
                $table->string('user_id', 255)->collation($userCollation);
                $table->integer('team_id');
                $table->string('device_uuid', 150)->unique();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('user_id')->on('users');
                $table->foreign('team_id')->references('team_id')->on('rescue_teams');
                $table->index(['team_id', 'user_id'], 'devices_team_user_index');
            });
        }

        if (! Schema::hasTable('dispatch_trips')) {
            Schema::create('dispatch_trips', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('dispatch_id');
                $table->integer('team_id');
                $table->unsignedBigInteger('device_id');
                $table->enum('profile', ['auto', 'pedestrian']);
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->unsignedInteger('distance_m')->nullable();
                $table->unsignedInteger('planned_distance_m')->nullable();
                $table->unsignedInteger('moving_s')->default(0);
                $table->unsignedInteger('stopped_s')->default(0);
                $table->decimal('avg_speed_ms', 8, 3)->nullable();
                $table->enum('status', ['recording', 'completed', 'abandoned'])->default('recording');
                $table->timestamps();
                $table->foreign('dispatch_id')->references('assignment_id')->on('responder_assignments');
                $table->foreign('team_id')->references('team_id')->on('rescue_teams');
                $table->foreign('device_id')->references('id')->on('devices');
                $table->index(['dispatch_id', 'started_at'], 'dispatch_trips_dispatch_started_index');
                $table->index(['team_id', 'started_at'], 'dispatch_trips_team_started_index');
            });
        }
>>>>>>> sub-main

        if (! Schema::hasTable('trip_points')) {
            Schema::create('trip_points', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('trip_id');
                $table->decimal('lat', 10, 7);
                $table->decimal('lng', 10, 7);
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->decimal('speed', 8, 3)->nullable();
                $table->decimal('heading', 6, 2)->nullable();
                $table->dateTime('recorded_at', 6);
                $table->foreign('trip_id')->references('id')->on('dispatch_trips')->cascadeOnDelete();
                $table->index(['trip_id', 'recorded_at'], 'trip_points_trip_recorded_at_index');
            });
        }

<<<<<<< HEAD
        Schema::create('sync_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('client_event_id')->unique();
            $table->bigInteger('device_id');
            $table->string('type', 40);
            $table->enum('status', ['received', 'processed', 'failed'])->default('received');
            $table->string('error_message', 255)->nullable();
            $table->timestamp('received_at');
            $table->foreign('device_id')->references('id')->on('device_tokens')->cascadeOnDelete();
            $table->index(['device_id', 'received_at'], 'sync_events_device_received_at_index');
        });
=======
        if (! Schema::hasTable('sync_events')) {
            Schema::create('sync_events', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('client_event_id')->unique();
                $table->unsignedBigInteger('device_id');
                $table->string('type', 40);
                $table->enum('status', ['received', 'processed', 'failed'])->default('received');
                $table->string('error_message', 255)->nullable();
                $table->timestamp('received_at');
                $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
                $table->index(['device_id', 'received_at'], 'sync_events_device_received_at_index');
            });
        }
>>>>>>> sub-main

        if (! Schema::hasTable('offline_packs')) {
            Schema::create('offline_packs', function (Blueprint $table): void {
                $table->string('version', 64)->primary();
                $table->json('bbox');
                $table->string('style_url', 500);
                $table->unsignedBigInteger('size_bytes');
                $table->char('checksum', 64);
                $table->boolean('is_active')->default(false);
                $table->timestamps();
                $table->index(['is_active', 'version'], 'offline_packs_active_version_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_packs');
        Schema::dropIfExists('sync_events');
        Schema::dropIfExists('trip_points');
        Schema::dropIfExists('dispatch_trips');
        Schema::dropIfExists('cached_routes');
    }
};
