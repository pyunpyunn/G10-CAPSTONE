<?php

namespace App\Jobs;

use App\Services\Navigation\RoutePrecomputeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrecomputeRoutesForTeam implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [60, 300];
    public int $uniqueFor = 1800;

    public function __construct(public int $teamId, public ?string $householdId = null)
    {
        $this->onQueue('navigation');
    }

    public function uniqueId(): string
    {
        return $this->teamId.($this->householdId !== null ? ':'.$this->householdId : '');
    }

    public function handle(RoutePrecomputeService $routes): void
    {
        if ($this->householdId !== null) {
            $routes->precomputeHouseholdForTeam($this->teamId, $this->householdId);
        } else {
            $routes->precomputeTeam($this->teamId);
        }
    }
}
