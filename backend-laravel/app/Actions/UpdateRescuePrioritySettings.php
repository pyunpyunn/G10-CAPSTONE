<?php

namespace App\Actions;

use App\Models\RescuePrioritySetting;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class UpdateRescuePrioritySettings
{
    public function apply(array $weights, ?string $userId, string $reason): RescuePrioritySetting
    {
        if (array_sum($weights) !== 100) {
            throw ValidationException::withMessages(['weights' => ['Priority weights must total 100 percent.']]);
        }

        return DB::transaction(function () use ($weights, $userId, $reason): RescuePrioritySetting {
            $previous = RescuePrioritySetting::query()->orderByDesc('version')->lockForUpdate()->first();
            $bands = [];
            foreach (['high_percent', 'medium_percent', 'deploy_first_percent'] as $column) {
                if (Schema::hasColumn('rescue_priority_settings', $column)) {
                    $bands[$column] = $previous?->{$column} ?? config('rescue_priority.'.$column);
                }
            }

            return RescuePrioritySetting::query()->create([
                'impact_weight' => $weights['impact_weight'],
                'vulnerability_weight' => $weights['vulnerability_weight'],
                'unreported_weight' => $weights['unreported_weight'],
                'no_contact_weight' => $weights['no_contact_weight'],
                'updated_by_user_id' => $userId,
                'change_reason' => $reason,
                ...$bands,
            ]);
        }, 3);
    }
}
