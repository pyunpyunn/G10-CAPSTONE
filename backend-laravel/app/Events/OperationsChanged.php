<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OperationsChanged implements ShouldBroadcastNow
{
    public function __construct(public array $topics, public string $batchId) {}

    public function broadcastOn(): array
    {
        return array_map(fn (string $topic) => new PrivateChannel('operations.'.$topic), $this->topics);
    }

    public function broadcastAs(): string
    {
        return 'operations.changed';
    }

    public function broadcastWith(): array
    {
        return ['topics' => $this->topics, 'batch_id' => $this->batchId, 'emitted_at_ms' => (int) (microtime(true) * 1000)];
    }
}
