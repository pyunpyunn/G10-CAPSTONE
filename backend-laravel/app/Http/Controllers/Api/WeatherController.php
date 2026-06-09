<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
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
        return $this->service->workspace();
    }

    public function index(string $eventId): JsonResponse
    {
        return $this->service->index($eventId);
    }

    public function refreshWorkspace(Request $request): JsonResponse
    {
        return $this->service->refreshWorkspace($request);
    }

    public function refreshEvent(Request $request, string $eventId): JsonResponse
    {
        return $this->service->refreshEvent($request, $eventId);
    }
}