<?php

namespace App\Services\Web;

use App\Presenters\DisasterBroadcastPresenter;
use App\Queries\DisasterBroadcastQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisasterBroadcastService
{
    public function __construct(
        private DisasterBroadcastQuery $query,
        private DisasterBroadcastPresenter $presenter,
        private DisasterBroadcastWorkflow $workflow,
    ) {}

    public function index(): JsonResponse
    {
        $event = $this->query->getActiveEvent();
        $eventId = $event?->event_id;
        $broadcasts = $eventId ? $this->query->getBroadcastsForEvent($eventId) : [];

        return response()->json(['data' => $this->query->getWorkspacePayload($event, $broadcasts)]);
    }

    public function show(string $eventId): JsonResponse
    {
        $event = $this->query->findEvent($eventId);
        if (! $event) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }

        return response()->json(['data' => $this->query->getWorkspacePayload($event, $this->query->getBroadcastsForEvent($eventId))]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        return $this->workflow->storeEvent($request);
    }

    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        return $this->workflow->updateEvent($request, $eventId);
    }

    public function broadcasts(string $eventId): JsonResponse
    {
        if (! $this->query->findEvent($eventId)) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }

        return response()->json(['data' => ['broadcasts' => $this->query->getBroadcastsForEvent($eventId)]]);
    }

    public function storeBroadcast(Request $request, string $eventId): JsonResponse
    {
        return $this->workflow->storeBroadcast($request, $eventId);
    }
}







