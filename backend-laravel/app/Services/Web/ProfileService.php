<?php

namespace App\Services\Web;

use App\Queries\ProfileQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileService
{
    public function __construct(
        private ProfileQuery $query,
        private ProfileWorkflow $workflow,
    ) {}

    public function show(Request $request): array
    {
        $user = $request->user()->load('role');
        return [
            'user' => $user,
            'last_seen' => $this->query->lastSeen($user),
            'recent_activity' => $this->query->recentActivity($user->user_id),
            'barangay_profile' => $this->query->barangayProfileData(),
        ];
    }

    public function update(Request $request): JsonResponse
    {
        return $this->workflow->update($request);
    }

    public function changePassword(Request $request): JsonResponse
    {
        return $this->workflow->changePassword($request);
    }

    public function updateBarangay(Request $request): JsonResponse
    {
        return $this->workflow->updateBarangay($request);
    }
}







