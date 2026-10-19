<?php

namespace Tests\Feature;

use App\Services\Navigation\MobileNavigationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileNavigationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createBootstrapTables();
    }

    public function test_bootstrap_is_team_scoped_and_supports_etag_revalidation_without_n_plus_one_queries(): void
    {
        DB::table('rescue_teams')->insert([
            ['team_id' => 1, 'team_name' => 'SAR', 'team_code' => 'SAR', 'team_type' => 'rescue', 'assigned_purok_id' => 10],
            ['team_id' => 2, 'team_name' => 'Medical', 'team_code' => 'MED', 'team_type' => 'medical', 'assigned_purok_id' => 20],
        ]);
        DB::table('addresses')->insert([
            ['address_id' => 10, 'purok_id' => 100],
            ['address_id' => 11, 'purok_id' => 100],
            ['address_id' => 20, 'purok_id' => 200],
            ['address_id' => 21, 'purok_id' => 200],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-SAR', 'household_name' => 'SAR Assigned', 'member_count' => 4, 'address_id' => 11, 'updated_at' => now()],
            ['household_id' => 'HH-MED', 'household_name' => 'Medical Assigned', 'member_count' => 2, 'address_id' => 21, 'updated_at' => now()],
        ]);
        DB::table('responder_assignments')->insert([
            'assignment_id' => 50,
            'assignment_code' => 'DSP-50',
            'team_id' => 1,
            'disaster_id' => 'EVENT-1',
            'household_id' => 'HH-SAR',
            'status' => 'en_route',
            'assigned_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('geotagged_locations')->insert([
            ['location_id' => 1, 'household_id' => 'HH-SAR', 'latitude' => 10.29, 'longitude' => 123.88, 'is_verified' => 0, 'updated_at' => now()],
            ['location_id' => 2, 'household_id' => 'HH-MED', 'latitude' => 10.291, 'longitude' => 123.881, 'is_verified' => 1, 'updated_at' => now()],
        ]);
        DB::table('cached_routes')->insert([
            'team_id' => 1, 'household_id' => 'HH-SAR', 'target_key' => 'household:HH-SAR', 'profile' => 'auto',
            'polyline6' => 'polyline', 'steps' => json_encode([['instruction' => 'Continue']], JSON_THROW_ON_ERROR),
            'distance_m' => 450, 'duration_s' => 80, 'status' => 'ready', 'computed_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('evacuation_centers')->insert([
            'evacuation_center_id' => 'EC-1', 'name' => 'Evacuation site', 'latitude' => 10.292,
            'longitude' => 123.882, 'status' => 'active', 'updated_at' => now(),
        ]);
        DB::table('offline_packs')->insert([
            'version' => 'v1', 'bbox' => json_encode([123.87, 10.28, 123.90, 10.30], JSON_THROW_ON_ERROR),
            'style_url' => 'http://tiles.example/styles/mambaling/style.json', 'size_bytes' => 100,
            'checksum' => str_repeat('a', 64), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/api/v1/mobile/bootstrap', 'GET');
        $request->attributes->set('mobile_device', (object) ['id' => 1, 'team_id' => 1]);
        DB::enableQueryLog();

        $service = app(MobileNavigationService::class);
        $payload = $service->bootstrap($request);
        $queryCount = count(DB::getQueryLog());
        $response = $service->etagResponse($request, $payload);
        $request->headers->set('If-None-Match', $response->headers->get('ETag'));
        $notModified = $service->etagResponse($request, $payload);

        $this->assertSame(1, (int) $payload['team']->team_id);
        $this->assertSame(['HH-SAR'], collect($payload['households'])->pluck('household_id')->all());
        $this->assertSame(['HH-SAR'], collect($payload['geotags'])->pluck('household_id')->all());
        $this->assertSame(['HH-SAR'], collect($payload['routes'])->pluck('household_id')->filter()->values()->all());
        $this->assertSame('v1', $payload['pack']['version']);
        $this->assertStringContainsString('signature=', $payload['pack']['download_url']);
        // Includes one fixed query for the offline household status choices.
        $this->assertLessThanOrEqual(15, $queryCount);
        $this->assertSame(304, $notModified->getStatusCode());
    }

    private function createBootstrapTables(): void
    {
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status_key');
            $table->string('status_label');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary();
            $table->string('team_name');
            $table->string('team_code');
            $table->string('team_type');
            $table->integer('assigned_purok_id')->nullable();
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->integer('purok_id')->nullable();
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_code')->nullable();
            $table->string('household_name')->nullable();
            $table->integer('member_count')->nullable();
            $table->integer('address_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->string('assignment_code')->nullable();
            $table->integer('team_id');
            $table->string('disaster_id')->nullable();
            $table->string('dispatch_type')->nullable();
            $table->string('household_id')->nullable();
            $table->text('route_notes')->nullable();
            $table->string('assigned_area')->nullable();
            $table->string('status');
            $table->dateTime('assigned_at');
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('geotagged_locations', function (Blueprint $table): void {
            $table->integer('location_id')->primary();
            $table->string('household_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_label')->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('cached_routes', function (Blueprint $table): void {
            $table->id();
            $table->integer('team_id');
            $table->string('household_id')->nullable();
            $table->string('evac_center_id')->nullable();
            $table->string('target_key');
            $table->string('profile');
            $table->text('polyline6')->nullable();
            $table->json('steps')->nullable();
            $table->integer('distance_m')->nullable();
            $table->integer('duration_s')->nullable();
            $table->string('status');
            $table->string('failure_reason')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('evacuation_centers', function (Blueprint $table): void {
            $table->string('evacuation_center_id')->primary();
            $table->string('name');
            $table->string('center_type')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('current_occupancy')->nullable();
            $table->string('status');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('offline_packs', function (Blueprint $table): void {
            $table->string('version')->primary();
            $table->json('bbox');
            $table->string('style_url');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }
}
