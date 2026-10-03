<?php

namespace App\Presenters;

use Illuminate\Support\Collection;

class RescuerHouseholdStatusPresenter
{
    public function defaultOptions(): array
    {
        return [
            ['key' => 'safe', 'label' => 'Safe'],
            ['key' => 'evacuated', 'label' => 'Evacuated'],
            ['key' => 'unsafe', 'label' => 'Unsafe'],
        ];
    }

    public function options(Collection $rows): array
    {
        return $rows->map(fn (object $status): array => [
            'status_id' => $status->status_id,
            'key' => $status->status_key,
            'label' => $status->status_label,
        ])->values()->all();
    }

    public function summaryRows(Collection $options, array $counts): array
    {
        $rows = $options->map(fn (array $option): array => [
            'key' => $option['key'],
            'label' => $option['label'],
            'status_id' => $option['status_id'] ?? null,
            'count' => (int) ($counts[$option['key']] ?? 0),
        ])->values()->all();

        return ['rows' => $rows];
    }
}


