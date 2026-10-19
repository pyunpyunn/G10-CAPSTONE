<?php

namespace App\Console\Commands;

use App\Services\Navigation\RoutePrecomputeService;
use Illuminate\Console\Command;

class RouteCoverageReport extends Command
{
    protected $signature = 'navigation:route-coverage {--team= : Limit report to one team ID}';
    protected $description = 'Report cached household route coverage and every failed route target';

    public function handle(RoutePrecomputeService $routes): int
    {
        $teamId = $this->option('team') !== null ? (int) $this->option('team') : null;
        $report = $routes->coverageRows($teamId);
        $this->table(['Team ID', 'Team', 'Profile', 'Targets', 'Ready', 'Failed', 'Coverage'], array_map(
            fn (array $row): array => [$row['team_id'], $row['team_name'], $row['profile'], $row['total'], $row['ready'], $row['failed'], $row['percent'].'%'],
            $report['coverage'],
        ));

        $failures = $report['failures'];
        if ($failures !== []) {
            $this->newLine();
            $this->table(['Team ID', 'Team', 'Household', 'Evacuation center', 'Profile', 'Reason'], array_map(
                fn (array $row): array => [$row['team_id'], $row['team_name'], $row['household_id'] ?? '—', $row['evac_center_id'] ?? '—', $row['profile'], $row['failure_reason']],
                $failures,
            ));
        }

        $this->info('Failed route targets: '.count($failures));

        return self::SUCCESS;
    }
}