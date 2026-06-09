<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MappingController extends Controller
{
    private MappingService $service;

    public function __construct(MappingService $service)
    {
        $this->service = $service;
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->service->overview($request);
    }

    public function householdGeotags(Request $request): JsonResponse
    {
        return $this->service->householdGeotags($request);
    }

    public function evacuationSites(Request $request): JsonResponse
    {
        return $this->service->evacuationSites($request);
    }

    public function dispatchRoutes(Request $request): JsonResponse
    {
        return $this->service->dispatchRoutes($request);
    }
}