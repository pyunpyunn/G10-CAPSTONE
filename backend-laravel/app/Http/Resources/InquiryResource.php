<?php

namespace App\Http\Resources;

use App\Presenters\InquiryPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(InquiryPresenter::class)->present($this->resource);
    }
}
