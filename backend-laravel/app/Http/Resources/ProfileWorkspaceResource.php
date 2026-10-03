<?php

namespace App\Http\Resources;

use App\Presenters\ProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileWorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $user = $snapshot['user'];
        $presenter = app(ProfilePresenter::class);

        return [
            'user' => new UserResource($user),
            'summary' => $presenter->summaryCards($user, $snapshot['last_seen']),
            'identity' => $presenter->identity($user),
            'permissions' => $presenter->permissions($user->roleKey()),
            'activity' => $presenter->activity($snapshot['recent_activity']),
            'barangay_profile' => $snapshot['barangay_profile'],
        ];
    }
}
