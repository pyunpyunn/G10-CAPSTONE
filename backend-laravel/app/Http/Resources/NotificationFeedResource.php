<?php

namespace App\Http\Resources;

use App\Presenters\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationFeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(NotificationPresenter::class)->workspace($this->resource);
    }
}
