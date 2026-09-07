<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        return $this->service->index();
    }

    /** Lightweight first-paint payload for the dashboard shell. */
    public function summary(): JsonResponse
    {
        return $this->service->summary();
    }

    public function dispatch(): JsonResponse
    {
        return $this->service->dispatch();
    }

    public function weather(): JsonResponse
    {
        return $this->service->weather();
    }

    public function requests(): JsonResponse
    {
        return $this->service->requests();
    }

    public function activity(): JsonResponse
    {
        return $this->service->activity();
    }

    public function closeActiveEvent(Request $request): JsonResponse
    {
        return $this->service->closeActiveEvent($request);
    }
}
