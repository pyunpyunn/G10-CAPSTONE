<?php

namespace App\Presenters;

class RescueCriteriaPresenter
{
    public function priorityBand(float $score, int $rank): array
    {
        if ($rank === 1 && $score >= config('rescue_priority.deploy_first_percent')) {
            return ['key' => 'first', 'label' => 'Deploy first'];
        }

        if ($score >= config('rescue_priority.high_percent')) {
            return ['key' => 'high', 'label' => 'High'];
        }

        if ($score >= config('rescue_priority.medium_percent')) {
            return ['key' => 'medium', 'label' => 'Medium'];
        }

        return ['key' => 'low', 'label' => 'Low'];
    }
}
