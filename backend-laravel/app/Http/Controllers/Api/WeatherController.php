<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WeatherController extends Controller
{
    private WeatherSnapshotService $weather;

    public function __construct(WeatherSnapshotService $weather)
    {
        $this->weather = $weather;
    }

    public function workspace(): JsonResponse
    {
        $activeEvent = $this->weather->getActiveEvent();

        return response()->json([
            'data' => $this->weather->pageData($activeEvent?->event_id),
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
        $activeEvent = $this->weather->getActiveEvent();

        return $this->saveRefresh($activeEvent?->event_id);
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

