<?php

namespace App\Console\Commands;

use App\Jobs\PrecomputeRoutesForTeam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshNavigationRoutes extends Command
{
    protected $signature = 'navigation:refresh-routes';
    protected $description = 'Queue a nightly refresh of all cached team routes';

    public function handle(): int
    {
        $teamIds = DB::table('rescue_teams')->orderBy('team_id')->pluck('team_id');
        foreach ($teamIds as $teamId) {
            PrecomputeRoutesForTeam::dispatch((int) $teamId)->afterCommit();
        }

        $this->info("Queued nightly route refresh for {$teamIds->count()} teams.");

        return self::SUCCESS;
    }
}