<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\DisasterBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisasterBroadcastController extends Controller
{
    private DisasterBroadcastService $service;

    public function __construct(DisasterBroadcastService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        return $this->service->index();
    }

    public function storeEvent(Request $request): JsonResponse
    {
        return $this->service->storeEvent($request);
    }

    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        return $this->service->updateEvent($request, $eventId);
    }

    public function show(string $eventId): JsonResponse
    {
        return $this->service->show($eventId);
    }

    public function broadcasts(string $eventId): JsonResponse
    {
        return $this->service->broadcasts($eventId);
    }

    public function storeBroadcast(Request $request, string $eventId): JsonResponse
    {
        return $this->service->storeBroadcast($request, $eventId);
    }

    public function storeStatusType(Request $request): JsonResponse
    {
        return $this->service->storeStatusType($request);
    }

    public function destroyStatusType(string $statusKey): JsonResponse
    {
        return $this->service->destroyStatusType($statusKey);
    }

    public function storeDisasterType(Request $request): JsonResponse
    {
        return $this->service->storeDisasterType($request);
    }

    public function destroyDisasterType(int $typeId): JsonResponse
    {
        return $this->service->destroyDisasterType($typeId);
    }
}


