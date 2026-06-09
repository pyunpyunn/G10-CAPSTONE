<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ResourceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceRequestController extends Controller
{
    private ResourceRequestService $service;

    public function __construct(ResourceRequestService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function show(string $requestId): JsonResponse
    {
        return $this->service->show($requestId);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function validateResource(Request $request, string $requestId): JsonResponse
    {
        return $this->service->validateResource($request, $requestId);
    }

    public function forward(Request $request, string $requestId): JsonResponse
    {
        return $this->service->forward($request, $requestId);
    }

    public function returnRequest(Request $request, string $requestId): JsonResponse
    {
        return $this->service->returnRequest($request, $requestId);
    }
}