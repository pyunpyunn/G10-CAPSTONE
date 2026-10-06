<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WelfareCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'household_id' => $this->household_id,
            'household_code' => $this->household_code,
            'household_name' => $this->household_name,
            'member_count' => (int) $this->member_count,
            'address' => $this->whenLoaded('address', fn () => $this->address?->full_address),
            'purok' => $this->whenLoaded('address', fn () => $this->address?->purok_sitio),
            'contact_channel_key' => 'none',
            'contact_channel_label' => trans('welfare_check.contact_none'),
            'has_geotag' => (bool) $this->has_geotag,
            'required_rescuers' => 2,
        ];
    }
}
