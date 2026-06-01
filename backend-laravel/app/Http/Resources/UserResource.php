<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'assigned_center_id' => $this->assigned_center_id,
            'household_id' => $this->household_id,
            'is_active' => (bool) $this->is_active,
            'role' => $this->whenLoaded('role', fn () => [
                'role_id' => $this->role?->role_id,
                'role_key' => $this->role?->role_key,
                'role_name' => $this->role?->role_name,
            ]),
        ];
    }
}
