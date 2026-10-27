<?php

namespace App\Presenters;

use Illuminate\Support\Collection;

class RescuerAssignmentPresenter
{
    public function statusKey(?string $status): string
    {
        return str_replace('-', '_', strtolower((string) ($status ?: 'dispatched')));
    }

    public function statusLabel(string $status): string
    {
        return app(\App\Queries\DispatchStatusQuery::class)->label($status) ?? ucwords(str_replace('_', ' ', $status));
    }

    public function summary(Collection $assignments): array
    {
        return [
            'total_assignments' => $assignments->count(),
            'active_assignments' => $assignments->filter(fn (object $assignment): bool => in_array(
                $this->statusKey($assignment->status ?? 'dispatched'),
                app(\App\Queries\DispatchStatusQuery::class)->keys(true),
                true,
            ))->count(),
            'completed_assignments' => $assignments->filter(fn (object $assignment): bool => $this->statusKey($assignment->status ?? null) === 'completed')->count(),
            'urgent_assignments' => $assignments->filter(fn (object $assignment): bool => ($assignment->priority_level ?: 'medium') === 'urgent')->count(),
        ];
    }
}


