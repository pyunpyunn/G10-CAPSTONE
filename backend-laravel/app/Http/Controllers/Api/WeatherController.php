<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WeatherWorkspaceResource;
use App\Services\Shared\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function refreshWorkspace(Request $request): JsonResponse
    {
        return (new WeatherWorkspaceResource($this->service->refreshWorkspace()))->additional([
            'message' => 'Weather refresh queued. The latest snapshot will appear when processing finishes.',
        ])->response()->setStatusCode(202);
    }

    public function refreshEvent(Request $request, string $eventId): JsonResponse
    {
        if (! $this->service->eventExists($eventId)) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }
        return (new WeatherWorkspaceResource($this->service->refreshEvent($eventId)))->additional([
            'message' => 'Weather refresh queued. The latest snapshot will appear when processing finishes.',
        ])->response()->setStatusCode(202);
    }
}


