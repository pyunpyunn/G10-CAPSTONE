<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileCheckInsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['check_ins' => RescuerMobileCheckInResource::collection($this->resource)];
    }
}


