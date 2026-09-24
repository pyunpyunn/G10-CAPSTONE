<?php

namespace App\Services;

use App\Services\WeatherSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WeatherService
{
    private WeatherSnapshotService $weather;

    public function __construct(WeatherSnapshotService $weather)
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
        try {
            $result = $this->weather->saveLatestSnapshot($eventId);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status']);
    }
}

