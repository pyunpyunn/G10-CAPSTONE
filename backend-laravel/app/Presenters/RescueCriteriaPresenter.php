<?php

namespace App\Presenters;

use App\Models\RescuePrioritySetting;

class RescueCriteriaPresenter
{
    public function priorityBand(float $score, int $rank, ?RescuePrioritySetting $settings = null): array
    {
        if ($rank === 1 && $score >= ($settings?->deploy_first_percent ?? config('rescue_priority.deploy_first_percent'))) {
            return ['key' => 'first', 'label' => 'Deploy first'];
        }

        if ($score >= ($settings?->high_percent ?? config('rescue_priority.high_percent'))) {
            return ['key' => 'high', 'label' => 'High'];
        }

        if ($score >= ($settings?->medium_percent ?? config('rescue_priority.medium_percent'))) {
            return ['key' => 'medium', 'label' => 'Medium'];
        }

        return ['key' => 'low', 'label' => 'Low'];
    }
}
