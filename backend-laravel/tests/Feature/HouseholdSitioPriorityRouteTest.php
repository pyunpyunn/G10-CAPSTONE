<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\RescueDispatchController;
use App\Queries\RescueDispatchQuery;
use App\Queries\SitioPriorityQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Tests\TestCase;

class HouseholdSitioPriorityRouteTest extends TestCase
{
    public function test_household_ranking_returns_an_empty_array_without_an_active_event(): void
    {
        $this->withoutMiddleware();
        $this->mock(RescueDispatchQuery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('activeEvent')->once()->andReturn(null);
        });
        $this->mock(SitioPriorityQuery::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ranked');
        });

        $this->getJson('/api/v1/dispatches/sitio-priorities')
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_household_ranking_returns_the_active_events_sitios(): void
    {
        $this->withoutMiddleware();
        $rows = [['sitio_id' => 1, 'sitio' => 'Test Sitio', 'priority_score' => 25]];
        $this->mock(RescueDispatchQuery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('activeEvent')->once()->andReturn((object) ['event_id' => 'event-1']);
        });
        $this->mock(SitioPriorityQuery::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('ranked')->once()->with('event-1')->andReturn($rows);
        });

        $this->getJson('/api/v1/dispatches/sitio-priorities')
            ->assertOk()->assertExactJson(['data' => $rows]);
    }

    public function test_dispatch_detail_still_matches_numeric_ids(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/v1/dispatches/123'));

        $this->assertSame(RescueDispatchController::class.'@show', $route->getActionName());
        $this->assertSame('123', $route->parameter('assignmentId'));
    }

    public function test_dispatch_detail_rejects_non_numeric_ids(): void
    {
        $this->withoutMiddleware();

        $this->getJson('/api/v1/dispatches/not-an-id')->assertStatus(405);
    }
}
