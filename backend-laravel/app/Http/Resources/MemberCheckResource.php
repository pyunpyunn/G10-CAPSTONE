<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reminder_id' => $this->reminder_id,
            'event_id' => $this->event_id,
            'household_id' => $this->household_id,
            'member_id' => $this->member_id,
            'member_name' => $this->whenLoaded('member', fn () => trim(($this->member?->first_name ?? '').' '.($this->member?->last_name ?? ''))),
            'attempt' => (int) $this->attempt,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
        ];
    }
}
