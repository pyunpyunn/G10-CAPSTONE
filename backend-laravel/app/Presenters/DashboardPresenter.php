<?php

namespace App\Presenters;

use Carbon\Carbon;

class DashboardPresenter
{
    public function formatActiveEvent(object $event, ?object $latestBroadcast): array
    {
        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Unspecified',
            'severity_key' => $event->severity_key ?? 'medium',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
            'latest_broadcast_title' => $latestBroadcast?->broadcast_title,
            'latest_broadcast_scope' => $latestBroadcast?->scope_type,
            'latest_broadcast_time' => $this->formatTime($latestBroadcast?->sent_at),
        ];
    }

    public function householdBars(int $safeTotal, int $safeOnly, int $evacuated, int $unsafe, int $unchecked): array
    {
        $values = [$safeTotal, $safeOnly, $evacuated, $unsafe, $unchecked];
        $max = max(max($values), 1);
        return [
            $this->bar('Safe total', $safeTotal, 'safe-total', $max),
            $this->bar('Safe only', $safeOnly, 'safe-only', $max),
            $this->bar('Evacuated', $evacuated, 'evacuated', $max),
            $this->bar('Unsafe', $unsafe, 'unsafe', $max),
            $this->bar('Unchecked', $unchecked, 'unchecked', $max),
        ];
    }

    public function dispatchBars(int $dispatched, int $onScene, int $standby): array
    {
        $max = max($dispatched, $onScene, $standby, 1);
        return [
            $this->bar('Dispatched', $dispatched, 'dispatched', $max),
            $this->bar('On-scene', $onScene, 'on-scene', $max),
            $this->bar('Stand-by', $standby, 'standby', $max),
        ];
    }

    public function sumStatusKeys(object $counts, array $keys): int
    {
        $total = 0;
        foreach ($keys as $key) $total += (int) ($counts[$key] ?? 0);
        return $total;
    }

    public function statusKey(?string $value): string
    {
        return strtolower(str_replace([' ', '_'], '-', $value ?? 'unknown'));
    }

    public function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    public function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    public function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }

    private function bar(string $label, int $value, string $className, int $max): array
    {
        $height = $value > 0 ? max(8, round(($value / $max) * 100)) : 0;
        return ['label' => $label, 'value' => $value, 'height' => $height.'%', 'class_name' => $className];
    }
}


