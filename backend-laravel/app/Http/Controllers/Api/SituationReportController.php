<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SituationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SituationReportController extends Controller
{
    private SituationReportService $service;

    public function __construct(SituationReportService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        return $this->service->index();
    }

    public function eventSummary(string $eventId): JsonResponse
    {
        return $this->service->eventSummary($eventId);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function show(int $sitRepId): JsonResponse
    {
        return $this->service->show($sitRepId);
    }

    public function pdf(int $sitRepId): JsonResponse
    {
        return $this->service->pdf($sitRepId);
    }
}