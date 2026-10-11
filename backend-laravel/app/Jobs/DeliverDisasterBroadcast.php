<?php

namespace App\Jobs;

use App\Services\Web\DisasterBroadcastWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverDisasterBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public int $broadcastId, public array $validated, public array $metadata, public string $eventId) {}

    public function handle(DisasterBroadcastWorkflow $workflow): void
    {
        $workflow->deliverBroadcast($this->broadcastId, $this->validated, $this->metadata, $this->eventId);
    }
}
