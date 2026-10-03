<?php

namespace App\Jobs;

use App\Services\Shared\WeatherSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshWeatherSnapshot implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public ?string $eventId) {}

    public function handle(WeatherSnapshotService $weather): void
    {
        $weather->saveLatestSnapshot($this->eventId);
    }
}
