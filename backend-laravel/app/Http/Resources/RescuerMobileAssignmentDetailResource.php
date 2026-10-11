<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileAssignmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment' => $this->resource['assignment']
                ? (new RescuerMobileAssignmentResource($this->resource['assignment']))->resolve($request)
                : null,
            'route' => $this->resource['route']
                ? (new RescuerMobileRouteResource($this->resource['route']))->resolve($request)
                : null,
        ];
    }

    public function with(Request $request): array
    {
        return isset($this->resource['message']) ? ['message' => $this->resource['message']] : [];
    }
}


