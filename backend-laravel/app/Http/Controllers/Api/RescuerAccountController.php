<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Mobile\RescuerAccountService;
use App\Services\Web\HeadquartersAccountWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescuerAccountController extends Controller
{
    private RescuerAccountService $service;

    public function __construct(RescuerAccountService $service)
    {
        $this->service = $service;
    }

    public function index(ListRequest $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function headquartersAccounts(HeadquartersAccountWorkflow $workflow): JsonResponse
    {
        return $workflow->index();
    }

    public function storeHeadquartersAccount(Request $request, HeadquartersAccountWorkflow $workflow): JsonResponse
    {
        return $workflow->store($request);
    }

    public function teamConfig(): JsonResponse
    {
        return $this->service->teamConfig();
    }

    public function storeTeam(Request $request): JsonResponse
    {
        return $this->service->storeTeam($request);
    }

    public function updateTeam(Request $request, int $teamId): JsonResponse
    {
        return $this->service->updateTeam($request, $teamId);
    }

    public function deleteTeam(Request $request, int $teamId): JsonResponse
    {
        return $this->service->deleteTeam($request, $teamId);
    }

    public function show(int $responderId): JsonResponse
    {
        return $this->service->show($responderId);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, int $responderId): JsonResponse
    {
        return $this->service->update($request, $responderId);
    }

    public function destroy(Request $request, int $responderId): JsonResponse
    {
        return $this->service->delete($request, $responderId);
    }

    public function deactivate(Request $request, int $responderId): JsonResponse
    {
        return $this->service->deactivate($request, $responderId);
    }
}



