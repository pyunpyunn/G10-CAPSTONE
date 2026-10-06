<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileAdminFieldReportsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'active_event' => $this->resource['active_event']
                ? (new RescuerMobileEventResource($this->resource['active_event']))->resolve($request)
                : null,
            'status_options' => $this->resource['status_options'],
            'summary' => $this->resource['summary'],
            'reports' => RescuerMobileFieldReportResource::collection($this->resource['reports']),
        ];
    }
}


