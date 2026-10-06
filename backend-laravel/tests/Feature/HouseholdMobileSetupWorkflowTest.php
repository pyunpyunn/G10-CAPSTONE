<?php

namespace Tests\Feature;

use App\Services\Mobile\HouseholdMobileSetupWorkflow;
use App\Services\Mobile\HouseholdMobileSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdMobileSetupWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('households', fn (Blueprint $table) => $table->string('household_id')->primary());
        Schema::create('geotagged_locations', function (Blueprint $table): void {
            $table->integer('location_id')->primary();
            $table->string('household_id');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->string('geotag_source')->nullable();
            $table->integer('is_verified')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('household_id');
            $table->string('user_id')->nullable();
            $table->string('device_uuid');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('app_role')->nullable();
            $table->string('push_provider')->nullable();
            $table->string('location_permission_status')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->boolean('is_active')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->string('last_location_label')->nullable();
            $table->decimal('last_location_accuracy_m', 8, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('device_tracking_logs', function (Blueprint $table): void {
            $table->integer('tracking_id')->primary();
            $table->integer('device_token_id');
            $table->string('household_id');
            $table->string('member_id')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_label')->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->string('location_source');
            $table->boolean('is_allowed_location');
            $table->integer('battery_level')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->timestamp('logged_at');
        });
    }

    public function test_complete_setup_preserves_response_and_saves_geotag_device_and_track(): void
    {
        $request = $this->householdRequest('/api/v1/household/setup', 'POST', [
            'latitude' => 10.2,
            'longitude' => 123.8,
            'address_label' => 'Zone 4, Cebu City',
            'relationship_to_family' => 'daughter',
            'device_uuid' => 'device-uuid-4',
        ]);
        $response = app(HouseholdMobileSetupWorkflow::class)->completeSetup($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Household mobile setup saved.',
            'data' => ['device_token_id' => 1],
        ], $response->getData(true));
        $this->assertDatabaseHas('geotagged_locations', [
            'household_id' => 'HH-1', 'latitude' => 10.2, 'longitude' => 123.8,
            'location_label' => 'Zone 4, Cebu City', 'geotag_source' => 'household_mobile',
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'id' => 1, 'household_id' => 'HH-1', 'device_uuid' => 'device-uuid-4',
            'app_role' => 'household', 'location_permission_status' => 'granted',
        ]);
        $this->assertDatabaseHas('device_tracking_logs', [
            'device_token_id' => 1, 'household_id' => 'HH-1', 'location_source' => 'setup_pin',
        ]);
    }

    public function test_device_heartbeat_keeps_response_and_does_not_accept_another_households_device(): void
    {
        DB::table('device_tokens')->insert([
            'id' => 3, 'household_id' => 'HH-OTHER', 'device_uuid' => 'shared-uuid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $request = $this->householdRequest('/api/v1/household/device-location', 'POST', [
            'device_uuid' => 'shared-uuid', 'latitude' => 10.4, 'longitude' => 123.9, 'location_label' => 'Near home',
        ]);
        $response = app(HouseholdMobileSetupWorkflow::class)->updateDeviceLocation($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Device location updated.', 'data' => ['device_token_id' => 4]], $response->getData(true));
        $this->assertDatabaseHas('device_tokens', ['id' => 3, 'household_id' => 'HH-OTHER', 'device_uuid' => 'shared-uuid']);
        $this->assertDatabaseHas('device_tokens', ['id' => 4, 'household_id' => 'HH-1', 'device_uuid' => 'shared-uuid']);
    }

    public function test_generated_primary_keys_are_preserved_during_insert_payload_preparation(): void
    {
        $support = new HouseholdMobileSupport();

        $geotagPayload = $support->filterColumns('geotagged_locations', [
            'household_id' => 'HH-1',
            'latitude' => 10.2,
            'longitude' => 123.8,
            'location_label' => 'Zone 4, Cebu City',
            'accuracy_m' => 15,
            'geotag_source' => 'household_mobile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preparedGeotag = $support->withGeneratedKey('geotagged_locations', 'location_id', $geotagPayload, 77);
        $this->assertSame(77, $preparedGeotag['location_id']);

        $devicePayload = $support->filterColumns('device_tokens', [
            'household_id' => 'HH-1',
            'device_uuid' => 'device-uuid-77',
            'app_role' => 'household',
            'location_permission_status' => 'granted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preparedDevice = $support->withGeneratedKey('device_tokens', 'id', $devicePayload, 88);
        $this->assertSame(88, $preparedDevice['id']);

        $trackingPayload = $support->filterColumns('device_tracking_logs', [
            'household_id' => 'HH-1',
            'device_token_id' => 88,
            'latitude' => 10.2,
            'longitude' => 123.8,
            'location_label' => 'Zone 4, Cebu City',
            'location_source' => 'setup_pin',
            'logged_at' => now(),
        ]);

        $preparedTracking = $support->withGeneratedKey('device_tracking_logs', 'tracking_id', $trackingPayload, 99);
        $this->assertSame(99, $preparedTracking['tracking_id']);
    }

    private function householdRequest(string $uri, string $method, array $input): Request
    {
        $request = Request::create($uri, $method, $input);
        $request->setUserResolver(fn () => (object) ['household_id' => 'HH-1', 'user_id' => 'USR-1']);
        return $request;
    }
}



