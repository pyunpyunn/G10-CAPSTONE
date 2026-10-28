<?php

namespace App\Services\Navigation;

use App\Models\TeamBase;
use Illuminate\Support\Facades\DB;

class TeamBaseInitializationService
{
    public function ensureDefaultBase(int $teamId): ?TeamBase
    {
        $existing = TeamBase::query()->find($teamId);
        if ($existing) {
            return $existing;
        }

        $office = config('rescue_office', []);
        if (! is_numeric($office['latitude'] ?? null) || ! is_numeric($office['longitude'] ?? null)) {
            return null;
        }

        if (! DB::table('rescue_teams')->where('team_id', $teamId)->exists()) {
            return null;
        }

        DB::table('rescue_teams')->where('team_id', $teamId)->whereNull('base_latitude')->whereNull('base_longitude')->update([
            'base_name' => (string) ($office['name'] ?? 'Rescue office'),
            'base_latitude' => (float) $office['latitude'], 'base_longitude' => (float) $office['longitude'],
        ]);
        return TeamBase::query()->find($teamId);
    }
}
