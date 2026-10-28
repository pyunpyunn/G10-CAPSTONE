<?php

namespace Tests\Feature;

use App\Services\Shared\BarangayProfileService;
use App\Services\Shared\HouseholdGeographicClassifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdGeographicClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sitios', function (Blueprint $t): void { $t->integer('sitio_id'); $t->integer('barangay_id'); $t->string('sitio_name'); });
        Schema::create('puroks', function (Blueprint $t): void { $t->integer('purok_id'); $t->integer('sitio_id'); $t->string('purok_name'); });
        Schema::create('addresses', function (Blueprint $t): void { $t->integer('address_id')->primary(); $t->integer('barangay_id')->nullable(); $t->integer('sitio_id')->nullable(); $t->integer('purok_id')->nullable(); $t->string('purok_sitio')->nullable(); });
        Schema::create('households', function (Blueprint $t): void { $t->string('household_id')->primary(); $t->integer('address_id')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        (require database_path('migrations/2026_10_15_000001_create_household_geographic_classification_tables.php'))->up();
        $geometry = $this->polygon(0, 0, 10, 10);
        $this->mock(BarangayProfileService::class, function ($mock) use ($geometry): void {
            $mock->shouldReceive('mapFocus')->andReturn(['barangay_id' => 1, 'name' => 'Mambaling', 'barangay_code' => '072217049', 'boundary' => $geometry]);
            $mock->shouldReceive('mambalingMapFocus')->andReturn(['barangay_id' => 1, 'boundary' => $geometry]);
            $mock->shouldReceive('current')->andReturn(['barangay_id' => 1, 'name' => 'Mambaling', 'barangay_code' => '072217049']);
            $mock->shouldReceive('isMambalingProfile')->andReturn(true);
        });
        DB::table('sitios')->insert([['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'], ['sitio_id' => 20, 'barangay_id' => 2, 'sitio_name' => 'Elsewhere']]);
        DB::table('puroks')->insert([['purok_id' => 100, 'sitio_id' => 10, 'purok_name' => 'Purok 1'], ['purok_id' => 200, 'sitio_id' => 20, 'purok_name' => 'Purok 1']]);
        DB::table('addresses')->insert(['address_id' => 1]);
        DB::table('households')->insert(['household_id' => 'HH-1', 'address_id' => 1]);
    }

    private function polygon(float $x1, float $y1, float $x2, float $y2): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$x1,$y1],[$x2,$y1],[$x2,$y2],[$x1,$y2],[$x1,$y1]]]];
    }
    private function boundary(int $id, array $geometry): void
    {
        DB::table('purok_boundaries')->insert(['purok_id' => $id, 'geometry_json' => json_encode($geometry), 'source' => 'Test fixture', 'created_at' => now(), 'updated_at' => now()]);
    }
    public function test_matched_pin_persists_registered_purok_and_parent_sitio(): void
    {
        $this->boundary(100, $this->polygon(1, 1, 5, 5));
        $result = app(HouseholdGeographicClassifier::class)->classify('HH-1', 2, 3);
        $this->assertSame('matched', $result['status']);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'barangay_id' => 1, 'sitio_id' => 10, 'purok_id' => 100]);
        $this->assertDatabaseHas('household_geographic_classifications', ['household_id' => 'HH-1', 'status' => 'matched']);
    }
    public function test_missing_boundaries_do_not_guess_from_names_or_other_barangay_polygons(): void
    {
        $this->boundary(200, $this->polygon(1, 1, 5, 5));
        $this->assertSame('purok_boundaries_missing', app(HouseholdGeographicClassifier::class)->classify('HH-1', 2, 3)['status']);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'purok_id' => null]);
    }
    public function test_outside_barangay_gaps_overlaps_and_edges_remain_unclassified(): void
    {
        $classifier = app(HouseholdGeographicClassifier::class);
        $this->boundary(100, $this->polygon(1, 1, 5, 5));
        $this->assertSame('outside_barangay', $classifier->resolve(11, 3)['status']);
        $this->assertSame('no_boundary_match', $classifier->resolve(8, 8)['status']);
        $this->assertSame('ambiguous_boundary', $classifier->resolve(1, 3)['status']);
        DB::table('puroks')->insert(['purok_id' => 101, 'sitio_id' => 10, 'purok_name' => 'Purok 2']);
        $this->boundary(101, $this->polygon(2, 2, 6, 6));
        $this->assertSame('ambiguous_boundary', $classifier->resolve(3, 3)['status']);
    }
    public function test_repinning_clears_an_obsolete_automatic_assignment(): void
    {
        $this->boundary(100, $this->polygon(1, 1, 5, 5));
        $classifier = app(HouseholdGeographicClassifier::class);
        $classifier->classify('HH-1', 2, 3);
        $classifier->classify('HH-1', 11, 3);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'sitio_id' => null, 'purok_id' => null]);
        $this->assertDatabaseHas('household_geographic_classifications', ['household_id' => 'HH-1', 'status' => 'outside_barangay']);
    }
    public function test_shared_address_is_copied_without_reclassifying_another_household(): void
    {
        DB::table('households')->insert(['household_id' => 'HH-2', 'address_id' => 1]);
        $this->boundary(100, $this->polygon(1, 1, 5, 5));
        app(HouseholdGeographicClassifier::class)->classify('HH-1', 2, 3);
        $this->assertDatabaseHas('households', ['household_id' => 'HH-1', 'address_id' => 2]);
        $this->assertDatabaseHas('households', ['household_id' => 'HH-2', 'address_id' => 1]);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'purok_id' => null]);
        $this->assertDatabaseHas('addresses', ['address_id' => 2, 'purok_id' => 100]);
    }
    public function test_pin_save_preserves_registered_address_in_the_same_backend_transaction(): void
    {
        Schema::create('geotagged_locations', function (Blueprint $t): void {
            $t->integer('location_id')->primary(); $t->string('household_id');
            $t->decimal('latitude', 10, 7); $t->decimal('longitude', 10, 7);
            $t->string('location_label'); $t->timestamps();
        });
        $this->boundary(100, $this->polygon(1, 1, 5, 5));
        $request = \Illuminate\Http\Request::create('/api/v1/household/geotag', 'PUT', [
            'latitude' => 2, 'longitude' => 3, 'address_label' => 'Saved home pin',
        ]);
        $request->setUserResolver(fn () => (object) ['household_id' => 'HH-1', 'user_id' => 'U-1']);
        $response = app(\App\Services\Mobile\HouseholdMobileSetupWorkflow::class)->updateGeotag($request);
        $this->assertSame('pin_only', $response->getData(true)['data']['geographic_classification']['status']);
        $this->assertDatabaseHas('addresses', ['address_id' => 1, 'sitio_id' => null, 'purok_id' => null]);
        $this->assertDatabaseHas('geotagged_locations', ['household_id' => 'HH-1', 'latitude' => 2, 'longitude' => 3]);
    }

    public function test_imported_boundary_uses_existing_registry_ids_and_classifies_an_existing_pin(): void
    {
        Schema::create('geotagged_locations', function (Blueprint $t): void {
            $t->integer('location_id')->primary(); $t->string('household_id');
            $t->decimal('latitude', 10, 7); $t->decimal('longitude', 10, 7); $t->timestamps();
        });
        DB::table('geotagged_locations')->insert(['location_id' => 1, 'household_id' => 'HH-1', 'latitude' => 2, 'longitude' => 3, 'updated_at' => now()]);
        $path = tempnam(sys_get_temp_dir(), 'purok-import-');
        try {
            file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => [
                ['type' => 'Feature', 'properties' => ['purok_id' => 100, 'sitio_id' => 999, 'name' => 'Untrusted external label'], 'geometry' => $this->polygon(1,1,5,5)],
            ]]));
            $this->artisan('geography:import-puroks', ['file' => $path, '--source' => 'Verified fixture source'])->assertExitCode(0);
            $this->artisan('geography:classify-households')->assertExitCode(0);
            $this->assertDatabaseHas('addresses', ['address_id' => 1, 'purok_id' => null]);
            $this->assertDatabaseCount('household_geographic_classifications', 0);
            $this->artisan('geography:classify-households', ['--apply' => true])->assertExitCode(0);
            $this->assertDatabaseHas('addresses', ['address_id' => 1, 'purok_id' => 100, 'sitio_id' => 10, 'purok_sitio' => 'Purok 1, Sitio A']);
            $this->assertDatabaseHas('purok_boundaries', ['purok_id' => 100, 'source' => 'Verified fixture source']);
            $this->assertDatabaseCount('puroks', 2);
            $this->assertDatabaseCount('sitios', 2);
        } finally { unlink($path); }
    }

    public function test_invalid_boundary_import_is_atomic(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'purok-test-');
        try {
            file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => [
                ['type' => 'Feature', 'properties' => ['purok_id' => 100], 'geometry' => $this->polygon(1,1,5,5)],
                ['type' => 'Feature', 'properties' => ['purok_id' => 200], 'geometry' => $this->polygon(1,1,5,5)],
            ]]));
            $this->artisan('geography:import-puroks', ['file' => $path, '--source' => 'Test fixture'])->assertExitCode(1);
            $this->assertDatabaseCount('purok_boundaries', 0);
        } finally { unlink($path); }
    }
}
