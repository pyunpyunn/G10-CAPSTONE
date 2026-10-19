<?php

namespace Tests\Feature;

use App\Services\Shared\ValhallaRoutingService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ValhallaRoutingServiceTest extends TestCase
{
    public function test_it_requests_each_supported_profile_and_trims_maneuvers(): void
    {
        config(['navigation.valhalla_url' => 'http://valhalla:8002']);
        Http::fake([
            'http://valhalla:8002/route' => Http::response([
                'trip' => [
                    'status' => 0,
                    'summary' => ['length' => 1.82, 'time' => 153],
                    'legs' => [[
                        'shape' => 'encoded-polyline6',
                        'maneuvers' => [
                            ['instruction' => 'Head west.', 'length' => 0.42, 'type' => 1, 'begin_shape_index' => 0, 'verbal_pre_transition_instruction' => 'unused'],
                            ['instruction' => 'Turn left.', 'length' => 1.40, 'type' => 15, 'begin_shape_index' => 5],
                        ],
                    ]],
                ],
            ]),
        ]);

        $service = app(ValhallaRoutingService::class);
        foreach (['auto', 'pedestrian'] as $profile) {
            $route = $service->routeBetween(
                ['latitude' => 10.2953733, 'longitude' => 123.8954363],
                ['latitude' => 10.2915837, 'longitude' => 123.8847532],
                $profile,
            );

            $this->assertSame($profile, $route['profile']);
            $this->assertSame('encoded-polyline6', $route['polyline6']);
            $this->assertSame(1820, $route['distance_m']);
            $this->assertSame(153, $route['duration_s']);
            $this->assertSame([
                ['instruction' => 'Head west.', 'distance_m' => 420, 'maneuver_type' => 1, 'polyline_index' => 0],
                ['instruction' => 'Turn left.', 'distance_m' => 1400, 'maneuver_type' => 15, 'polyline_index' => 5],
            ], $route['steps']);
        }

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'http://valhalla:8002/route'
            && in_array($request['costing'], ['auto', 'pedestrian'], true)
            && $request['shape_format'] === 'polyline6');
    }

    public function test_it_rejects_profiles_not_supported_by_the_offline_navigation_spec(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ValhallaRoutingService::class)->routeBetween(
            ['latitude' => 10.29, 'longitude' => 123.88],
            ['latitude' => 10.29, 'longitude' => 123.89],
            'bicycle',
        );
    }
}