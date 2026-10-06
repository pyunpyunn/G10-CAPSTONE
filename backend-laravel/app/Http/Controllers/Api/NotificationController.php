<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Resources\NotificationFeedResource;
use App\Services\Web\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    public function index(ListRequest $request): JsonResponse
    {
        return (new NotificationFeedResource($this->service->index($request)))->response();
    }

    public function markRead(Request $request): JsonResponse
    {
        $this->service->markRead($request);
        return response()->json(['message' => 'Notifications marked as read in the current HQ view.', 'data' => ['action' => 'mark_read']]);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        $ids = $this->service->deleteSelected($request);
        return response()->json(['message' => count($ids).' notification(s) hidden from the current HQ view.',
            'data' => ['action' => 'delete_selected', 'notification_ids' => $ids]]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $this->service->clearAll($request);
        return response()->json(['message' => 'All notifications cleared from the current HQ view.', 'data' => ['action' => 'clear_all']]);
    }
}


