<?php

namespace App\Services\Shared;

use App\Jobs\RefreshWeatherSnapshot;
use App\Services\Shared\WeatherSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherService
{
    private \App\Services\Shared\WeatherSnapshotService $weather;

    public function __construct(\App\Services\Shared\WeatherSnapshotService $weather)
    {
        $this->weather = $weather;
    }

    public function workspace(): JsonResponse
    {
        return response()->json([
            'data' => $this->weather->pageData(null),
        ]);
    }

    public function index(string $eventId): JsonResponse
    {
        if (! $this->weather->findEvent($eventId)) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'logs' => $this->weather->getWeatherLogs($eventId),
            ],
        ]);
    }

    public function refreshWorkspace(Request $request): JsonResponse
    {
        return $this->saveRefresh(null);
    }

    public function refreshEvent(Request $request, string $eventId): JsonResponse
    {
        if (! $this->weather->findEvent($eventId)) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        return $this->saveRefresh($eventId);
    }

    private function saveRefresh(?string $eventId): JsonResponse
    {
        RefreshWeatherSnapshot::dispatch($eventId)->onConnection('operations_outbox')->onQueue('operations');
        return response()->json([
            'message' => 'Weather refresh queued. The latest snapshot will appear when processing finishes.',
            'data' => $this->weather->pageData($eventId),
        ], 202);
    }
}








