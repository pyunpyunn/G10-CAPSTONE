<?php

namespace App\Services\Navigation;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TripStatsService
{
    public function recompute(int $tripId): array
    {
        $trip = DB::table('dispatch_trips')->where('id', $tripId)->first();
        if (! $trip) {
            throw new RuntimeException('Dispatch trip was not found.');
        }

        $profile = config('navigation.trip_stats.profiles.'.$trip->profile);
        if (! is_array($profile)) {
            throw new RuntimeException('Trip profile has no distance thresholds.');
        }

        $points = DB::table('trip_points')->where('trip_id', $tripId)
            ->orderBy('recorded_at')->orderBy('id')->get();
        $stats = $this->calculate($points, $profile);

        DB::table('dispatch_trips')->where('id', $tripId)->update([
            'distance_m' => $stats['distance_m'],
            'moving_s' => $stats['moving_s'],
            'stopped_s' => $stats['stopped_s'],
            'avg_speed_ms' => $stats['avg_speed_ms'],
            'updated_at' => now(),
        ]);

        return $stats;
    }

    public function calculate(iterable $points, array $profile): array
    {
        $points = collect($points)->sort(fn ($left, $right): int => [
            strtotime((string) $left->recorded_at), (int) ($left->id ?? 0),
        ] <=> [
            strtotime((string) $right->recorded_at), (int) ($right->id ?? 0),
        ]);
        $accepted = [];
        $maxAccuracy = (float) config('navigation.trip_stats.max_accuracy_m', 30);
        $maxSpeed = (float) ($profile['max_speed_ms'] ?? 0);
        $minStep = (float) ($profile['min_step_m'] ?? 0);
        $maxGap = max(1, (int) config('navigation.trip_stats.max_gap_s', 120));

        foreach ($points as $point) {
            if ($point->accuracy !== null && (float) $point->accuracy > $maxAccuracy) {
                continue;
            }
            if ($point->speed !== null && (float) $point->speed > $maxSpeed) {
                continue;
            }

            $current = [
                'lat' => (float) $point->lat,
                'lng' => (float) $point->lng,
                'at' => strtotime((string) $point->recorded_at),
            ];
            $previous = $accepted === [] ? null : $accepted[array_key_last($accepted)];
            if ($previous) {
                $elapsed = $current['at'] - $previous['at'];
                if ($elapsed <= 0) {
                    continue;
                }
                $step = $this->haversineMeters($previous['lat'], $previous['lng'], $current['lat'], $current['lng']);
                if ($step / $elapsed > $maxSpeed) {
                    continue;
                }
                $current['step_m'] = $step;
                $current['elapsed_s'] = min($elapsed, $maxGap);
            } else {
                $current['step_m'] = 0.0;
                $current['elapsed_s'] = 0;
            }
            $accepted[] = $current;
        }

        $distance = 0.0;
        $moving = 0;
        $stopped = 0;
        foreach (array_slice($accepted, 1) as $point) {
            if ($point['step_m'] >= $minStep) {
                $distance += $point['step_m'];
                $moving += $point['elapsed_s'];
            } else {
                $stopped += $point['elapsed_s'];
            }
        }

        $distanceM = (int) round($distance);

        return [
            'distance_m' => $distanceM,
            'moving_s' => $moving,
            'stopped_s' => $stopped,
            'avg_speed_ms' => $moving > 0 ? round($distance / $moving, 3) : null,
            'accepted_points' => count($accepted),
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($lat2 - $lat1);
        $longitudeDelta = deg2rad($lng2 - $lng1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }
}