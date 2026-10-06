<?php

namespace App\Jobs;

use App\Services\Mobile\RescuerAssignmentWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshResponderRoadRoute implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public int $assignmentId, public array $start) {}

    public function handle(RescuerAssignmentWorkflow $workflow): void
    {
        $workflow->refreshPlannedRoadRoute($this->assignmentId, $this->start);
    }
}
