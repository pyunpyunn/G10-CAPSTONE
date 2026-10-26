<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\DashboardService;
use App\Http\Resources\DashboardSnapshotResource;
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
        return (new DashboardSnapshotResource($this->service->index()))->response();
    }

    /** Lightweight first-paint payload for the dashboard shell. */
    public function summary(): JsonResponse
    {
        return (new DashboardSnapshotResource($this->service->summary()))->response();
    }

    public function dispatch(): JsonResponse
    {
        return response()->json(['data' => $this->service->dispatch()]);
    }

    public function weather(): JsonResponse
    {
        return response()->json(['data' => $this->service->weather()]);
    }

    public function requests(): JsonResponse
    {
        return response()->json(['data' => $this->service->requests()]);
    }

    public function activity(): JsonResponse
    {
        return response()->json(['data' => $this->service->activity()]);
    }

    public function closeActiveEvent(Request $request): JsonResponse
    {
        $result = $this->service->closeActiveEvent($request);
        if (! $result) return response()->json(['message' => 'There is no active disaster event to close.'], 404);
        return response()->json([
            'message' => 'Active event closed, saved to the Disaster Event Log, and queued for SitRep/archive.',
            'data' => [
                'closed_event' => $result['closed_event'],
                'dashboard' => new DashboardSnapshotResource($result['dashboard']),
            ],
        ]);
    }
}



