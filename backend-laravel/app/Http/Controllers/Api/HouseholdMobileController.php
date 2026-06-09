<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HouseholdMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdMobileController extends Controller
{
    private HouseholdMobileService $service;

    public function __construct(HouseholdMobileService $service)
    {
        $this->service = $service;
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->service->overview($request);
    }

    public function completeSetup(Request $request): JsonResponse
    {
        return $this->service->completeSetup($request);
    }

    public function updateDeviceLocation(Request $request): JsonResponse
    {
        return $this->service->updateDeviceLocation($request);
    }

    public function updateMember(Request $request, string $memberId): JsonResponse
    {
        return $this->service->updateMember($request, $memberId);
    }

    public function storeStatus(Request $request): JsonResponse
    {
        return $this->service->storeStatus($request);
    }

    public function statusHistory(Request $request): JsonResponse
    {
        return $this->service->statusHistory($request);
    }

    public function qr(Request $request): JsonResponse
    {
        return $this->service->qr($request);
    }

    public function trustedHouseholds(Request $request): JsonResponse
    {
        return $this->service->trustedHouseholds($request);
    }

    public function lookupTrustedHousehold(Request $request, string $householdId): JsonResponse
    {
        return $this->service->lookupTrustedHousehold($request, $householdId);
    }

    public function storeTrustedHousehold(Request $request): JsonResponse
    {
        return $this->service->storeTrustedHousehold($request);
    }
}
