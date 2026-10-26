<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WeatherWorkspaceResource;
use App\Services\Shared\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use UnexpectedValueException;

class WeatherController extends Controller
{
    private WeatherService $service;

    public function __construct(WeatherService $service)
    {
        $this->service = $service;
    }

    public function workspace(): JsonResponse
    {
        return (new WeatherWorkspaceResource($this->service->workspace()))->response();
    }

    public function index(string $eventId): JsonResponse
    {
        if (! $this->service->eventExists($eventId)) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }
        return (new WeatherWorkspaceResource($this->service->index($eventId)))->response();
    }

    public function refreshWorkspace(): JsonResponse
    {
        return $this->refreshResponse(fn () => $this->service->refreshWorkspace());
    }

    public function refreshEvent(string $eventId): JsonResponse
    {
        if (! $this->service->eventExists($eventId)) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }
        return $this->refreshResponse(fn () => $this->service->refreshEvent($eventId));
    }

    public function officialAdvisories(\App\Services\Shared\OfficialWeatherAdvisoryService $advisories): JsonResponse
    {
        return response()->json(['data' => $advisories->overview()]);
    }

    private function refreshResponse(callable $refresh): JsonResponse
    {
        try {
            $result = $refresh();
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            report($exception);
            return response()->json([
                'message' => 'Weather provider unavailable. The latest saved snapshot is still available. Please try again.',
            ], 503);
        }

        return (new WeatherWorkspaceResource($result['data']))->additional([
            'message' => $result['message'],
        ])->response()->setStatusCode($result['status']);
    }
}
