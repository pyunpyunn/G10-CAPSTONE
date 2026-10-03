<?php

namespace App\Services\Shared;

use App\Jobs\RefreshWeatherSnapshot;
use App\Services\Shared\WeatherSnapshotService;

class WeatherService
{
    private \App\Services\Shared\WeatherSnapshotService $weather;

    public function __construct(\App\Services\Shared\WeatherSnapshotService $weather)
    {
        $this->weather = $weather;
    }

    public function workspace(): array
    {
        return $this->weather->pageData(null);
    }

    public function eventExists(string $eventId): bool
    {
        return (bool) $this->weather->findEvent($eventId);
    }

    public function index(string $eventId): array
    {
        return ['logs' => $this->weather->getWeatherLogs($eventId)];
    }

    public function refreshWorkspace(): array
    {
        return $this->saveRefresh(null);
    }

    public function refreshEvent(string $eventId): array
    {
        return $this->saveRefresh($eventId);
    }

    private function saveRefresh(?string $eventId): array
    {
        RefreshWeatherSnapshot::dispatch($eventId)->onConnection('operations_outbox')->onQueue('operations');
        return $this->weather->pageData($eventId);
    }
}








