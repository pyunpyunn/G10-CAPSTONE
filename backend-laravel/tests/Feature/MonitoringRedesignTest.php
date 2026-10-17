<?php

namespace Tests\Feature;

use App\Presenters\WeatherSnapshotPresenter;
use App\Queries\MappingQuery;
use App\Services\Shared\BarangayProfileService;
use App\Services\Shared\RoutingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Tests\TestCase;

class MonitoringRedesignTest extends TestCase
{
    private function mapContext(?object $event, array $points = []): void
    {
        $this->withoutMiddleware();
        $this->mock(BarangayProfileService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('mapFocus')->andReturn(['barangay_id' => 1, 'boundary' => null]);
        });
        $this->mock(MappingQuery::class, function (MockInterface $mock) use ($event, $points): void {
            $mock->shouldReceive('getActiveEvent')->once()->andReturn($event);
            if ($event) $mock->shouldReceive('getHouseholdGeotags')->once()
                ->withArgs(fn (Request $r, $id, $barangay) => $r->query('household_id') === 'HH-1' && $id === 'EVT-1' && $barangay === 1)
                ->andReturn($points);
        });
    }

    public function test_household_route_uses_the_saved_office_and_household_geotag(): void
    {
        $this->mapContext((object) ['event_id' => 'EVT-1'], [['household_id' => 'HH-1', 'latitude' => 10.3, 'longitude' => 123.9]]);
        config(['rescue_office.latitude' => 10.29, 'rescue_office.longitude' => 123.88, 'rescue_office.name' => 'Registered office']);
        $this->mock(RoutingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('drivingRoute')->once()->with(10.29, 123.88, 10.3, 123.9)->andReturn([
                'distance_km' => 2.4, 'duration_min' => 8,
                'coordinates' => [['lat' => 10.29, 'lng' => 123.88], ['lat' => 10.3, 'lng' => 123.9]],
            ]);
        });
        $this->getJson('/api/v1/map/households/HH-1/route?latitude=0&longitude=0')->assertOk()
            ->assertJsonPath('data.distance_km', 2.4)->assertJsonPath('data.origin_name', 'Registered office')
            ->assertJsonPath('data.coordinates.0', [10.29, 123.88]);
    }

    public function test_household_route_is_unavailable_in_standby(): void
    {
        $this->mapContext(null);
        $this->getJson('/api/v1/map/households/HH-1/route')->assertStatus(409);
    }

    public function test_household_route_cannot_use_a_household_outside_the_current_barangay(): void
    {
        $this->mapContext((object) ['event_id' => 'EVT-1']);
        $this->getJson('/api/v1/map/households/HH-1/route')->assertNotFound();
    }

    public function test_route_provider_failure_does_not_fabricate_a_route(): void
    {
        $this->mapContext((object) ['event_id' => 'EVT-1'], [['household_id' => 'HH-1', 'latitude' => 10.3, 'longitude' => 123.9]]);
        config(['rescue_office.latitude' => 10.29, 'rescue_office.longitude' => 123.88]);
        $this->mock(RoutingService::class, fn (MockInterface $m) => $m->shouldReceive('drivingRoute')->once()->andReturn(null));
        $this->getJson('/api/v1/map/households/HH-1/route')->assertStatus(503)->assertJsonMissingPath('data.coordinates');
    }

    public function test_weather_snapshots_expose_unambiguous_timestamps_and_real_zero_values(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Manila'));
        try {
            $result = app(WeatherSnapshotPresenter::class)->formatWeatherLog((object) [
                'weather_log_id' => 1, 'disaster_id' => null, 'source_name' => 'Provider', 'source_url' => 'https://example.test',
                'condition_name' => 'Rainy', 'temperature' => 0, 'humidity' => 0, 'rainfall_mm' => 0,
                'wind_speed' => 0, 'wind_direction' => 'N', 'advisory_title' => null, 'advisory_text' => null,
                'observed_at' => '2026-10-15 11:45:00', 'created_at' => '2026-10-15 11:45:00',
                'raw_payload' => ['current' => [], 'daily' => ['time' => ['2026-10-15']]],
            ]);
            $this->assertSame('2026-10-15T11:45:00+08:00', $result['observed_at_iso']);
            $this->assertSame(15, $result['observed_age_minutes']);
            $this->assertFalse($result['is_stale']);
            $this->assertSame(0.0, $result['temperature']);
            $this->assertNull($result['wind_gusts']);
            $this->assertNull($result['daily_forecast'][0]['rain_probability']);
            $this->assertSame('Not recorded', $result['daily_forecast'][0]['condition_name']);
            $this->assertSame('2026-10-15T00:00:00+08:00', $result['daily_forecast'][0]['date_iso']);
        } finally { Carbon::setTestNow(); }
    }
}
