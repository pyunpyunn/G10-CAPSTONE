<?php

namespace Tests\Feature;

use App\Models\User;
use App\Queries\MappingQuery;
use App\Queries\HouseholdPurokQuery;
use App\Jobs\RefreshWeatherSnapshot;
use App\Jobs\DeliverDisasterBroadcast;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticatedWorkspaceContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('disaster_types', function (Blueprint $table): void {
            $table->integer('type_id')->primary();
            $table->string('type_code');
            $table->string('type_name');
        });
        Schema::create('severity_levels', function (Blueprint $table): void {
            $table->integer('severity_id')->primary();
            $table->string('severity_key');
            $table->string('severity_label');
        });
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->integer('type_id');
            $table->integer('severity_level_id');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('disaster_broadcasts', function (Blueprint $table): void {
            $table->integer('broadcast_id')->primary();
            $table->string('broadcast_title')->nullable();
            $table->string('disaster_id');
            $table->string('sent_by_admin_id')->nullable();
            $table->integer('severity_id')->nullable();
            $table->string('scope_type')->nullable();
            $table->integer('target_purok_id')->nullable();
            $table->integer('target_area_id')->nullable();
            $table->text('message')->nullable();
            $table->text('allowed_statuses')->nullable();
            $table->string('channel')->nullable();
            $table->string('status')->nullable();
            $table->integer('notification_id')->nullable();
            $table->integer('weather_log_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->integer('address_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        DB::table('disaster_types')->insert(['type_id' => 1, 'type_code' => 'FLOOD', 'type_name' => 'Flood']);
        DB::table('severity_levels')->insert(['severity_id' => 1, 'severity_key' => 'high', 'severity_label' => 'High']);
        DB::table('disaster_events')->insert([
            'event_id' => 'EV-1', 'name' => 'Flood response', 'type_id' => 1,
            'severity_level_id' => 1, 'started_at' => '2026-01-01 08:00:00',
        ]);
        DB::table('households')->insert(['household_id' => 'HH-1']);
        Sanctum::actingAs(User::query()->create(['user_id' => 'ADMIN-TEST', 'role' => 'admin']));
    }

    public function test_dashboard_summary_keeps_authenticated_json_shape(): void
    {
        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.active_event.event_id', 'EV-1')
            ->assertJsonPath('data.households.total', 1)
            ->assertJsonStructure(['data' => ['barangay_profile', 'active_event', 'households']]);
    }

    public function test_dashboard_full_workspace_keeps_authenticated_json_shape(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_event.event_id', 'EV-1')
            ->assertJsonStructure(['data' => ['active_event', 'households']]);
    }

    public function test_broadcast_index_and_missing_event_keep_status_contracts(): void
    {
        $this->getJson('/api/v1/disaster-events')
            ->assertOk()
            ->assertJsonPath('data.current_event.event_id', 'EV-1');
        $this->getJson('/api/v1/disaster-events/EV-MISSING')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Disaster event record was not found.']);
    }

    public function test_mapping_overview_keeps_marker_layer_keys(): void
    {
        $this->getJson('/api/v1/map/overview')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'active_event', 'summary', 'filters', 'households',
                'evacuation_sites', 'rescue_teams', 'dispatch_routes', 'map_rules',
            ]]);
    }

    public function test_profile_workspace_keeps_authenticated_json_shape(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary();
            $table->string('role_key');
            $table->string('role_name');
        });
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->string('tokenable_id');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user.user_id', 'ADMIN-TEST')
            ->assertJsonStructure(['data' => ['user', 'summary', 'identity', 'permissions',
                'activity', 'barangay_profile']]);
    }

    public function test_profile_update_rolls_back_when_audit_write_fails(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('assigned_center_id')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('audit_log_id');
            $table->string('user_id')->nullable();
            $table->string('role_key')->nullable();
            $table->string('module');
            $table->string('action');
            $table->string('reference_table');
            $table->string('reference_id');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        DB::statement("CREATE TRIGGER fail_profile_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(FAIL, 'audit unavailable'); END");

        $this->patchJson('/api/v1/profile', ['first_name' => 'Changed', 'last_name' => 'Name'])
            ->assertStatus(500);

        $this->assertNull(DB::table('users')->where('user_id', 'ADMIN-TEST')->value('first_name'));
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_purok_filters_follow_active_household_addresses_and_catalog_fallback(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->integer('purok_id')->nullable();
            $table->string('purok_sitio')->nullable();
        });
        Schema::create('puroks', function (Blueprint $table): void {
            $table->integer('purok_id')->primary();
            $table->string('purok_name');
        });
        DB::table('puroks')->insert([
            ['purok_id' => 1, 'purok_name' => 'Catalog One'],
            ['purok_id' => 2, 'purok_name' => 'Catalog Two'],
            ['purok_id' => 3, 'purok_name' => 'Unused Purok'],
        ]);
        DB::table('addresses')->insert([
            ['address_id' => 1, 'purok_id' => 1, 'purok_sitio' => 'Address Label'],
            ['address_id' => 2, 'purok_id' => 2, 'purok_sitio' => ''],
        ]);
        DB::table('households')->where('household_id', 'HH-1')->update(['address_id' => 1]);
        DB::table('households')->insert(['household_id' => 'HH-2', 'address_id' => 2]);
        DB::table('households')->insert(['household_id' => 'HH-DELETED', 'address_id' => 2, 'deleted_at' => now()]);

        $this->assertSame(['Address Label', 'Catalog Two'], app(HouseholdPurokQuery::class)->names());
        $this->assertSame(['Address Label', 'Catalog Two'], app(MappingQuery::class)->getPuroks());
    }

    public function test_archive_disaster_events_keeps_paginated_json_contract(): void
    {
        Schema::create('weather_logs', function (Blueprint $table): void {
            $table->integer('weather_log_id')->primary();
            $table->string('disaster_id');
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->string('household_id');
            $table->string('disaster_id');
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->string('purok_sitio')->nullable();
        });
        Schema::create('incident_archives', function (Blueprint $table): void {
            $table->integer('archive_id')->primary();
            $table->string('disaster_id')->nullable();
            $table->text('archive_note')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
        $this->getJson('/api/v1/archive/disaster-events')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category', 'disaster-events')
            ->assertJsonStructure(['data' => ['records' => ['data', 'current_page', 'per_page', 'total'], 'filters']]);
    }

    public function test_weather_refresh_is_queued_and_keeps_a_readable_response(): void
    {
        $this->getJson('/api/v1/weather')->assertOk()
            ->assertJsonStructure(['data' => ['active_event', 'latest_snapshot', 'logs', 'location']]);
        $this->getJson('/api/v1/disaster-events/EV-MISSING/weather-logs')
            ->assertNotFound()->assertExactJson(['message' => 'Disaster event record was not found.']);
        Queue::fake();
        $this->postJson('/api/v1/weather/refresh')
            ->assertStatus(202)
            ->assertJsonStructure(['message', 'data' => ['active_event', 'latest_snapshot', 'logs', 'location']]);
        Queue::assertPushedOn('operations', RefreshWeatherSnapshot::class);
    }

    public function test_broadcast_save_queues_delivery_without_calling_providers(): void
    {
        Queue::fake();
        $this->postJson('/api/v1/disaster-events/EV-1/broadcasts', [
            'broadcast_title' => 'Flood warning',
            'message' => 'Please prepare for rising water.',
            'scope_type' => 'barangay_wide',
            'allowed_statuses' => ['safe', 'unsafe', 'evacuated', 'unchecked'],
        ])->assertCreated()
            ->assertJsonPath('data.push_delivery.status', 'queued')
            ->assertJsonPath('data.sms_delivery.status', 'queued');

        Queue::assertPushedOn('broadcasts', DeliverDisasterBroadcast::class);
        $this->assertDatabaseCount('disaster_broadcasts', 1);
    }

    public function test_dashboard_clears_ended_event_references_without_loading_event_ids(): void
    {
        Schema::create('evacuation_centers', function (Blueprint $table): void {
            $table->integer('evacuation_center_id')->primary();
            $table->string('current_event_id')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('disaster_events')->insert([
            'event_id' => 'EV-CLOSED', 'name' => 'Past response', 'type_id' => 1,
            'severity_level_id' => 1, 'started_at' => '2025-01-01 08:00:00',
            'ended_at' => '2025-01-02 08:00:00',
        ]);
        DB::table('evacuation_centers')->insert([
            ['evacuation_center_id' => 1, 'current_event_id' => 'EV-CLOSED'],
            ['evacuation_center_id' => 2, 'current_event_id' => 'EV-1'],
        ]);

        $this->getJson('/api/v1/dashboard/summary')->assertOk();

        $this->assertDatabaseHas('evacuation_centers', ['evacuation_center_id' => 1, 'current_event_id' => null]);
        $this->assertDatabaseHas('evacuation_centers', ['evacuation_center_id' => 2, 'current_event_id' => 'EV-1']);
    }

    public function test_broadcast_workspace_keeps_authenticated_json_shape(): void
    {
        $this->getJson('/api/v1/disaster-events/EV-1')
            ->assertOk()
            ->assertJsonPath('data.current_event.event_id', 'EV-1')
            ->assertJsonPath('data.current_event.status_sent_count', 0)
            ->assertJsonStructure(['data' => ['current_event', 'active_event', 'events', 'broadcasts', 'disaster_types', 'severity_levels', 'puroks', 'status_options']]);
    }

    public function test_mapping_workspace_keeps_authenticated_json_shape(): void
    {
        $this->getJson('/api/v1/map/workspace')
            ->assertOk()
            ->assertJsonPath('data.active_event.event_id', 'EV-1')
            ->assertJsonPath('data.summary.no_verified_geotag', 1)
            ->assertJsonStructure(['data' => ['active_event', 'barangay', 'summary', 'filters', 'map_rules']]);
    }

    public function test_mapping_member_status_fallback_uses_one_query_for_multiple_households(): void
    {
        Schema::table('households', function (Blueprint $table): void {
            $table->string('household_code')->nullable();
            $table->string('household_name')->nullable();
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->integer('purok_id')->nullable();
        });
        Schema::create('puroks', function (Blueprint $table): void {
            $table->integer('purok_id')->primary();
            $table->string('purok_name');
        });
        Schema::create('geotagged_locations', function (Blueprint $table): void {
            $table->integer('location_id')->primary();
            $table->string('household_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('initial_status_id')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status_key');
            $table->string('status_label');
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->integer('member_id')->primary();
            $table->string('household_id');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('member_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status_key');
        });
        Schema::create('member_disaster_statuses', function (Blueprint $table): void {
            $table->integer('member_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('status_id');
        });
        DB::table('households')->insert(['household_id' => 'HH-2', 'household_name' => 'Second']);
        DB::table('geotagged_locations')->insert([
            ['location_id' => 1, 'household_id' => 'HH-1', 'latitude' => 10.1, 'longitude' => 123.1],
            ['location_id' => 2, 'household_id' => 'HH-2', 'latitude' => 10.2, 'longitude' => 123.2],
        ]);
        DB::table('member_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe'],
            ['status_id' => 2, 'status_key' => 'needs_help'],
        ]);
        DB::table('household_members')->insert([
            ['member_id' => 1, 'household_id' => 'HH-1'],
            ['member_id' => 2, 'household_id' => 'HH-2'],
        ]);
        DB::table('member_disaster_statuses')->insert([
            ['member_id' => 1, 'household_id' => 'HH-1', 'disaster_id' => 'EV-1', 'status_id' => 1],
            ['member_id' => 2, 'household_id' => 'HH-2', 'disaster_id' => 'EV-1', 'status_id' => 2],
        ]);

        $fallbackReads = 0;
        DB::listen(function ($query) use (&$fallbackReads): void {
            if (str_contains(strtolower($query->sql), 'from "member_disaster_statuses"')) $fallbackReads++;
        });
        $points = app(MappingQuery::class)->getHouseholdGeotags(Request::create('/map'), 'EV-1');

        $this->assertCount(2, $points);
        $this->assertSame(['safe', 'unsafe'], array_column($points, 'status_key'));
        $this->assertSame(1, $fallbackReads);
    }
}
