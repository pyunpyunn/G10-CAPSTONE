<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        $data = $profile['data'] ?? $profile;

        return [
            'user' => $data['user'] ? (new RescuerMobileUserResource($data['user']))->resolve($request) : null,
            'responder' => $data['responder'] ? (new RescuerMobileResponderResource($data['responder']))->resolve($request) : null,
            'active_assignment' => $data['active_assignment']
                ? (new RescuerMobileAssignmentResource($data['active_assignment']))->resolve($request)
                : null,
        ];
    }

    public function with(Request $request): array
    {
        return isset($this->resource['message'])
            ? ['message' => $this->resource['message']]
            : [];
    }
}


