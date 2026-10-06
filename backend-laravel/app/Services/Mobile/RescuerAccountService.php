<?php

namespace App\Services\Mobile;

use App\Presenters\RescuerAccountPresenter;
use App\Queries\RescuerAccountQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescuerAccountService
{
    public function __construct(
        private RescuerAccountQuery $query,
        private RescuerAccountPresenter $presenter,
        private RescuerAccountSupport $support,
        private RescuerAccountWriteWorkflow $accountWorkflow,
        private RescuerTeamWriteWorkflow $teamWriteWorkflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'team' => trim((string) $request->query('team', 'all')),
            'duty_status' => trim((string) $request->query('duty_status', 'all')),
            'purok' => trim((string) $request->query('purok', 'all')),
        ];
        $perPage = \App\Http\Requests\ListRequest::clampPerPage($request->query('per_page'));
        $paginator = $this->query->paginateResponders($filters, $perPage);
        $view = $this->query->viewData();
        $teamOptions = $view['team_options'];
        $accountIds = $this->query->accountIdChoices($teamOptions);

        return response()->json(['data' => [
            'area_label' => app(\App\Queries\AreaCoverageQuery::class)->label(),
            'summary' => $view['summary'],
            'rescuers' => [
                'data' => collect($paginator->items())->map(fn (object $row): array => $this->presenter->formatResponder($row))->values()->all(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'teams' => $view['teams'],
            'team_options' => $teamOptions,
            'account_id_options' => $accountIds['options'],
            'next_account_id' => $accountIds['default'],
            'account_id_format' => 'BDRRM-{TEAM_CODE}-###',
            'filters' => [
                'puroks' => $view['puroks'],
                'duty_statuses' => $this->presenter->dutyStatuses(),
                'roles' => $this->presenter->responderRoles(),
                'blood_types' => $this->presenter->bloodTypes(),
            ],
            'note' => 'HQ/Admin creates verified rescuer accounts manually. There is no public self-registration or pending verification queue.',
        ]]);
    }

    public function show(int $responderId): JsonResponse
    {
        $responder = $this->query->findResponder($responderId);
        if (! $responder) {
            return response()->json(['message' => 'Rescuer account was not found.'], 404);
        }

        return response()->json(['data' => ['rescuer' => $this->presenter->formatResponder($responder, true)]]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->accountWorkflow->store($request);
    }

    public function update(Request $request, int $responderId): JsonResponse
    {
        return $this->accountWorkflow->update($request, $responderId);
    }

    public function deactivate(Request $request, int $responderId): JsonResponse
    {
        return $this->accountWorkflow->deactivate($request, $responderId);
    }

    public function teamConfig(): JsonResponse
    {
        return response()->json(['data' => $this->query->teamConfigWorkspace()]);
    }

    public function storeTeam(Request $request): JsonResponse
    {
        return $this->teamWriteWorkflow->storeTeam($request);
    }

    public function updateTeam(Request $request, int $teamId): JsonResponse
    {
        return $this->teamWriteWorkflow->updateTeam($request, $teamId);
    }

    public function deleteTeam(Request $request, int $teamId): JsonResponse
    {
        return $this->teamWriteWorkflow->deleteTeam($request, $teamId);
    }
}






