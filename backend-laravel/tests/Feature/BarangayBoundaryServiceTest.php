<?php

namespace Tests\Feature;

use App\Services\Shared\BarangayBoundaryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BarangayBoundaryServiceTest extends TestCase
{
    public function test_mambaling_uses_a_polygon_and_rejects_points_outside_it(): void
    {
        $boundary = app(BarangayBoundaryService::class)->forProfile([
            'barangay_code' => '072217049', 'name' => 'Barangay Mambaling', 'city_name' => 'Cebu City',
        ]);

        $this->assertSame('Polygon', $boundary['geometry']['type']);
        $this->assertCount(2, $boundary['bounds']);
        $this->assertTrue(app(BarangayBoundaryService::class)->contains($boundary['geometry'], 10.287, 123.880));
        $this->assertFalse(app(BarangayBoundaryService::class)->contains($boundary['geometry'], 10.294, 123.887));
    }

    public function test_another_barangay_does_not_inherit_mambalings_boundary(): void
    {
        $this->assertNull(app(BarangayBoundaryService::class)->forProfile([
            'barangay_code' => 'other', 'name' => 'Another Barangay', 'city_name' => 'Cebu City',
        ]));
    }

    public function test_migration_stores_only_the_identified_mambaling_feature(): void
    {
        $migration = require database_path('migrations/2026_10_04_000005_create_barangay_boundaries_table.php');
        $migration->up();

        $this->assertDatabaseCount('barangay_boundaries', 1);
        $this->assertSame('Mambaling', DB::table('barangay_boundaries')
            ->where('psgc_code', '0730600049')->value('barangay_name'));
        $this->assertNotNull(app(BarangayBoundaryService::class)->forProfile([
            'barangay_code' => '072217049', 'name' => 'Mambaling', 'city_name' => 'Cebu City',
        ]));
    }
}
