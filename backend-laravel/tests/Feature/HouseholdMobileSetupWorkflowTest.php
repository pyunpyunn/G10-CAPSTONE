<?php

namespace Tests\Feature;

use App\Services\Mobile\HouseholdMobileSetupWorkflow;
use App\Services\Mobile\HouseholdMobileSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HouseholdMobileSetupWorkflowTest extends TestCase
{
    public function test_pin_autosave_updates_only_the_authenticated_household_geotag(): void
    {
        DB::table('geotagged_locations')->insert([
            'location_id' => 5, 'household_id' => 'HH-OTHER', 'latitude' => 10.1,
            'longitude' => 123.8, 'location_label' => 'Other household',
        ]);
        $workflow = app(HouseholdMobileSetupWorkflow::class);
        foreach ([10.287, 10.288] as $latitude) {
            $response = $workflow->updateGeotag($this->householdRequest('/api/v1/household/geotag', 'PUT', [
                'latitude' => $latitude, 'longitude' => 123.88,
                'address_label' => 'Mambaling, Cebu City', 'accuracy_m' => null,
                'household_id' => 'HH-OTHER',
            ]));
            $this->assertSame(200, $response->getStatusCode());
        }
        $this->assertDatabaseHas('geotagged_locations', [
            'household_id' => 'HH-1', 'latitude' => 10.288, 'longitude' => 123.88,
            'location_label' => 'Mambaling, Cebu City',
        ]);
        $this->assertDatabaseHas('geotagged_locations', ['household_id' => 'HH-OTHER', 'latitude' => 10.1]);
        $this->assertDatabaseCount('geotagged_locations', 2);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_geotag_pin_outside_mambaling_is_rejected_without_saving(): void
    {
        $response = app(HouseholdMobileSetupWorkflow::class)->updateGeotag(
            $this->householdRequest('/api/v1/household/geotag', 'PUT', [
                'latitude' => 10.5,
                'longitude' => 123.88,
                'address_label' => 'Outside Mambaling',
            ])
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Choose a household location inside the Mambaling boundary.', $response->getData(true)['message']);
        $this->assertDatabaseCount('geotagged_locations', 0);
    }

    public function test_geotag_uses_mambaling_boundary_instead_of_unrelated_active_profile(): void
    {
        $feature = json_decode(file_get_contents(database_path('boundaries/0730600049.geojson')), true);
        $this->mock(\App\Services\Shared\BarangayProfileService::class, function ($mock) use ($feature): void {
            $mock->shouldNotReceive('current');
            $mock->shouldNotReceive('isMambalingProfile');
            $mock->shouldReceive('mambalingMapFocus')->andReturn([
                'barangay_id' => 1,
                'boundary' => $feature['geometry'],
            ]);
        });

        $response = app(HouseholdMobileSetupWorkflow::class)->updateGeotag(
            $this->householdRequest('/api/v1/household/geotag', 'PUT', [
                'latitude' => 10.288,
                'longitude' => 123.881,
                'address_label' => 'Household in Mambaling',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('geotagged_locations', [
            'household_id' => 'HH-1',
            'latitude' => 10.288,
            'longitude' => 123.881,
        ]);
    }

    public function test_geotag_ignores_sitio_and_purok_without_changing_registered_address(): void
    {
        $this->registeredAddress();
        $response = app(HouseholdMobileSetupWorkflow::class)->updateGeotag($this->householdRequest('/geotag', 'PUT', [
            'latitude' => 10.287, 'longitude' => 123.88, 'address_label' => 'Independent pin',
            'sitio_id' => 999, 'purok_id' => 999,
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => 'pin_only'], $response->getData(true)['data']['geographic_classification']);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'sitio_id' => 99, 'purok_id' => 2]);
        $this->assertDatabaseHas('geotagged_locations', ['household_id' => 'HH-1', 'location_label' => 'Independent pin']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('households', fn (Blueprint $table) => $table->string('household_id')->primary());
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary(); $table->string('household_id'); $table->string('name');
        });
        DB::table('household_members')->insert(['member_id' => 'HM-1-1', 'household_id' => 'HH-1', 'name' => 'Device owner']);
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
            $table->string('member_id')->nullable();
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
        $this->registeredAddress();
        $request = $this->householdRequest('/api/v1/household/setup', 'POST', [
            'latitude' => 10.287,
            'longitude' => 123.88,
            'is_apartment' => false,
            'house_number' => '42',
            'unit_number' => 'Ignore for a non-apartment household',
            'address_label' => 'Untrusted address text',
            'relationship_to_family' => 'daughter',
            'device_uuid' => 'device-uuid-4',
        ]);
        $response = app(\App\Http\Controllers\Api\HouseholdMobileController::class)->completeSetup($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['data']['reused_geotag']);
        $this->assertSame(['status' => 'pin_only'], $payload['data']['geographic_classification']);
        $this->assertDatabaseHas('geotagged_locations', [
            'household_id' => 'HH-1', 'latitude' => 10.287, 'longitude' => 123.88,
            'location_label' => '10.287, 123.88', 'geotag_source' => 'household_mobile',
        ]);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'sitio_id' => 99, 'purok_id' => 2, 'house_number' => '42', 'unit_number' => null]);
        $this->assertDatabaseHas('device_tokens', [
            'id' => 1, 'household_id' => 'HH-1', 'device_uuid' => 'device-uuid-4', 'member_id' => 'HM-1-1',
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

    private function registeredAddress(): void
    {
        Schema::table('households', fn (Blueprint $t) => $t->integer('address_id')->nullable());
        Schema::create('addresses', function (Blueprint $t): void {
            $t->integer('address_id')->primary(); $t->integer('barangay_id'); $t->integer('sitio_id'); $t->integer('purok_id');
            $t->string('barangay_name')->nullable(); $t->string('purok_sitio')->nullable();
            $t->string('street')->nullable(); $t->string('house_number')->nullable(); $t->text('full_address')->nullable(); $t->timestamps();
        });
        Schema::create('barangays', function (Blueprint $t): void { $t->integer('barangay_id'); $t->string('barangay_name'); });
        Schema::create('sitios', function (Blueprint $t): void { $t->integer('sitio_id'); $t->integer('barangay_id'); $t->string('sitio_name'); });
        Schema::create('puroks', function (Blueprint $t): void { $t->integer('purok_id'); $t->integer('sitio_id'); $t->string('purok_name'); });
        DB::table('barangays')->insert(['barangay_id' => 396, 'barangay_name' => 'Mambaling']);
        DB::table('sitios')->insert(['sitio_id' => 1, 'barangay_id' => 396, 'sitio_name' => 'Registered Sitio']);
        DB::table('puroks')->insert(['purok_id' => 2, 'sitio_id' => 1, 'purok_name' => 'Purok 2']);
        DB::table('addresses')->insert(['address_id' => 1, 'barangay_id' => 396, 'sitio_id' => 99, 'purok_id' => 2, 'street' => 'Sample Street']);
        DB::table('households')->insert(['household_id' => 'HH-1', 'address_id' => 1]);
        $migration = require database_path('migrations/2026_10_26_000002_add_household_apartment_address_details.php');
        $migration->up();
        $this->mock(\App\Services\Shared\BarangayProfileService::class, function ($mock): void {
            $mock->shouldReceive('mambalingMapFocus')->andReturn([
                'barangay_id' => 396,
                'boundary' => ['type' => 'Polygon', 'coordinates' => [[[123.87,10.28],[123.90,10.28],[123.90,10.30],[123.87,10.30],[123.87,10.28]]]],
            ]);
        });
    }

    public function test_location_preview_uses_registered_ids_and_does_not_save_a_partial_geotag(): void
    {
        $this->registeredAddress();
        $workflow = app(HouseholdMobileSetupWorkflow::class);
        $response = $workflow->previewSetupLocation($this->householdRequest('/setup/location', 'POST', ['latitude' => 10.287, 'longitude' => 123.88]));
        $address = $response->getData(true)['data']['address'];
        $this->assertSame(99, $address['sitio_id']);
        $this->assertSame(2, $address['purok_id']);
        $this->assertSame('registered_address', $address['source']);
        $this->assertDatabaseCount('geotagged_locations', 0);
        $this->assertDatabaseHas('addresses', ['sitio_id' => 99]);
    }

    public function test_existing_household_geotag_is_reused_without_repinning_or_address_changes(): void
    {
        $this->registeredAddress();
        DB::table('geotagged_locations')->insert(['location_id' => 8, 'household_id' => 'HH-1', 'latitude' => 10.287, 'longitude' => 123.88, 'location_label' => 'Shared home']);
        $response = app(HouseholdMobileSetupWorkflow::class)->completeSetup($this->householdRequest('/setup', 'POST', [
            'device_uuid' => 'new-member-phone', 'latitude' => 0, 'longitude' => 0, 'house_number' => 'Must not overwrite',
        ]));
        $this->assertTrue($response->getData(true)['data']['reused_geotag']);
        $this->assertDatabaseHas('geotagged_locations', ['location_id' => 8, 'latitude' => 10.287, 'longitude' => 123.88, 'location_label' => 'Shared home']);
        $this->assertDatabaseCount('geotagged_locations', 1);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'house_number' => null]);
        $this->assertDatabaseHas('device_tokens', ['device_uuid' => 'new-member-phone', 'member_id' => 'HM-1-1']);
    }

    public function test_apartment_unit_is_required_and_details_are_saved_with_official_ids(): void
    {
        $this->registeredAddress();
        $workflow = app(HouseholdMobileSetupWorkflow::class);
        $payload = ['device_uuid' => 'apt-phone', 'latitude' => 10.287, 'longitude' => 123.88, 'is_apartment' => true];
        try {
            $workflow->completeSetup($this->householdRequest('/setup', 'POST', $payload));
            $this->fail('An apartment requires a unit number.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('unit_number', $error->errors());
        }
        $this->assertDatabaseCount('geotagged_locations', 0);
        $response = $workflow->completeSetup($this->householdRequest('/setup', 'POST', array_merge($payload, [
            'unit_number' => '12B', 'building_name' => 'Building A', 'floor_number' => '3', 'room_number' => '2', 'landmark' => 'School',
            'sitio_id' => 999, 'purok_id' => 999,
        ])));
        $this->assertFalse($response->getData(true)['data']['reused_geotag']);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'sitio_id' => 99, 'purok_id' => 2, 'is_apartment' => true, 'unit_number' => '12B', 'floor_number' => '3', 'building_name' => 'Building A', 'room_number' => '2', 'landmark' => 'School']);
        $this->assertStringContainsString('Unit 12B', DB::table('addresses')->value('full_address'));
        $this->assertStringContainsString('Unit 12B', app(\App\Queries\HouseholdAddressQuery::class)->forHouseholds(['HH-1'])->get('HH-1'));
    }

    public function test_new_setup_rejects_an_outside_pin_and_requires_an_apartment_choice(): void
    {
        $this->registeredAddress();
        $workflow = app(HouseholdMobileSetupWorkflow::class);
        foreach ([
            ['latitude' => 10.287, 'longitude' => 123.88],
            ['latitude' => 10.2, 'longitude' => 123.8, 'is_apartment' => false],
        ] as $details) {
            try {
                $workflow->completeSetup($this->householdRequest('/setup', 'POST', array_merge(['device_uuid' => 'phone'], $details)));
                $this->fail('Invalid setup must not be saved.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $this->assertDatabaseCount('geotagged_locations', 0);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_another_households_location_does_not_bypass_setup(): void
    {
        $this->registeredAddress();
        DB::table('geotagged_locations')->insert(['location_id' => 5, 'household_id' => 'HH-OTHER', 'latitude' => 10.287, 'longitude' => 123.88]);
        $this->expectException(ValidationException::class);
        app(HouseholdMobileSetupWorkflow::class)->completeSetup($this->householdRequest('/setup', 'POST', ['device_uuid' => 'phone']));
    }

    public function test_missing_registered_purok_does_not_block_preview_or_setup(): void
    {
        $this->registeredAddress();
        DB::table('addresses')->update(['purok_id' => 999]);
        $workflow = app(HouseholdMobileSetupWorkflow::class);
        $pin = ['latitude' => 10.287, 'longitude' => 123.88, 'sitio_id' => 1, 'purok_id' => 2];
        $this->assertSame(200, $workflow->previewSetupLocation($this->householdRequest('/setup/location', 'POST', $pin))->getStatusCode());
        $response = $workflow->completeSetup($this->householdRequest('/setup', 'POST', array_merge($pin, [
            'device_uuid' => 'phone', 'is_apartment' => false,
        ])));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'sitio_id' => 99, 'purok_id' => 999]);
        $this->assertDatabaseHas('geotagged_locations', ['household_id' => 'HH-1', 'latitude' => 10.287, 'longitude' => 123.88]);
    }

    public function test_incomplete_location_record_is_completed_instead_of_creating_a_duplicate(): void
    {
        $this->registeredAddress();
        DB::table('geotagged_locations')->insert(['location_id' => 5, 'household_id' => 'HH-1']);
        $response = app(HouseholdMobileSetupWorkflow::class)->completeSetup($this->householdRequest('/setup', 'POST', [
            'device_uuid' => 'phone', 'latitude' => 10.287, 'longitude' => 123.88, 'is_apartment' => false,
        ]));
        $this->assertFalse($response->getData(true)['data']['reused_geotag']);
        $this->assertDatabaseCount('geotagged_locations', 1);
        $this->assertDatabaseHas('geotagged_locations', ['location_id' => 5, 'latitude' => 10.287, 'longitude' => 123.88]);
    }

    private function householdRequest(string $uri, string $method, array $input): Request
    {
        $request = Request::create($uri, $method, $input);
        $request->setUserResolver(fn () => (object) ['household_id' => 'HH-1', 'user_id' => 'USR-1', 'member_id' => 'HM-1-1']);
        return $request;
    }
}
