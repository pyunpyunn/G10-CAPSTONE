<?php

namespace App\Console\Commands;

use App\Jobs\PrecomputeRoutesForTeam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrecomputeNavigationRoutes extends Command
{
    protected $signature = 'navigation:precompute-routes {team? : Limit precompute to one team ID} {--sync : Run jobs inline instead of queueing them}';
    protected $description = 'Precompute auto and pedestrian routes for assigned households and active evacuation centers';

    public function handle(): int
    {
        $query = DB::table('rescue_teams')->orderBy('team_id');
        if ($this->argument('team') !== null) {
            $query->where('team_id', (int) $this->argument('team'));
        }

        $teamIds = $query->pluck('team_id');
        if ($teamIds->isEmpty()) {
            $this->warn('No rescue teams matched the precompute request.');

            return self::SUCCESS;
        }

        foreach ($teamIds as $teamId) {
            if ($this->option('sync')) {
                PrecomputeRoutesForTeam::dispatchSync((int) $teamId);
            } else {
                PrecomputeRoutesForTeam::dispatch((int) $teamId)->afterCommit();
            }
        }

        $verb = $this->option('sync') ? 'Ran' : 'Queued';
        $this->info("{$verb} route precompute for {$teamIds->count()} teams.");

        return self::SUCCESS;
    }
}