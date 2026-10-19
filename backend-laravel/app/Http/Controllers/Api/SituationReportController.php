<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Web\SituationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SituationReportController extends Controller
{
    private SituationReportService $service;

    public function __construct(SituationReportService $service)
    {
        $this->service = $service;
    }

    public function index(ListRequest $request): JsonResponse
    {
        return $this->service->index();
    }

    public function eventSummary(string $eventId, Request $request): JsonResponse
    {
        $data = $request->validate(['sitio_id' => 'nullable|string|max:120']);
        return $this->service->eventSummary($eventId, $data['sitio_id'] ?? null);
    }

    public function sitios(): JsonResponse
    {
        return $this->service->sitios();
    }

    public function sync(Request $request): JsonResponse
    {
        return $this->service->sync($request);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function show(int $sitRepId): JsonResponse
    {
        return $this->service->show($sitRepId);
    }

    public function submission(Request $request, int $sitRepId): JsonResponse
    {
        return $this->service->recordSubmission($request, $sitRepId);
    }

    public function pdf(int $sitRepId): JsonResponse
    {
        return $this->service->pdf($sitRepId);
    }
}


