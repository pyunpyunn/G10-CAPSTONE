<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'name' => $this->name,
            'type' => $this->type_name ?? 'Disaster event',
            'severity' => $this->severity_label ?? 'Monitoring',
            'severity_key' => $this->severity_key ?? 'medium',
            'started_at' => $this->started_at,
        ];
    }
}


