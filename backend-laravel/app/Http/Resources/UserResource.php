<?php

namespace App\Http\Resources;

use App\Presenters\UserRolePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource->exists && !$this->resource->relationLoaded('role')) $this->resource->setRelation('role', null);
        else $this->resource->loadMissing('role');
        $loadedRole = $this->resource->relationLoaded('role') ? $this->resource->getRelation('role') : null;
        $roles = app(UserRolePresenter::class);
        $roleKey = $roles->key($loadedRole, $this->resource->getAttributes());
        $roleName = $roles->name($loadedRole, $this->resource->getAttributes(), $roleKey);

        return [
            'user_id' => $this->user_id ?? $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'assigned_center_id' => $this->assigned_center_id,
            'household_id' => $this->household_id,
            'is_active' => (bool) $this->is_active,
            'is_hqcc_leader' => $this->resource->isHqccLeader(),
            'permissions' => [
                'can_approve_requests' => app(\App\Services\Web\WebAccessPolicy::class)->allows($this->resource, 'resources_requests', 'approve'),
                'can_manage_personnel' => $this->resource->canManagePersonnel(),
                'web_modules' => app(\App\Services\Web\WebAccessPolicy::class)->modulesFor($this->resource),
            ],
            'role' => [
                'role_id' => $loadedRole?->role_id ?? $roleKey,
                'role_key' => $roleKey,
                'assigned_role_key' => $loadedRole?->role_key,
                'role_name' => $roleName,
            ],
        ];
    }
}


