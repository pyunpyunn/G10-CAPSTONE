<?php

namespace App\Services\Web;

use App\Http\Resources\UserResource;
use App\Presenters\ProfilePresenter;
use App\Queries\ProfileQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileService
{
    public function __construct(
        private ProfileQuery $query,
        private ProfilePresenter $presenter,
        private ProfileWorkflow $workflow,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');
        return response()->json(['data' => [
            'user' => new UserResource($user),
            'summary' => $this->presenter->summaryCards($user, $this->query->lastSeen($user)),
            'identity' => $this->presenter->identity($user),
            'permissions' => $this->presenter->permissions($user->role?->role_key),
            'activity' => $this->presenter->activity($this->query->recentActivity($user->user_id)),
            'barangay_profile' => $this->query->barangayProfileData(),
        ]]);
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







