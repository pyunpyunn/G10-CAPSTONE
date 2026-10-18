<?php

namespace App\Support;

class StatusPalette
{
    public static function tone(?string $status): string
    {
        $key = str_replace(['-', ' '], '_', strtolower(trim((string) $status)));
        if (array_key_exists($key, config('status_palette'))) return $key;
        return match ($key) {
            'safe', 'safe_only', 'safe_total', 'safe_at_home', 'all_members_safe', 'checked', 'active', 'returned', 'returned_home', 'available', 'on_duty', 'completed', 'approved', 'validated', 'verified', 'forwarded', 'fulfilled', 'delivered', 'released', 'rescued', 'responded', 'sent', 'normal', 'low', 'success' => 'green',
            'evacuated', 'relocated', 'dispatched', 'assigned', 'already_assigned', 'accepted', 'en_route', 'deployed', 'acknowledged', 'in_review', 'reserve', 'info' => 'blue',
            'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'not_safe', 'not_evacuated', 'displaced', 'missing', 'injured', 'trapped', 'unreachable', 'deceased', 'failed', 'rejected', 'critical', 'high', 'urgent', 'danger', 'error' => 'red',
            'pending', 'needs_validation', 'returning', 'unavailable', 'warning', 'warn', 'moderate', 'medium', 'new', 'watch' => 'amber',
            'on_scene', 'onscene', 'reviewed', 'archived', 'generated' => 'purple',
            default => 'gray',
        };
    }

    public static function color(?string $status): string
    {
        return config('status_palette.'.self::tone($status));
    }
}
