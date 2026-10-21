<?php
namespace Tests\Feature;
use App\Services\Web\HazardLayerService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class HazardLayerTest extends TestCase
{
    public function test_valid_official_geometry_types_are_accepted(): void {
        $service = new HazardLayerService;
        foreach ([
            ['hazard','Polygon', [[[123,10],[124,10],[124,11],[123,10]]]],
            ['hazard','MultiPolygon', [[[[123,10],[124,10],[124,11],[123,10]]]]],
            ['evacuation_center','Point', [123,10]],
            ['safe_route','LineString', [[123,10],[124,11]]],
        ] as [$kind,$type,$coordinates]) {
            $service->validateGeometry(['properties'=>['kind'=>$kind], 'geometry'=>compact('type','coordinates')]);
        }
        $this->assertTrue(true);
    }
    public function test_open_polygon_rings_are_rejected(): void {
        $this->expectException(ValidationException::class);
        (new HazardLayerService)->validateGeometry(['properties'=>['kind'=>'hazard'], 'geometry'=>['type'=>'Polygon','coordinates'=>[[[123,10],[124,10],[124,11],[123,11]]]]]);
    }
    public function test_out_of_range_coordinates_are_rejected(): void {
        $this->expectException(ValidationException::class);
        (new HazardLayerService)->validateGeometry(['properties'=>['kind'=>'evacuation_center'], 'geometry'=>['type'=>'Point','coordinates'=>[200,10]]]);
    }
    public function test_route_cannot_be_imported_as_a_hazard_polygon(): void {
        $this->expectException(ValidationException::class);
        (new HazardLayerService)->validateGeometry(['properties'=>['kind'=>'hazard'], 'geometry'=>['type'=>'LineString','coordinates'=>[[123,10],[124,11]]]]);
    }
    public function test_empty_polygon_is_rejected(): void {
        $this->expectException(ValidationException::class);
        (new HazardLayerService)->validateGeometry(['properties'=>['kind'=>'hazard'], 'geometry'=>['type'=>'Polygon','coordinates'=>[]]]);
    }

    public function test_import_persists_and_reads_scoped_maps_without_an_active_event(): void {
        (require database_path('migrations/2026_10_20_000002_create_mapping_hazard_layers.php'))->up();
        $this->mock(\App\Services\Shared\BarangayProfileService::class, function ($mock) {
            $mock->shouldReceive('mapFocus')->andReturn(['barangay_id'=>7]);
        });
        $this->mock(\App\Queries\MappingQuery::class, function ($mock) {
            $mock->shouldReceive('getPurokOptions')->with('7')->andReturn([['purok_id'=>10]]);
        });
        $payload = ['name'=>'Flood assessment', 'source'=>'MDRRMO', 'assessed_at'=>'2026-01-01',
            'geojson'=>['type'=>'FeatureCollection', 'features'=>[['type'=>'Feature',
                'properties'=>['kind'=>'hazard','name'=>'Zone A','hazard_type'=>'Flood','risk_level'=>'high','purok_id'=>10,'individuals'=>25,'families'=>5,'reviewed_by'=>'MDRRMO GIS'],
                'geometry'=>['type'=>'Polygon','coordinates'=>[[[123,10],[124,10],[124,11],[123,10]]]],
            ]]]];
        $response = app(\App\Http\Controllers\Api\HazardLayerController::class)->store(\Illuminate\Http\Request::create('/', 'POST', $payload), new HazardLayerService);
        $this->assertSame(201, $response->getStatusCode());
        $layers = (new HazardLayerService)->layers();
        $this->assertCount(1, $layers);
        $this->assertSame(25, $layers[0]['geojson']['features'][0]['properties']['individuals']);
        \Illuminate\Support\Facades\DB::table('mapping_hazard_layers')->insert(['barangay_id'=>'8','name'=>'Other barangay','source'=>'MDRRMO','assessed_at'=>'2026-01-01','geojson'=>'{}']);
        $this->assertCount(1, (new HazardLayerService)->layers());
        app(\App\Http\Controllers\Api\HazardLayerController::class)->destroy((string)$layers[0]['id'], new HazardLayerService);
        $this->assertCount(0, (new HazardLayerService)->layers());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('mapping_hazard_layers')->count());
    }

    public function test_web_hazard_permissions_follow_committee_roles(): void {
        $policy = new \App\Services\Web\WebAccessPolicy;
        foreach (['chair', 'operations_admin', 'mitigation_prevention', 'preparedness'] as $role) {
            $user = new \App\Models\User(['role'=>$role, 'is_active'=>true]);
            $this->assertTrue($policy->allows($user, 'geospatial_analytics', 'manage_hazards'), $role);
        }
        foreach (['response', 'recovery_rehabilitation', 'rescuer', 'household_resident'] as $role) {
            $user = new \App\Models\User(['role'=>$role, 'is_active'=>true]);
            $this->assertFalse($policy->allows($user, 'geospatial_analytics', 'manage_hazards'), $role);
        }
        $this->assertFalse($policy->allows(new \App\Models\User(['role'=>'chair','is_active'=>false]), 'geospatial_analytics', 'manage_hazards'));
    }
    public function test_authenticated_web_routes_allow_import_and_reject_read_only_roles(): void {
        \Illuminate\Support\Facades\Schema::create('users', function ($table) {
            $table->string('user_id')->primary(); $table->string('role'); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        (require database_path('migrations/2026_10_20_000002_create_mapping_hazard_layers.php'))->up();
        $this->mock(\App\Services\Shared\BarangayProfileService::class, function ($mock) {
            $mock->shouldReceive('mapFocus')->andReturn(['barangay_id'=>7]);
        });
        $this->mock(\App\Queries\MappingQuery::class, function ($mock) {
            $mock->shouldReceive('getPurokOptions')->with('7')->andReturn([['purok_id'=>10]]);
        });
        $payload = ['name'=>'Official flood assessment','source'=>'MDRRMO','assessed_at'=>'2026-01-01',
            'geojson'=>['type'=>'FeatureCollection','features'=>[['type'=>'Feature',
                'properties'=>['kind'=>'hazard','name'=>'Zone A','hazard_type'=>'Flood','risk_level'=>'high','purok_id'=>10,'reviewed_by'=>'GIS officer'],
                'geometry'=>['type'=>'Polygon','coordinates'=>[[[123,10],[124,10],[124,11],[123,10]]]],
            ]]]];
        $chair = \App\Models\User::create(['user_id'=>'CHAIR','role'=>'chair','is_active'=>true]);
        \Laravel\Sanctum\Sanctum::actingAs($chair);
        $id = $this->postJson('/api/v1/map/hazard-layers', $payload)->assertCreated()->json('data.id');
        $this->assertCount(1, (new HazardLayerService)->layers());
        $viewer = \App\Models\User::create(['user_id'=>'VIEWER','role'=>'response','is_active'=>true]);
        \Laravel\Sanctum\Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/map/hazard-layers', $payload)->assertForbidden();
        $this->deleteJson('/api/v1/map/hazard-layers/'.$id)->assertForbidden();
        \Laravel\Sanctum\Sanctum::actingAs($chair);
        $this->deleteJson('/api/v1/map/hazard-layers/'.$id)->assertOk();
        $this->assertCount(0, (new HazardLayerService)->layers());
    }

    public function test_mambaling_has_real_bundled_government_hazards_without_imports(): void {
        $service = new HazardLayerService;
        $layers = $service->governmentLayers(['name'=>'Barangay Mambaling','center'=>['latitude'=>10.29025,'longitude'=>123.88325]]);
        $this->assertCount(2, $layers);
        $this->assertSame('Flood', $layers[0]['hazard_type']);
        $this->assertCount(4, $layers[0]['legend']);
        $this->assertStringContainsString('Very High', $layers[0]['legend'][0]['label']);
        $image = base_path('../frontend-web/public/'.$layers[0]['raster']['path']);
        $this->assertFileExists($image);
        $this->assertSame("\x89PNG\r\n\x1a\n", file_get_contents($image, false, null, 0, 8));
        $this->assertCount(2, $layers[1]['geojson']['features']);
        foreach ($layers[1]['geojson']['features'] as $feature) {
            $this->assertNotEmpty($feature['properties']['source_objectid']);
            $this->assertNull($feature['properties']['individuals']);
            foreach ($feature['geometry']['coordinates'] as $polygon) foreach ($polygon as $ring) foreach ($ring as $point) {
                $this->assertGreaterThanOrEqual(123.8741759, $point[0]);
                $this->assertLessThanOrEqual(123.8875591, $point[0]);
                $this->assertGreaterThanOrEqual(10.2802089, $point[1]);
                $this->assertLessThanOrEqual(10.2948398, $point[1]);
            }
        }
        $this->assertSame([], $service->governmentLayers(['name'=>'Other barangay','center'=>['latitude'=>10.29025,'longitude'=>123.88325]]));
        $this->assertSame([], $service->governmentLayers(['name'=>'Mambaling','center'=>['latitude'=>14.5,'longitude'=>121.0]]));
    }
}
