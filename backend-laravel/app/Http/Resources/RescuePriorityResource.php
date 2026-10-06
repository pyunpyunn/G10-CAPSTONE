<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuePriorityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'household_id' => $this->household_id,
            'household_code' => $this->household_code,
            'household_name' => $this->household_name,
            'area_name' => $this->area_name,
            'status_key' => $this->status_key ?: 'unreported',
            'urgent_tier' => (bool) $this->urgent_tier,
            'priority_score' => (float) $this->priority_score,
            'settings_version' => (int) $this->settings_version,
            'impacted_households' => (int) $this->impacted_households,
            'area_households' => (int) $this->area_households,
            'vulnerable_members' => (int) $this->vulnerable_members,
            'total_vulnerable_members' => (int) $this->total_vulnerable_members,
            'area_members' => (int) $this->area_members,
            'unreported_members' => (int) $this->unreported_members,
            'household_members' => (int) $this->household_members,
            'no_contact_channel' => (bool) $this->no_contact_channel,
        ];
    }
}
