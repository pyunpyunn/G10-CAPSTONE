<?php

namespace App\Console\Commands;

use App\Services\Navigation\TeamBaseInitializationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeTeamBases extends Command
{
    protected $signature = 'navigation:initialize-team-bases';
    protected $description = 'Create missing team bases from the configured rescue-office location';

    public function handle(TeamBaseInitializationService $bases): int
    {
        $teamIds = DB::table('rescue_teams')->orderBy('team_id')->pluck('team_id');
        $created = 0;
        $missing = 0;

        foreach ($teamIds as $teamId) {
            $existing = DB::table('rescue_teams')->where('team_id', $teamId)->whereNotNull('base_latitude')->whereNotNull('base_longitude')->exists();
            if ($bases->ensureDefaultBase((int) $teamId)) {
                $created += $existing ? 0 : 1;
            } else {
                $missing++;
                $this->warn("Team {$teamId} has no configured base coordinates.");
            }
        }

        $this->info("Initialized {$created} team bases; {$missing} teams still need a configured base.");

        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }
}