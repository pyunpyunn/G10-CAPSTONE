<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArchiveController extends Controller
{
    private ArchiveService $service;

    public function __construct(ArchiveService $service)
    {
        $this->service = $service;
    }

    public function disasterEvents(Request $request): JsonResponse
    {
        return $this->service->disasterEvents($request);
    }

    public function householdStatusLogs(Request $request): JsonResponse
    {
        return $this->service->householdStatusLogs($request);
    }

    public function dispatchLogs(Request $request): JsonResponse
    {
        return $this->service->dispatchLogs($request);
    }

    public function resourceRequests(Request $request): JsonResponse
    {
        return $this->service->resourceRequests($request);
    }

    public function situationReports(Request $request): JsonResponse
    {
        return $this->service->situationReports($request);
    }

    public function export(Request $request): Response|JsonResponse
    {
        return $this->service->export($request);
    }
}