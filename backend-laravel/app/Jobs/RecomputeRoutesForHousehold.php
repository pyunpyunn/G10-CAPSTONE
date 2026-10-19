<?php

namespace App\Jobs;

use App\Services\Navigation\RoutePrecomputeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecomputeRoutesForHousehold implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [30, 120];
    public int $uniqueFor = 900;

    public function __construct(public string $householdId)
    {
        $this->onQueue('navigation');
    }

    public function uniqueId(): string
    {
        return $this->householdId;
    }

    public function handle(RoutePrecomputeService $routes): void
    {
        $routes->recomputeHousehold($this->householdId);
    }
}