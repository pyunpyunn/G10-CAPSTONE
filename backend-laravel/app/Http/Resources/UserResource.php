<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loadedRole = $this->resource->relationLoaded('role') ? $this->resource->getRelation('role') : null;
        $roleKey = method_exists($this->resource, 'roleKey') ? $this->resource->roleKey() : $loadedRole?->role_key;
        $roleName = method_exists($this->resource, 'roleName') ? $this->resource->roleName() : $loadedRole?->role_name;

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
            'role' => [
                'role_id' => $loadedRole?->role_id ?? $roleKey,
                'role_key' => $roleKey,
                'role_name' => $roleName,
            ],
        ];
    }
}
