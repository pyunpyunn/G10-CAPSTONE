<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function markRead(Request $request): JsonResponse
    {
        return $this->service->markRead($request);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        return $this->service->deleteSelected($request);
    }

    public function clearAll(Request $request): JsonResponse
    {
        return $this->service->clearAll($request);
    }
}