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
        return match ($status) {
            'accepted' => 'Accepted',
            'en_route' => 'En route',
            'on_scene' => 'On-scene',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Dispatched',
        };
    }

    public function summary(Collection $assignments): array
    {
        return [
            'total_assignments' => $assignments->count(),
            'active_assignments' => $assignments->filter(fn (object $assignment): bool => in_array(
                $this->statusKey($assignment->status ?? 'dispatched'),
                ['accepted', 'dispatched', 'en_route', 'on_scene'],
                true,
            ))->count(),
            'completed_assignments' => $assignments->filter(fn (object $assignment): bool => $this->statusKey($assignment->status ?? null) === 'completed')->count(),
            'urgent_assignments' => $assignments->filter(fn (object $assignment): bool => ($assignment->priority_level ?: 'medium') === 'urgent')->count(),
        ];
    }
}


