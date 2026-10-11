<?php

namespace App\Services\Web;

use App\Services\Archive\ArchiveGroupManager;
use App\Presenters\ArchiveEventPresenter;
use App\Presenters\ArchivePresenter;
use App\Queries\ArchiveQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArchiveService
{
    public function __construct(
        private ArchiveQuery $query,
        private ArchiveEventPresenter $eventPresenter,
        private ArchiveGroupManager $savedGroups,
        private ArchiveDeletionWorkflow $deletions,
        private ArchiveExportWorkflow $exports,
        private \App\Services\Shared\BarangayProfileService $barangayProfile,
    ) {}

    public function disasterEvents(Request $request): JsonResponse { return $this->categoryResponse('disaster-events', $request); }
    public function householdStatusLogs(Request $request): JsonResponse { return $this->categoryResponse('household-status-logs', $request); }
    public function dispatchLogs(Request $request): JsonResponse { return $this->categoryResponse('dispatch-logs', $request); }
    public function radioCommunicationLogs(Request $request): JsonResponse { return $this->categoryResponse('radio-communication-logs', $request); }
    public function resourceRequests(Request $request): JsonResponse { return $this->categoryResponse('resource-requests', $request); }
    public function situationReports(Request $request): JsonResponse { return $this->categoryResponse('situation-reports', $request); }
    public function export(Request $request): Response|JsonResponse { return $this->exports->export($request); }
    public function deleteSelected(Request $request): JsonResponse { return $this->deletions->deleteSelected($request); }
    public function savedGroups(Request $request): JsonResponse { return $this->savedGroups->savedGroups(); }
    public function storeSavedGroup(Request $request): JsonResponse { return $this->savedGroups->store($request); }
    public function deleteSavedGroup(string $groupId): JsonResponse { return $this->savedGroups->delete($groupId); }
    public function deleteSavedGroupRecord(string $groupId, string $recordId): JsonResponse { return $this->savedGroups->deleteRecord($groupId, $recordId); }

    private function categoryResponse(string $category, Request $request): JsonResponse
    {
        [$paginator, $records] = $this->query->categoryRows($category, $request, 25);
        if ($category === 'disaster-events') {
            $records = collect($paginator->items())->map(fn (object $row): array => $this->eventPresenter->present($row))->values()->all();
        }
        $categoryLabels = ['disaster-events' => 'Disaster Event', 'household-status-logs' => 'Household Status Logs', 'dispatch-logs' => 'Rescue Dispatch Logs', 'radio-communication-logs' => 'Radio Communication Logs', 'resource-requests' => 'Resources & Requests', 'situation-reports' => 'Situation Reporting'];
        $label = $categoryLabels[$category];
        return response()->json([
            'success' => true,
            'message' => $label.' archive loaded.',
            'data' => [
                'barangay_profile' => $this->barangayProfile->current(),
                'category' => $category,
                'category_label' => $label,
                'records' => ['data' => $records, 'current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()],
                'filters' => $this->query->options(),
                'note' => 'Archive reads existing disaster, household, dispatch, request, and SitRep history. No duplicate archive copy is created here.',
            ],
        ]);
    }
}







