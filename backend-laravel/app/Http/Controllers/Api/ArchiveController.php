<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Web\ArchiveService;
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

    public function inquiryLogs(ListRequest $request): JsonResponse
    {
        if (! \App\Support\RequestSchema::hasTable('landing_inquiries')) {
            return response()->json(['data' => ['records' => ['data' => [], 'total' => 0]]]);
        }
        $search = trim((string) $request->query('search', ''));
        $rows = \App\Models\LandingInquiry::query()
            ->select(['inquiry_id', 'name', 'organization', 'email', 'message', 'status', 'created_at'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')->paginate($request->integer('per_page', 20));
        return response()->json(['data' => ['records' => $rows]]);
    }

    public function disasterEvents(ListRequest $request): JsonResponse
    {
        return $this->service->disasterEvents($request);
    }

    public function householdStatusLogs(ListRequest $request): JsonResponse
    {
        return $this->service->householdStatusLogs($request);
    }

    public function dispatchLogs(ListRequest $request): JsonResponse
    {
        return $this->service->dispatchLogs($request);
    }

    public function radioCommunicationLogs(ListRequest $request): JsonResponse
    {
        return $this->service->radioCommunicationLogs($request);
    }

    public function resourceRequests(ListRequest $request): JsonResponse
    {
        return $this->service->resourceRequests($request);
    }

    public function situationReports(ListRequest $request): JsonResponse
    {
        return $this->service->situationReports($request);
    }

    public function export(Request $request): Response|JsonResponse
    {
        return $this->service->export($request);
    }

    public function savedGroups(ListRequest $request): JsonResponse
    {
        return $this->service->savedGroups($request);
    }

    public function storeSavedGroup(Request $request): JsonResponse
    {
        return $this->service->storeSavedGroup($request);
    }

    public function deleteSavedGroup(string $groupId): JsonResponse
    {
        return $this->service->deleteSavedGroup($groupId);
    }

    public function deleteSavedGroupRecord(string $groupId, string $recordId): JsonResponse
    {
        return $this->service->deleteSavedGroupRecord($groupId, $recordId);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        return $this->service->deleteSelected($request);
    }
}



