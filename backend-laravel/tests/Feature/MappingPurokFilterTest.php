<?php

namespace Tests\Feature;

use App\Queries\MappingQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MappingPurokFilterTest extends TestCase
{
    public function test_map_filters_same_named_puroks_by_registered_id_and_separates_evacuated_status(): void
    {
        Schema::create('sitios', function (Blueprint $t): void { $t->integer('sitio_id'); $t->integer('barangay_id'); $t->string('sitio_name'); });
        Schema::create('puroks', function (Blueprint $t): void { $t->integer('purok_id'); $t->integer('sitio_id'); $t->string('purok_name'); });
        Schema::create('addresses', function (Blueprint $t): void { $t->integer('address_id'); $t->integer('barangay_id'); $t->integer('purok_id'); $t->string('purok_sitio'); });
        Schema::create('households', function (Blueprint $t): void { $t->string('household_id'); $t->integer('address_id'); $t->string('household_code'); $t->string('household_name'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('household_disasters', function (Blueprint $t): void { $t->string('household_id'); $t->string('disaster_id'); $t->integer('current_status_id'); });
        Schema::create('household_statuses', function (Blueprint $t): void { $t->integer('status_id'); $t->string('status_key'); $t->string('status_label'); });
        Schema::create('geotagged_locations', function (Blueprint $t): void { $t->integer('location_id'); $t->string('household_id'); $t->float('latitude'); $t->float('longitude'); $t->timestamp('updated_at')->nullable(); });
        DB::table('sitios')->insert([['sitio_id' => 1, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'], ['sitio_id' => 2, 'barangay_id' => 1, 'sitio_name' => 'Sitio B']]);
        DB::table('puroks')->insert([['purok_id' => 10, 'sitio_id' => 1, 'purok_name' => 'Purok 1'], ['purok_id' => 20, 'sitio_id' => 2, 'purok_name' => 'Purok 1']]);
        DB::table('household_statuses')->insert(['status_id' => 1, 'status_key' => 'evacuated', 'status_label' => 'Evacuated']);
        foreach ([1 => 10, 2 => 20] as $id => $purok) {
            DB::table('addresses')->insert(['address_id' => $id, 'barangay_id' => 1, 'purok_id' => $purok, 'purok_sitio' => 'Purok 1']);
            DB::table('households')->insert(['household_id' => 'HH-'.$id, 'address_id' => $id, 'household_code' => 'CODE-'.$id, 'household_name' => 'Family '.$id]);
            DB::table('household_disasters')->insert(['household_id' => 'HH-'.$id, 'disaster_id' => 'EVT-1', 'current_status_id' => 1]);
            DB::table('geotagged_locations')->insert(['location_id' => $id, 'household_id' => 'HH-'.$id, 'latitude' => 10.3, 'longitude' => 123.9]);
        }
        $query = app(MappingQuery::class);
        $points = $query->getHouseholdGeotags(Request::create('/', 'GET', ['purok_id' => 20]), 'EVT-1', 1);
        $this->assertCount(1, $points);
        $this->assertSame('HH-2', $points[0]['household_id']);
        $this->assertSame('blue', $points[0]['marker_group']);
        $this->assertSame('#2a5a90', $points[0]['marker_color']);
        $this->assertSame(20, $points[0]['purok_id']);
        $this->assertCount(2, $query->getPurokOptions(1));
        // A same-named purok elsewhere, a name-only address, and deleted accounts are ineligible.
        DB::table('sitios')->insert(['sitio_id' => 3, 'barangay_id' => 2, 'sitio_name' => 'Other barangay']);
        DB::table('puroks')->insert(['purok_id' => 30, 'sitio_id' => 3, 'purok_name' => 'Purok 1']);
        Schema::table('addresses', fn (Blueprint $t) => $t->integer('purok_id')->nullable()->change());
        foreach ([3 => 30, 4 => null, 5 => 10, 6 => 10] as $id => $purok) {
            DB::table('addresses')->insert(['address_id' => $id, 'barangay_id' => $id === 6 ? 2 : 1, 'purok_id' => $purok, 'purok_sitio' => 'Purok 1']);
            DB::table('households')->insert(['household_id' => 'HH-'.$id, 'address_id' => $id, 'household_code' => 'CODE-'.$id, 'household_name' => 'Family '.$id, 'deleted_at' => $id === 5 ? now() : null]);
            DB::table('geotagged_locations')->insert(['location_id' => $id, 'household_id' => 'HH-'.$id, 'latitude' => 10.3, 'longitude' => 123.9]);
        }
        $this->mock(\App\Services\Shared\BarangayProfileService::class, function ($mock): void {
            $mock->shouldReceive('current')->andReturn(['barangay_id' => 1]);
        });
        $eligible = \App\Queries\HouseholdEligibilityQuery::ids(1)->pluck('eligible_household.household_id')->sort()->values()->all();
        $this->assertSame(['HH-1', 'HH-2', 'HH-6'], $eligible);
        $this->assertCount(3, $query->getHouseholdGeotags(Request::create('/'), 'EVT-1', 1));
        $summary = $query->getSummary(null, false, 1, 0);
        $this->assertSame(3, $summary['total_households']);
        $this->assertSame(3, $summary['gps_tagged_households']);
        $this->assertSame(3, app(\App\Queries\DashboardQuery::class)->getHouseholdSummary(null)['total']);

    }
}
