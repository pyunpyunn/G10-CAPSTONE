<?php

namespace Tests\Feature;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdMobileMapAvailabilityTest extends TestCase
{
    public function test_mobile_map_retains_vacant_destinations_after_twenty_unavailable_centers(): void
    {
        Schema::create('evacuation_centers', function ($table) {
            $table->string('evacuation_center_id')->primary();
            $table->string('name');
            $table->string('latitude');
            $table->string('longitude');
            $table->integer('capacity')->nullable();
            $table->integer('current_occupancy')->nullable();
            $table->string('status');
        });

        for ($i = 0; $i < 20; $i++) {
            $this->insertCenter('full-'.$i, 10, 10, 'active');
        }
        $this->insertCenter('vacant', 10, 2, 'active');
        $this->insertCenter('closed', 10, 2, 'closed');
        $this->insertCenter('unknown', null, null, 'active');
        $this->insertCenter('invalid', 10, 2, 'active', 'invalid');

        $centers = collect((new HouseholdMobileReadQuery(new HouseholdMobilePresenter()))
            ->evacuationCenters(null, 10.3157, 123.8854))->keyBy('evacuation_center_id');

        $this->assertCount(24, $centers);
        $this->assertTrue($centers['vacant']['route_available']);
        $this->assertSame(8, $centers['vacant']['vacancy']);
        foreach (['full-0', 'closed', 'unknown', 'invalid'] as $id) {
            $this->assertFalse($centers[$id]['route_available'], $id);
        }
    }

    private function insertCenter(string $id, ?int $capacity, ?int $occupancy, string $status, string $latitude = '10.32'): void
    {
        DB::table('evacuation_centers')->insert([
            'evacuation_center_id' => $id,
            'name' => $id,
            'latitude' => $latitude,
            'longitude' => '123.89',
            'capacity' => $capacity,
            'current_occupancy' => $occupancy,
            'status' => $status,
        ]);
    }
}
