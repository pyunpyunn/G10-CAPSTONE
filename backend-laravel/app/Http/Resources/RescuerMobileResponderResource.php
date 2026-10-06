<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileResponderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'responder_id' => $this->responder_id,
            'account_id' => $this->responder_code,
            'responder_code' => $this->responder_code,
            'full_name' => $this->full_name,
            'title' => $this->title ?: 'Responder',
            'contact_number' => $this->contact_number,
            'team_id' => $this->team_id,
            'team_name' => $this->team_name ?: 'Unassigned',
            'team_code' => $this->team_code,
            'team_type' => $this->team_type,
            'duty_status' => $this->duty_status ?: 'available',
            'is_deployed' => (bool) ($this->is_deployed ?? false),
            'skills' => $this->skills,
            'blood_type' => $this->blood_type ?: 'Unknown',
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_number' => $this->emergency_contact_number,
        ];
    }
}


