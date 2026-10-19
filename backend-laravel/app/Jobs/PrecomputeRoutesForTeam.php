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

    public function __construct(public int $teamId)
    {
        $this->onQueue('navigation');
    }

    public function uniqueId(): string
    {
        return (string) $this->teamId;
    }

    public function handle(RoutePrecomputeService $routes): void
    {
        $routes->precomputeTeam($this->teamId);
    }
}