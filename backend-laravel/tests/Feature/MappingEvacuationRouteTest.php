<?php
namespace Tests\Feature;
use App\Queries\MappingQuery;
use App\Services\Shared\BarangayProfileService;
use App\Services\Shared\RoutingService;
use App\Services\Web\MappingService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MappingEvacuationRouteTest extends TestCase
{
    private function prepareSites(array $sites): void {
        config(['rescue_office'=>['name'=>'Barangay Mambaling Hall','latitude'=>10.292222,'longitude'=>123.876389]]);
        $this->mock(BarangayProfileService::class, function ($mock) {
            $mock->shouldReceive('mapFocus')->andReturn(['barangay_id'=>396,'boundary'=>null]);
        });
        $this->mock(MappingQuery::class, function ($mock) use ($sites) {
            $mock->shouldReceive('getActiveEvent')->andReturn(null);
            $mock->shouldReceive('getEvacuationSites')->with(null)->andReturn($sites);
        });
    }
    public function test_selected_center_gets_road_route_from_barangay_hall_with_kilometers(): void {
        $this->prepareSites([['id'=>3,'name'=>'Center A','latitude'=>10.29,'longitude'=>123.88]]);
        $this->mock(RoutingService::class, function ($mock) {
            $mock->shouldReceive('drivingRoute')->once()->with(10.292222,123.876389,10.29,123.88)->andReturn([
                'distance_km'=>1.25,'duration_min'=>4,'coordinates'=>[['lat'=>10.292222,'lng'=>123.876389],['lat'=>10.29,'lng'=>123.88]],
            ]);
        });
        $route = app(MappingService::class)->evacuationRoute('3');
        $this->assertSame('Barangay Mambaling Hall',$route['origin_name']);
        $this->assertSame('Center A',$route['destination_name']);
        $this->assertSame(1.25,$route['distance_km']);
        $this->assertSame([[10.292222,123.876389],[10.29,123.88]],$route['coordinates']);
    }
    public function test_unknown_center_is_rejected_without_calling_router(): void {
        $this->prepareSites([]);
        $this->mock(RoutingService::class)->shouldNotReceive('drivingRoute');
        try { app(MappingService::class)->evacuationRoute('999'); $this->fail('Expected missing center'); }
        catch (HttpException $exception) { $this->assertSame(404,$exception->getStatusCode()); }
    }
    public function test_router_outage_returns_service_unavailable_instead_of_fake_route(): void {
        $this->prepareSites([['id'=>3,'name'=>'Center A','latitude'=>10.29,'longitude'=>123.88]]);
        $this->mock(RoutingService::class)->shouldReceive('drivingRoute')->once()->andReturn(null);
        try { app(MappingService::class)->evacuationRoute('3'); $this->fail('Expected router failure'); }
        catch (HttpException $exception) { $this->assertSame(503,$exception->getStatusCode()); }
    }
}
