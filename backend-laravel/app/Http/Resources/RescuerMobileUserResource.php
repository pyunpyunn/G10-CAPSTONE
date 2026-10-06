<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'full_name' => $this->full_name ?? $this->name ?? $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'display_username' => $this->username,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'role' => $this->whenLoaded('role', fn ($role): array => [
                'role_key' => $role?->role_key,
                'role_name' => $role?->role_name,
            ]),
        ];
    }
}


