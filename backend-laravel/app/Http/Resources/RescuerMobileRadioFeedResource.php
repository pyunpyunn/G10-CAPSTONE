<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileRadioFeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'channel' => $this->resource['channel'],
            'active_transmission' => $this->resource['active_transmission'],
            'team_members' => $this->resource['team_members'],
            'logs' => [
                'data' => RescuerMobileRadioLogResource::collection($this->resource['logs']['data']),
                'current_page' => $this->resource['logs']['current_page'],
                'per_page' => $this->resource['logs']['per_page'],
                'total' => $this->resource['logs']['total'],
                'has_more' => $this->resource['logs']['has_more'],
            ],
            'audio_note' => $this->resource['audio_note'],
        ];
    }
}


