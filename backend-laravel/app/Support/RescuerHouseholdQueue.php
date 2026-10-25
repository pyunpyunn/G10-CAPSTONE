<?php

namespace App\Support;

class RescuerHouseholdQueue
{
    public static function overallStatus(object $assignment): string
    {
        $notes = json_decode($assignment->route_notes ?? '{}', true) ?: [];
        $coverage = $notes['responder_households'] ?? [];
        $statuses = $notes['responder_statuses'] ?? [];
        if ($coverage) {
            $statuses = [];
            foreach ($coverage as $id => $households) {
                // Support members with no household queue must not keep a
                // finished dispatch open indefinitely.
                if (! $households) continue;
                $statuses[] = self::pending($assignment, (int) $id)
                    ? self::status($assignment, (int) $id) : 'completed';
            }
        }
        if (! $statuses) return str_replace('-', '_', $assignment->status ?? 'dispatched');
        foreach (['on_scene', 'en_route', 'accepted', 'dispatched', 'returning'] as $active) {
            if (in_array($active, $statuses, true)) return $active;
        }
        return in_array('cancelled', $statuses, true) ? 'cancelled' : 'completed';
    }

    public static function status(object $assignment, int $responderId): string
    {
        $notes = json_decode($assignment->route_notes ?? '{}', true) ?: [];
        return str_replace('-', '_', $notes['responder_statuses'][$responderId] ?? $assignment->status ?? 'dispatched');
    }

    public static function ids(object $assignment, int $responderId): array
    {
        $notes = json_decode($assignment->route_notes ?? '{}', true) ?: [];
        $coverage = $notes['responder_households'] ?? [];
        $directId = property_exists($assignment, 'dispatch_household_id')
            ? $assignment->dispatch_household_id : ($assignment->household_id ?? null);
        $ids = array_key_exists($responderId, $coverage)
            ? $coverage[$responderId]
            : ($directId ? [$directId] : ($notes['household_ids'] ?? []));

        return array_values(array_unique(array_map('strval', array_filter($ids))));
    }

    public static function pending(object $assignment, int $responderId): array
    {
        $notes = json_decode($assignment->route_notes ?? '{}', true) ?: [];
        return array_values(array_diff(self::ids($assignment, $responderId), $notes['completed_households'][$responderId] ?? []));
    }

    public static function target(object $assignment, int $responderId): ?string
    {
        return self::pending($assignment, $responderId)[0] ?? null;
    }
}
