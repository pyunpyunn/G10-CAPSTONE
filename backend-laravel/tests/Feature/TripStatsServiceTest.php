<?php

namespace Tests\Feature;

use App\Services\Navigation\TripStatsService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TripStatsServiceTest extends TestCase
{
    public function test_one_kilometer_walk_is_within_three_percent_and_late_order_is_identical(): void
    {
        $originLatitude = 10.29;
        $longitude = 123.88;
        $latitudeStep = 10 / 111195;
        $start = CarbonImmutable::parse('2026-10-18T08:00:00Z');
        $points = [];

        for ($index = 0; $index <= 100; $index++) {
            $points[] = (object) [
                'id' => $index + 1,
                'lat' => $originLatitude + ($latitudeStep * $index),
                'lng' => $longitude,
                'accuracy' => 5,
                'speed' => 1,
                'recorded_at' => $start->addSeconds($index * 10)->toDateTimeString(),
            ];
        }

        $service = app(TripStatsService::class);
        $profile = config('navigation.trip_stats.profiles.pedestrian');
        $live = $service->calculate($points, $profile);
        $late = $service->calculate(array_reverse($points), $profile);

        $this->assertEqualsWithDelta(1000, $live['distance_m'], 30);
        $this->assertSame($live, $late);
        $this->assertSame(1000, $live['moving_s']);
        $this->assertSame(0, $live['stopped_s']);
    }

    public function test_low_quality_fixes_and_impossible_speed_jumps_do_not_add_distance(): void
    {
        $points = [
            (object) ['id' => 1, 'lat' => 10.0, 'lng' => 123.0, 'accuracy' => 5, 'speed' => 1, 'recorded_at' => '2026-10-18 08:00:00'],
            (object) ['id' => 2, 'lat' => 10.001, 'lng' => 123.0, 'accuracy' => 5, 'speed' => 1, 'recorded_at' => '2026-10-18 08:00:01'],
            (object) ['id' => 3, 'lat' => 10.00001, 'lng' => 123.0, 'accuracy' => 5, 'speed' => 0, 'recorded_at' => '2026-10-18 08:00:10'],
            (object) ['id' => 4, 'lat' => 10.0001, 'lng' => 123.0, 'accuracy' => 45, 'speed' => 0, 'recorded_at' => '2026-10-18 08:00:20'],
        ];

        $result = app(TripStatsService::class)->calculate($points, config('navigation.trip_stats.profiles.pedestrian'));

        $this->assertSame(2, $result['accepted_points']);
        $this->assertSame(0, $result['distance_m']);
    }
}