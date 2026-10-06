<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileFieldReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'status_log_id' => $row->status_log_id,
            'household_id' => $row->household_id,
            'household_code' => $row->household_code ?? null,
            'household_head_name' => $row->household_head_name ?? 'Household',
            'status_id' => $row->status_id ?? null,
            'status_key' => $row->status_key ?? 'reported',
            'status_label' => $row->status_label ?? 'Reported',
            'event_id' => $row->disaster_id ?? null,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
            'battery_level' => $row->battery_level,
            'notes' => $row->notes,
            'submitted_at' => $row->submitted_at,
        ];
    }
}


