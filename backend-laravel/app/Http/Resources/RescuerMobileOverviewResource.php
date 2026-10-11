<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $overview = $this->resource;

        return [
            'profile' => [
                'user' => $overview['profile']['user']
                    ? (new RescuerMobileUserResource($overview['profile']['user']))->resolve($request)
                    : null,
                'responder' => $overview['profile']['responder']
                    ? (new RescuerMobileResponderResource($overview['profile']['responder']))->resolve($request)
                    : null,
            ],
            'active_event' => $overview['active_event']
                ? (new RescuerMobileEventResource($overview['active_event']))->resolve($request)
                : null,
            'summary' => $overview['summary'],
            'assignments' => RescuerMobileAssignmentResource::collection($overview['assignments']),
            'field_reports' => RescuerMobileFieldReportResource::collection($overview['field_reports']),
            'check_ins' => RescuerMobileCheckInResource::collection($overview['check_ins']),
            'evacuation_centers' => RescuerMobileEvacuationCenterResource::collection($overview['evacuation_centers']),
            'resource_requests' => RescuerMobileResourceRequestResource::collection($overview['resource_requests']),
            'status_options' => $overview['status_options'],
            'category_options' => $overview['category_options'],
        ];
    }
}


