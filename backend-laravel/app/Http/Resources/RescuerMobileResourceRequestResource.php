<?php

namespace App\Http\Resources;

use App\Presenters\RescuerResourceRequestPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileResourceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'request_id' => $this->request_id,
            'location' => ($this->evacuation_center_id ?? null)
                ?: app(RescuerResourceRequestPresenter::class)->location($this->description ?? null),
            'resource_type' => $this->resource_type,
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'description' => $this->description,
            'validation_status' => $this->validation_status ?? 'needs_validation',
            'tracking_reference' => $this->tracking_reference ?? null,
            'created_at' => $this->created_at,
        ];
    }
}


