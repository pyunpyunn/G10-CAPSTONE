<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RescuerMobileOverviewResource;
use App\Http\Resources\RescuerMobileAssignmentDetailResource;
use App\Http\Resources\RescuerMobileAssignmentListResource;
use App\Http\Resources\RescuerMobileFieldReportsResource;
use App\Http\Resources\RescuerMobileAdminFieldReportsResource;
use App\Http\Resources\RescuerMobileCheckInsResource;
use App\Http\Resources\RescuerMobileResourceRequestsResource;
use App\Http\Resources\RescuerMobileRadioFeedResource;
use App\Http\Resources\RescuerMobileProfileResource;
use App\Services\Mobile\RescuerMobileService;
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
        return (new RescuerMobileProfileResource($this->service->profile($request)))->response();
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $result = $this->service->updateProfile($request);

        return $result instanceof JsonResponse
            ? $result
            : (new RescuerMobileProfileResource($result))->response();
    }

    public function overview(Request $request): JsonResponse
    {
        return (new RescuerMobileOverviewResource($this->service->overview($request)))->response();
    }

    public function assignments(Request $request): JsonResponse
    {
        return (new RescuerMobileAssignmentListResource($this->service->assignments($request)))->response();
    }

    public function assignment(Request $request, int $assignmentId): JsonResponse
    {
        $result = $this->service->assignment($request, $assignmentId);

        return $result instanceof JsonResponse
            ? $result
            : (new RescuerMobileAssignmentDetailResource($result))->response();
    }

    public function updateAssignmentStatus(Request $request, int $assignmentId): JsonResponse
    {
        $result = $this->service->updateAssignmentStatus($request, $assignmentId);

        return $result instanceof JsonResponse
            ? $result
            : (new RescuerMobileAssignmentDetailResource($result))->response();
    }

    public function storeLocation(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->storeLocation($request, $assignmentId);
    }

    public function fieldReports(Request $request): JsonResponse
    {
        return (new RescuerMobileFieldReportsResource($this->service->fieldReports($request)))->response();
    }

    public function storeFieldReport(Request $request): JsonResponse
    {
        return $this->service->storeFieldReport($request);
    }

    public function fieldReportsAdmin(Request $request): JsonResponse
    {
        return (new RescuerMobileAdminFieldReportsResource($this->service->fieldReportsAdmin($request)))->response();
    }

    public function checkIns(Request $request): JsonResponse
    {
        return (new RescuerMobileCheckInsResource($this->service->checkIns($request)))->response();
    }

    public function storeCheckIn(Request $request): JsonResponse
    {
        return $this->service->storeCheckIn($request);
    }

    public function resourceRequests(Request $request): JsonResponse
    {
        return (new RescuerMobileResourceRequestsResource($this->service->resourceRequests($request)))->response();
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
        $result = $this->service->radioFeed($request);

        return $result instanceof JsonResponse
            ? $result
            : (new RescuerMobileRadioFeedResource($result))->response();
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



