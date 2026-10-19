<?php

namespace App\Jobs;

use App\Services\Navigation\TripStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeTripStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(public int $tripId)
    {
        $this->onQueue('navigation');
    }

    public function handle(TripStatsService $stats): void
    {
        $stats->recompute($this->tripId);
    }
}