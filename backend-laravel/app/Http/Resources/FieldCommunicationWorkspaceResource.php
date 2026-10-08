<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldCommunicationWorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logs = $this->resource['logs'];
        return [
            'active_event' => $this->resource['active_event'],
            'logs' => RescuerMobileRadioLogResource::collection(collect($logs->items())),
            'meta' => ['current_page' => $logs->currentPage(), 'per_page' => $logs->perPage(),
                'total' => $logs->total(), 'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(), 'to' => $logs->lastItem()],
            'teams' => $this->resource['teams'],
            'channels' => [['key' => 'command', 'label' => 'HQ Command'],
                ['key' => 'team', 'label' => 'Team'], ['key' => 'event', 'label' => 'Event']],
        ];
    }
}
