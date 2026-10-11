<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileEvacuationCenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'evacuation_center_id' => $this->evacuation_center_id,
            'name' => $this->name ?: 'Evacuation center',
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'capacity' => $this->capacity !== null ? (int) $this->capacity : null,
            'current_occupancy' => $this->current_occupancy !== null ? (int) $this->current_occupancy : null,
            'status' => $this->status ?: 'active',
        ];
    }
}


