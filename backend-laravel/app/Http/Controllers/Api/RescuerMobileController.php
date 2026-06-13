<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RescuerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescuerMobileController extends Controller
{
    private RescuerMobileService $service;

    public function __construct(RescuerMobileService $service)
    {
        $this->service = $service;
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->service->profile($request);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return $this->service->updateProfile($request);
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->service->overview($request);
    }

    public function assignments(Request $request): JsonResponse
    {
        return $this->service->assignments($request);
    }

    public function assignment(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->assignment($request, $assignmentId);
    }

    public function updateAssignmentStatus(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->updateAssignmentStatus($request, $assignmentId);
    }

    public function storeLocation(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->storeLocation($request, $assignmentId);
    }

    public function fieldReports(Request $request): JsonResponse
    {
        return $this->service->fieldReports($request);
    }

    public function storeFieldReport(Request $request): JsonResponse
    {
        return $this->service->storeFieldReport($request);
    }

    public function resourceRequests(Request $request): JsonResponse
    {
        return $this->service->resourceRequests($request);
    }

    public function storeResourceRequest(Request $request): JsonResponse
    {
        return $this->service->storeResourceRequest($request);
    }

    public function cancelResourceRequest(Request $request, string $requestId): JsonResponse
    {
        return $this->service->cancelResourceRequest($request, $requestId);
    }

    public function radioFeed(Request $request): JsonResponse
    {
        return $this->service->radioFeed($request);
    }

    public function startRadioTransmission(Request $request): JsonResponse
    {
        return $this->service->startRadioTransmission($request);
    }

    public function heartbeatRadioTransmission(Request $request): JsonResponse
    {
        return $this->service->heartbeatRadioTransmission($request);
    }

    public function stopRadioTransmission(Request $request): JsonResponse
    {
        return $this->service->stopRadioTransmission($request);
    }

    public function storeRadioClip(Request $request): JsonResponse
    {
        return $this->service->storeRadioClip($request);
    }

    public function storeRadioSignal(Request $request): JsonResponse
    {
        return $this->service->storeRadioSignal($request);
    }
}
