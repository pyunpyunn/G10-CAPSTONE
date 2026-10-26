<?php

namespace App\Http\Resources;

use App\Presenters\RescuerRadioPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileRadioLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(RescuerRadioPresenter::class)->log($this->resource);
    }
}


