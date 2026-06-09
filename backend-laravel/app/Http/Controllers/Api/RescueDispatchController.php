<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RescueDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescueDispatchController extends Controller
{
    private RescueDispatchService $service;

    public function __construct(RescueDispatchService $service)
    {
        $this->service = $service;
    }

    public function teams(): JsonResponse
    {
        return $this->service->teams();
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function show(int $assignmentId): JsonResponse
    {
        return $this->service->show($assignmentId);
    }

    public function update(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->update($request, $assignmentId);
    }

    public function complete(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->complete($request, $assignmentId);
    }

    public function updateLocation(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->updateLocation($request, $assignmentId);
    }
}