<?php

namespace Tests\Feature;

use App\Services\Shared\RoutingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingServiceTest extends TestCase
{
    public function test_no_segment_falls_back_to_the_nearest_of_bounded_road_candidates(): void
    {
        config([
            'services.osrm.base_url' => 'https://routing.test',
            'services.osrm.ssl_verify' => true,
        ]);
        $routeRequests = [];
        Http::fake(function ($request) use (&$routeRequests) {
            if (str_contains($request->url(), '/nearest/')) {
                return Http::response([
                    'code' => 'Ok',
                    'waypoints' => [
                        ['location' => [123.882, 10.292], 'distance' => 45.0],
                        ['location' => [123.881, 10.291], 'distance' => 12.0],
                        ['location' => [123.883, 10.293], 'distance' => 20.0],
                    ],
                ]);
            }

            $routeRequests[] = $request->url();
            if (count($routeRequests) === 1) {
                return Http::response(['code' => 'NoSegment'], 400);
            }

            return Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 1500,
                    'duration' => 240,
                    'geometry' => ['coordinates' => [[123.881, 10.291], [123.9, 10.3]]],
                    'legs' => ['steps' => []],
                ]],
            ]);
        });

        $route = app(RoutingService::class)->drivingRoute(10.29, 123.88, 10.3, 123.9);

        $this->assertNotNull($route);
        $this->assertSame(2, count($routeRequests));
        $this->assertStringContainsString('/route/v1/driving/123.881,10.291;123.9,10.3', $routeRequests[1]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/nearest/v1/driving/123.88,10.29')
            && $request['number'] === 5
            && $request['radiuses'] === 500);
    }

    public function test_routing_provider_unavailability_does_not_trigger_nearest_road_fallback(): void
    {
        config([
            'services.osrm.base_url' => 'https://routing.test',
            'services.osrm.ssl_verify' => true,
        ]);
        Http::fake(['*' => Http::response([], 503)]);

        $route = app(RoutingService::class)->drivingRoute(10.29, 123.88, 10.3, 123.9);

        $this->assertNull($route);
        Http::assertSentCount(1);
    }
}
