<?php

namespace Tests\Feature;

use App\Presenters\MappingPresenter;
use Tests\TestCase;

class MappingPresenterTest extends TestCase
{
    public function test_household_marker_keeps_coordinates_status_and_color(): void
    {
        $marker=app(MappingPresenter::class)->formatHouseholdPoint((object)[
            'location_id'=>4,'household_id'=>'HH-4','status_key'=>'safe_at_home','status_label'=>'Safe',
            'household_name'=>'Household Four','household_code'=>'HH04','purok_name'=>'Sitio Uno',
            'latitude'=>'10.1','longitude'=>'123.2','accuracy_m'=>'8','location_label'=>'Near hall',
            'geotag_source'=>'mobile','is_verified'=>1,'last_reported_at'=>null,'last_battery_level'=>90,
            'priority_level'=>null,'created_at'=>'2026-09-30 10:00:00','updated_at'=>null,
        ]);
        $this->assertSame('green',$marker['marker_group']);
        $this->assertSame('#16a34a',$marker['marker_color']);
        $this->assertSame(10.1,$marker['latitude']);
        $this->assertSame('HH-4',$marker['household_id']);
    }

    public function test_route_projection_keeps_team_status_and_query_coordinates(): void
    {
        $route=app(MappingPresenter::class)->formatRoute((object)[
            'route_id'=>2,'assignment_id'=>5,'route_name'=>'Route A','team_name'=>'SAR','full_name'=>null,
            'assigned_area'=>'Sitio Uno','route_status'=>'en_route','status'=>'assigned','estimated_distance_km'=>'2.5',
            'estimated_duration_min'=>8,'created_at'=>null,
        ],[[10.0,123.0],[10.1,123.1]]);
        $this->assertSame('SAR',$route['team_name']);
        $this->assertSame('en_route',$route['status']);
        $this->assertSame([[10.0,123.0],[10.1,123.1]],$route['coordinates']);
    }
}


