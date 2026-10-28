<?php

namespace Tests\Feature;

use App\Services\Navigation\RoutePrecomputeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoutePrecomputeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createNavigationTables();
        config(['rescue_office.latitude' => 10.292222, 'rescue_office.longitude' => 123.876389]);
    }

    public function test_team_precompute_persists_ready_routes_and_lists_households_without_pins(): void
    {
        DB::table('rescue_teams')->insert(['team_id' => 1, 'team_name' => 'Search and Rescue', 'assigned_purok_id' => 10]);
        DB::table('addresses')->insert([
            ['address_id' => 10, 'purok_id' => 50],
            ['address_id' => 11, 'purok_id' => 50],
            ['address_id' => 12, 'purok_id' => 50],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-A', 'address_id' => 11],
            ['household_id' => 'HH-B', 'address_id' => 12],
            ['household_id' => 'HH-C', 'address_id' => null],
        ]);
        DB::table('geotagged_locations')->insert([
            ['location_id' => 1, 'household_id' => 'HH-A', 'latitude' => 10.291, 'longitude' => 123.877, 'updated_at' => now()],
            ['location_id' => 2, 'household_id' => 'HH-C', 'latitude' => 10.292, 'longitude' => 123.878, 'updated_at' => now()],
        ]);
        DB::table('responder_assignments')->insert([
            'assignment_id' => 1,
            'team_id' => 1,
            'household_id' => 'HH-C',
            'status' => 'en_route',
            'route_notes' => json_encode(['household_ids' => ['HH-B']]),
        ]);
        DB::table('evacuation_centers')->insert([
            'evacuation_center_id' => 'EC-1',
            'name' => 'Mambaling School',
            'latitude' => 10.293,
            'longitude' => 123.879,
            'status' => 'active',
        ]);

        Http::fake(fn (Request $request) => Http::response([
            'trip' => [
                'status' => 0,
                'summary' => ['length' => 1.25, 'time' => 90],
                'legs' => [[
                    'shape' => 'polyline6',
                    'maneuvers' => [['instruction' => 'Continue.', 'length' => 1.25, 'type' => 1, 'begin_shape_index' => 0]],
                ]],
            ],
        ]));

        $result = app(RoutePrecomputeService::class)->precomputeTeam(1);
        app(RoutePrecomputeService::class)->precomputeTeam(1);
        $coverage = app(RoutePrecomputeService::class)->coverageRows(1);

        $this->assertSame(3, $result['households']);
        $this->assertSame(1, $result['centers']);
        $this->assertSame(6, $result['ready']);
        $this->assertSame(2, $result['failed']);
        $this->assertDatabaseHas('cached_routes', [
            'team_id' => 1,
            'household_id' => 'HH-A',
            'target_key' => 'household:HH-A',
            'profile' => 'pedestrian',
            'status' => 'ready',
            'distance_m' => 1250,
        ]);
        $this->assertDatabaseHas('cached_routes', [
            'team_id' => 1,
            'household_id' => 'HH-B',
            'target_key' => 'household:HH-B',
            'profile' => 'auto',
            'status' => 'failed',
            'failure_reason' => 'target_geotag_missing',
        ]);
        $this->assertDatabaseCount('cached_routes', 8);
        $this->assertSame(2, count(array_filter($coverage['failures'], fn (array $row): bool => $row['household_id'] === 'HH-B')));
        Http::assertSentCount(12);
    }

    private function createNavigationTables(): void
    {
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary();
            $table->string('team_name')->nullable();
            $table->integer('assigned_purok_id')->nullable();
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->integer('purok_id')->nullable();
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->integer('address_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('geotagged_locations', function (Blueprint $table): void {
            $table->integer('location_id')->primary();
            $table->string('household_id');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->integer('team_id')->nullable();
            $table->string('household_id')->nullable();
            $table->string('status')->nullable();
            $table->text('route_notes')->nullable();
        });
        Schema::create('evacuation_centers', function (Blueprint $table): void {
            $table->string('evacuation_center_id')->primary();
            $table->string('name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::table('rescue_teams', function (Blueprint $table): void {
            $table->string('base_name')->nullable();
            $table->decimal('base_latitude', 10, 7)->nullable();
            $table->decimal('base_longitude', 10, 7)->nullable();
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
            $table->unique(['team_id', 'target_key', 'profile']);
        });
    }
}
