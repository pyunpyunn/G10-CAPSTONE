<?php

namespace App\Services\Mobile;

use App\Queries\RescuerAssignmentQuery;
use App\Queries\RescuerMobileReadQuery;
use App\Presenters\RescuerAssignmentPresenter;
use App\Presenters\RescuerResourceRequestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RescuerMobileService
{
    private RescuerAssignmentPresenter $assignmentPresenter;
    private RescuerMobileResourceRequestWorkflow $resourceRequestWorkflow;
    private RescuerFieldReportWorkflow $fieldReportWorkflow;
    private \App\Services\Mobile\RescuerMobileSupport $support;
    private RescuerProfileWorkflow $profileWorkflow;
    private RescuerRadioWorkflow $radioWorkflow;
    private RescuerAssignmentQuery $assignmentQuery;
    private RescuerAssignmentWorkflow $assignmentWorkflow;
    private RescuerMobileReadQuery $readQuery;
    private RescuerCheckInWorkflow $checkInWorkflow;
    private RescuerResourceRequestPresenter $resourceRequestPresenter;

    public function __construct(
        RescuerAssignmentPresenter $assignmentPresenter,
        RescuerMobileResourceRequestWorkflow $resourceRequestWorkflow,
        RescuerAssignmentQuery $assignmentQuery,
        RescuerAssignmentWorkflow $assignmentWorkflow,
        RescuerFieldReportWorkflow $fieldReportWorkflow,
        \App\Services\Mobile\RescuerMobileSupport $support,
        RescuerProfileWorkflow $profileWorkflow,
        RescuerRadioWorkflow $radioWorkflow,
        RescuerMobileReadQuery $readQuery,
        RescuerCheckInWorkflow $checkInWorkflow,
        RescuerResourceRequestPresenter $resourceRequestPresenter,
    ) {
        $this->assignmentPresenter = $assignmentPresenter;
        $this->resourceRequestWorkflow = $resourceRequestWorkflow;
        $this->assignmentQuery = $assignmentQuery;
        $this->assignmentWorkflow = $assignmentWorkflow;
        $this->fieldReportWorkflow = $fieldReportWorkflow;
        $this->support = $support;
        $this->profileWorkflow = $profileWorkflow;
        $this->radioWorkflow = $radioWorkflow;
        $this->readQuery = $readQuery;
        $this->checkInWorkflow = $checkInWorkflow;
        $this->resourceRequestPresenter = $resourceRequestPresenter;
    }

    public function profile(Request $request): array
    {
        return $this->profileWorkflow->profile($request);
    }

    public function updateProfile(Request $request): JsonResponse|array
    {
        return $this->profileWorkflow->updateProfile($request);
    }

    public function overview(Request $request): array
    {
        $user = $request->user()?->load('role');
        $responder = $this->support->responderForUser($user);
        $assignments = $this->assignmentQuery->list($responder?->responder_id, 10);
        $activeEvent = $this->support->activeEvent();

        return [
            'profile' => [
                'user' => $user,
                'responder' => $responder,
            ],
            'active_event' => $activeEvent,
            'summary' => $this->assignmentPresenter->summary($assignments),
            'assignments' => $assignments,
            'field_reports' => $this->fieldReportWorkflow->fieldReports($request)['reports'],
            'check_ins' => $this->readQuery->checkIns($responder?->responder_id, 10),
            'evacuation_centers' => $this->readQuery->evacuationCenters(),
            'resource_requests' => $this->readQuery->resourceRequests($user?->user_id, 8),
            'status_options' => $this->fieldReportWorkflow->statusOptions(),
            'category_options' => $this->resourceRequestPresenter->categoryOptions(),
        ];
    }

    public function assignments(Request $request): Collection
    {
        $responder = $this->support->responderForUser($request->user());

        return $this->assignmentQuery->list($responder?->responder_id, 30);
    }

    public function assignment(Request $request, int $assignmentId): JsonResponse|array
    {
        $responder = $this->support->responderForUser($request->user());
        $assignment = $this->assignmentQuery->find($assignmentId, $responder?->responder_id);

        if (! $assignment) {
            return response()->json([
                'message' => 'Assignment was not found for your rescuer account.',
            ], 404);
        }

        $assignment->mobile_route = $this->assignmentQuery->routeForAssignment($assignmentId);

        return [
            'assignment' => $assignment,
            'route' => $assignment->mobile_route,
        ];
    }

    public function updateAssignmentStatus(Request $request, int $assignmentId): JsonResponse|array
    {
        return $this->assignmentWorkflow->updateAssignmentStatus($request, $assignmentId);
    }

    public function storeLocation(Request $request, int $assignmentId): JsonResponse
    {
        return $this->assignmentWorkflow->storeLocation($request, $assignmentId);
    }

    public function fieldReports(Request $request): array
    {
        return $this->fieldReportWorkflow->fieldReports($request);
    }

    public function storeFieldReport(Request $request): JsonResponse
    {
        return $this->fieldReportWorkflow->store($request);
    }

    public function fieldReportsAdmin(Request $request): array
    {
        return $this->fieldReportWorkflow->fieldReportsAdmin($request);
    }

    public function checkIns(Request $request): Collection
    {
        return $this->checkInWorkflow->list($request);
    }

    public function storeCheckIn(Request $request): JsonResponse
    {
        return $this->checkInWorkflow->store($request);
    }

    public function resourceRequests(Request $request): array
    {
        return [
            'requests' => $this->readQuery->resourceRequests($request->user()?->user_id, 25),
            'category_options' => $this->resourceRequestPresenter->categoryOptions(),
        ];
    }

    public function storeResourceRequest(Request $request): JsonResponse
    {
        return $this->resourceRequestWorkflow->store($request);
    }

    public function cancelResourceRequest(Request $request, string $requestId): JsonResponse
    {
        return $this->resourceRequestWorkflow->cancel($request, $requestId);
    }




    public function radioFeed(Request $request): JsonResponse|array
    {
        return $this->radioWorkflow->radioFeed($request);
    }

    public function startRadioTransmission(Request $request): JsonResponse
    {
        return $this->radioWorkflow->startRadioTransmission($request);
    }

    public function heartbeatRadioTransmission(Request $request): JsonResponse
    {
        return $this->radioWorkflow->heartbeatRadioTransmission($request);
    }

    public function stopRadioTransmission(Request $request): JsonResponse
    {
        return $this->radioWorkflow->stopRadioTransmission($request);
    }

    public function storeRadioClip(Request $request): JsonResponse
    {
        return $this->radioWorkflow->storeRadioClip($request);
    }

    public function storeRadioSignal(Request $request): JsonResponse
    {
        return $this->radioWorkflow->storeRadioSignal($request);
    }

}







