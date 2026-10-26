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

        return TeamBase::query()->firstOrCreate(
            ['team_id' => $teamId],
            [
                'name' => (string) ($office['name'] ?? 'Rescue office'),
                'lat' => (float) $office['latitude'],
                'lng' => (float) $office['longitude'],
            ],
        );
    }
}