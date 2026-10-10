<?php

namespace App\Services\Mobile;

use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdMobileService
{
    public function __construct(private HouseholdMobileSetupWorkflow $setupWorkflow, private HouseholdMobileSupport $support, private \App\Presenters\HouseholdMobilePresenter $presenter, private \App\Queries\HouseholdMobileReadQuery $readQuery, private HouseholdTrustedHouseholdWorkflow $trustedWorkflow, private HouseholdMemberProfileWorkflow $memberProfileWorkflow, private HouseholdMobileStatusWorkflow $statusWorkflow, private HouseholdMobileReadWorkflow $readWorkflow) {}

    public function overview(Request $request): JsonResponse
    {
        return $this->readWorkflow->overview($request);
    }

    public function statusHistory(Request $request): JsonResponse
    {
        return $this->readWorkflow->statusHistory($request);
    }

    public function qr(Request $request): JsonResponse
    {
        return $this->readWorkflow->qr($request);
    }

    public function completeSetup(Request $request): JsonResponse
    {
        return $this->setupWorkflow->completeSetup($request);
    }

    public function updateGeotag(Request $request): JsonResponse
    {
        return $this->setupWorkflow->updateGeotag($request);
    }

    public function updateDeviceLocation(Request $request): JsonResponse
    {
        return $this->setupWorkflow->updateDeviceLocation($request);
    }



    public function storeMemberStatus(Request $request, string $memberId): JsonResponse
    {
        return $this->statusWorkflow->storeMemberStatus($request, $memberId);
    }

    public function storeTrustedMemberStatus(Request $request, string $connectionId, string $memberId): JsonResponse
    {
        return $this->trustedWorkflow->storeTrustedMemberStatus($request, $connectionId, $memberId);
    }

    public function storeStatus(Request $request): JsonResponse
    {
        return $this->statusWorkflow->storeStatus($request);
    }

    public function storeStatusFromSms(string $householdCode, string $statusKey, string $from, string $rawText): array
    {
        return $this->statusWorkflow->storeStatusFromSms($householdCode, $statusKey, $from, $rawText);
    }

    public function updateMember(Request $request, string $memberId): JsonResponse
    {
        return $this->memberProfileWorkflow->updateMember($request, $memberId);
    }

    public function trustedHouseholds(Request $request): JsonResponse
    {
        return $this->trustedWorkflow->trustedHouseholds($request);
    }

    public function saveTrustedPin(Request $request): JsonResponse
    {
        return $this->trustedWorkflow->saveTrustedPin($request);
    }

    public function verifyTrustedPin(Request $request): JsonResponse
    {
        return $this->trustedWorkflow->verifyTrustedPin($request);
    }

    public function lookupTrustedHousehold(Request $request, string $householdId): JsonResponse
    {
        return $this->trustedWorkflow->lookupTrustedHousehold($request, $householdId);
    }

    public function storeTrustedHousehold(Request $request): JsonResponse
    {
        return $this->trustedWorkflow->storeTrustedHousehold($request);
    }

    public function respondToTrustedHousehold(Request $request, string $connectionId): JsonResponse
    {
        return $this->trustedWorkflow->respondToTrustedHousehold($request, $connectionId);
    }

}







