<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileCheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'check_in_id' => $this->check_in_id,
            'household_id' => $this->household_id,
            'member_id' => $this->member_id ?? null,
            'latitude' => $this->latitude ?? null,
            'longitude' => $this->longitude ?? null,
            'check_in_method' => $this->check_in_method ?? 'field_visit',
            'notes' => $this->notes ?? null,
            'checked_in_at' => $this->checked_in_at,
        ];
    }
}


