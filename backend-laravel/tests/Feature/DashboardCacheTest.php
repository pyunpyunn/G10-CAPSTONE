<?php

namespace Tests\Feature;

use App\Data\DashboardSnapshot;
use App\Http\Resources\DashboardSnapshotResource;
use App\Queries\DashboardQuery;
use App\Services\Shared\BarangayProfileService;
use App\Services\Web\DashboardEventClosureWorkflow;
use App\Services\Web\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    public function test_full_dashboard_survives_serialized_cache_hits_and_legacy_object_entries(): void
    {
        $this->verifyCacheHits(true);
    }

    public function test_summary_survives_serialized_cache_hits(): void
    {
        $this->verifyCacheHits(false);
    }

    private function verifyCacheHits(bool $full): void
    {
        config([
            'realtime.read_cache' => true,
            'realtime.cache_store' => 'dashboard-test',
            'cache.serializable_classes' => false,
            'cache.stores.dashboard-test' => ['driver' => 'array', 'serialize' => true],
        ]);

        $event = (object) ['event_id' => 'EVT-1', 'name' => 'Flood', 'started_at' => '2026-10-09 12:00:00'];
        $broadcast = (object) ['broadcast_title' => 'Alert', 'scope_type' => 'barangay', 'sent_at' => '2026-10-09 12:00:00'];
        $query = Mockery::mock(DashboardQuery::class);
        $query->shouldReceive('getActiveEvent')->once()->andReturn($event);
        $query->shouldReceive('getHouseholdSummary')->once()->with('EVT-1')->andReturn(['total' => 4]);
        $query->shouldReceive('latestBroadcast')->once()->with('EVT-1')->andReturn($broadcast);
        if ($full) {
            $query->shouldReceive('getDispatchSummary')->once()->with('EVT-1')->andReturn(['total' => 2]);
            $query->shouldReceive('getWeatherSnapshot')->once()->with('EVT-1')->andReturn(null);
            $query->shouldReceive('getRequestSummary')->once()->andReturn(['pending' => 1]);
            $query->shouldReceive('getMapSummary')->once()->with('EVT-1', ['total' => 4])->andReturn(['evacuation_sites' => 1]);
            $query->shouldReceive('getRecentActivity')->once()->with('EVT-1')->andReturn([]);
        }
        $workflow = Mockery::mock(DashboardEventClosureWorkflow::class);
        $workflow->shouldReceive('releaseEndedEventReferences')->twice()->andReturn(0);
        $profile = Mockery::mock(BarangayProfileService::class);
        $profile->shouldReceive('current')->once()->andReturn(['barangay_id' => 396]);

        $name = $full ? 'dashboard-full' : 'dashboard-summary';
        $oldKey = 'realtime:'.config('app.env').':'.config('realtime.connection').':revision:initial:'.$name;
        $cache = Cache::store('dashboard-test');
        $cache->put($oldKey, new DashboardSnapshot($event, $broadcast, [], []), 60);
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $cache->get($oldKey));

        $service = new DashboardService($query, $workflow, $profile);
        $fresh = $full ? $service->index() : $service->summary();
        $cached = $full ? $service->index() : $service->summary();
        $this->assertInstanceOf(DashboardSnapshot::class, $cached);
        $this->assertEquals($fresh, $cached);
        $this->assertSame('EVT-1', $cached->event->event_id);
        $this->assertSame('Alert', $cached->latestBroadcast->broadcast_title);
        $this->assertSame(
            (new DashboardSnapshotResource($fresh))->resolve(Request::create('/dashboard')),
            (new DashboardSnapshotResource($cached))->resolve(Request::create('/dashboard')),
        );
        $this->assertIsArray($cache->get($oldKey.':v2'));
        $this->assertIsArray($cache->get($oldKey.':v2')['event']);
        $this->assertIsArray($cache->get($oldKey.':v2')['latestBroadcast']);
    }
}
