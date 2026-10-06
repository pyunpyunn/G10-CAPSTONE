<?php

namespace App\Actions;

use App\Models\RescuePrioritySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRescuePrioritySettings
{
    public function apply(array $weights, ?string $userId): RescuePrioritySetting
    {
        if (array_sum($weights) !== 100) {
            throw ValidationException::withMessages(['weights' => ['Priority weights must total 100 percent.']]);
        }

        return DB::transaction(function () use ($weights, $userId): RescuePrioritySetting {
            RescuePrioritySetting::query()->orderByDesc('version')->lockForUpdate()->first();

            return RescuePrioritySetting::query()->create([
                'impact_weight' => $weights['impact_weight'],
                'vulnerability_weight' => $weights['vulnerability_weight'],
                'unreported_weight' => $weights['unreported_weight'],
                'no_contact_weight' => $weights['no_contact_weight'],
                'updated_by_user_id' => $userId,
            ]);
        }, 3);
    }
}
