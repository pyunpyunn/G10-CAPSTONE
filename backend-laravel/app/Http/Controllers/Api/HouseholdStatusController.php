<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HouseholdStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdStatusController extends Controller
{
    private HouseholdStatusService $service;

    public function __construct(HouseholdStatusService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function show(string $householdId): JsonResponse
    {
        return $this->service->show($householdId);
    }

    public function statusLogs(string $householdId): JsonResponse
    {
        return $this->service->statusLogs($householdId);
    }

    public function storeStatusLog(Request $request, string $householdId): JsonResponse
    {
        return $this->service->storeStatusLog($request, $householdId);
    }
}