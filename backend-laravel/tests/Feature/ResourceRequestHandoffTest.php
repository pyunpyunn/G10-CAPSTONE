<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResourceRequestHandoffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('role_id');
                $table->string('role_key');
                $table->string('role_name');
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        DB::table('roles')->updateOrInsert(['role_id' => 1], [
            'role_key' => 'super_admin',
            'role_name' => 'Super Admin',
        ]);

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->string('user_id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->integer('role_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('disaster_types')) {
            Schema::create('disaster_types', function (Blueprint $table) {
                $table->integer('type_id')->primary();
                $table->string('type_code');
                $table->string('type_name');
            });
        }

        if (! Schema::hasTable('severity_levels')) {
            Schema::create('severity_levels', function (Blueprint $table) {
                $table->integer('severity_id')->primary();
                $table->string('severity_key');
                $table->string('severity_label');
            });
        }

        if (! Schema::hasTable('disaster_events')) {
            Schema::create('disaster_events', function (Blueprint $table) {
                $table->string('event_id')->primary();
                $table->string('name');
                $table->integer('type_id')->nullable();
                $table->integer('severity_level_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('evacuation_centers')) {
            Schema::create('evacuation_centers', function (Blueprint $table) {
                $table->string('evacuation_center_id')->primary();
                $table->string('name');
                $table->string('osm_address')->nullable();
                $table->string('current_event_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('urgency_levels')) {
            Schema::create('urgency_levels', function (Blueprint $table) {
                $table->integer('urgency_id')->primary();
                $table->string('urgency_key');
                $table->string('urgency_label');
            });

            DB::table('urgency_levels')->insert([
                ['urgency_id' => 1, 'urgency_key' => 'low', 'urgency_label' => 'Low'],
                ['urgency_id' => 2, 'urgency_key' => 'medium', 'urgency_label' => 'Medium'],
                ['urgency_id' => 3, 'urgency_key' => 'high', 'urgency_label' => 'High'],
                ['urgency_id' => 4, 'urgency_key' => 'critical', 'urgency_label' => 'Critical'],
            ]);
        }

        if (! Schema::hasTable('resource_request_status')) {
            Schema::create('resource_request_status', function (Blueprint $table) {
                $table->integer('status_id')->primary();
                $table->string('status_key');
                $table->string('status_label');
            });

            DB::table('resource_request_status')->insert([
                ['status_id' => 1, 'status_key' => 'pending', 'status_label' => 'Pending'],
                ['status_id' => 2, 'status_key' => 'approved', 'status_label' => 'Approved'],
                ['status_id' => 3, 'status_key' => 'acknowledged', 'status_label' => 'Acknowledged'],
                ['status_id' => 4, 'status_key' => 'rejected', 'status_label' => 'Rejected'],
                ['status_id' => 5, 'status_key' => 'delivered', 'status_label' => 'Delivered'],
            ]);
        }

        if (! Schema::hasTable('resource_requests')) {
            Schema::create('resource_requests', function (Blueprint $table) {
                $table->string('request_id')->primary();
                $table->string('request_source')->default('hq_desk');
                $table->string('source_reference')->nullable();
                $table->string('request_category')->default('resource');
                $table->string('evacuation_center_id')->nullable();
                $table->string('requested_by')->nullable();
                $table->string('handled_by')->nullable();
                $table->string('resource_type');
                $table->string('item_name')->nullable();
                $table->integer('quantity')->default(1);
                $table->string('unit')->default('units');
                $table->text('description')->nullable();
                $table->integer('urgency_id')->default(2);
                $table->integer('status_id')->default(1);
                $table->string('validation_status')->default('needs_validation');
                $table->text('validation_notes')->nullable();
                $table->string('validated_by_user_id')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('released_for_tracking_at')->nullable();
                $table->string('tracking_reference')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('request_validations')) {
            Schema::create('request_validations', function (Blueprint $table) {
                $table->id();
                $table->string('request_id');
                $table->string('validation_status');
                $table->string('validator_user_id')->nullable();
                $table->string('validated_by_user_id')->nullable();
                $table->text('validation_notes')->nullable();
                $table->text('missing_information')->nullable();
                $table->string('duplicate_request_id')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('user_id')->nullable();
                $table->string('role_key')->nullable();
                $table->string('module')->nullable();
                $table->string('action');
                $table->string('reference_table')->nullable();
                $table->string('reference_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('resqperation_forwarded_requests')) {
            Schema::create('resqperation_forwarded_requests', function (Blueprint $table) {
                $table->id();
                $table->string('tracking_reference');
                $table->string('resqperation_request_id');
                $table->string('source_reference')->nullable();
                $table->string('request_source')->nullable();
                $table->string('source_system')->nullable();
                $table->string('request_category')->nullable();
                $table->string('resource_type')->nullable();
                $table->string('item_name')->nullable();
                $table->integer('quantity')->default(1);
                $table->string('unit')->nullable();
                $table->string('urgency')->nullable();
                $table->string('area_label')->nullable();
                $table->text('area_note')->nullable();
                $table->string('requested_by')->nullable();
                $table->text('description')->nullable();
                $table->text('validation_notes')->nullable();
                $table->string('validated_by_user_id')->nullable();
                $table->string('forwarded_by_user_id')->nullable();
                $table->string('forwarded_by_name')->nullable();
                $table->string('forwarded_by_role')->nullable();
                $table->string('resqperation_status')->default('forwarded');
                $table->json('payload_json')->nullable();
                $table->timestamp('forwarded_at')->nullable();
                $table->timestamps();
            });
        }

        DB::table('users')->updateOrInsert(['user_id' => 'USR-ADMIN-001'], [
            'name' => 'Super Admin',
            'email' => 'admin@resqperation.local',
            'password' => 'secret',
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_create_resource_request_and_forward_to_trackingaid(): void
    {
        config(['services.trackingaid.connection' => 'sqlite']);
        config(['services.trackingaid.forward_table' => 'resqperation_forwarded_requests']);

        $user = User::query()->where('user_id', 'USR-ADMIN-001')->first();

        // 1. Create request
        $response = $this->actingAs($user)->postJson('/api/v1/resource-requests', [
            'request_source' => 'hq_desk',
            'request_category' => 'resource',
            'resource_type' => 'Food packs and clean water',
            'quantity' => 150,
            'unit' => 'packs',
            'requested_by' => 'Punong Barangay',
            'source_reference' => 'EVT-TEST-2026',
            'urgency_id' => 3,
            'description' => 'Urgent food assistance for evacuees',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.request.validation.key', 'needs_validation');

        $requestId = $response->json('data.request.request_id');
        $this->assertNotEmpty($requestId);

        // 2. Validate request
        $valResponse = $this->actingAs($user)->postJson("/api/v1/resource-requests/{$requestId}/validate", [
            'validation_status' => 'verified',
            'validation_notes' => 'Checked beneficiary counts. Approved.',
        ]);

        $valResponse->assertStatus(200)
            ->assertJsonPath('data.request.validation.key', 'verified');

        // 3. Forward request to TrackingAid
        $fwdResponse = $this->actingAs($user)->postJson("/api/v1/resource-requests/{$requestId}/forward", [
            'validation_notes' => 'Verified and forwarded to TrackingAid warehouse team.',
        ]);

        $fwdResponse->assertStatus(200)
            ->assertJsonPath('data.request.validation.key', 'forwarded');

        $trackingRef = $fwdResponse->json('data.request.tracking_reference');
        $this->assertNotEmpty($trackingRef);
        // 4. Run outbox sync job for test environment
        $job = new \App\Jobs\SyncTrackingAidRequest($requestId, true, $trackingRef, $user->user_id, $user->name, 'super_admin', 'Verified and forwarded to TrackingAid warehouse team.');
        app()->call([$job, 'handle']);

        // 5. Verify record in shared DB table
        $this->assertDatabaseHas('resqperation_forwarded_requests', [
            'tracking_reference' => $trackingRef,
            'resqperation_request_id' => $requestId,
            'resqperation_status' => 'forwarded',
            'quantity' => 150,
        ]);
    }
}
