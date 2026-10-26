<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileResourceRequestsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'requests' => RescuerMobileResourceRequestResource::collection($this->resource['requests']),
            'category_options' => $this->resource['category_options'],
        ];
    }
}


