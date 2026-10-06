<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileFieldReportsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reports' => RescuerMobileFieldReportResource::collection($this->resource['reports']),
            'status_options' => $this->resource['status_options'],
        ];
    }
}


