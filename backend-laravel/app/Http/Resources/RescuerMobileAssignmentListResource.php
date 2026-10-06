<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileAssignmentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignments' => RescuerMobileAssignmentResource::collection($this->resource),
        ];
    }
}


