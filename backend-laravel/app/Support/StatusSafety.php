<?php

namespace App\Support;

class StatusSafety
{
    public static function basis(string $key, ?string $configured = null): string
    {
        if (in_array($configured, ['safe', 'unsafe'], true)) return $configured;
        return in_array($key, ['safe', 'safe_at_home', 'active', 'returned', 'evacuated', 'relocated'], true) ? 'safe' : 'unsafe';
    }
}
