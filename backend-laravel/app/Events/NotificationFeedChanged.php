<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NotificationFeedChanged implements ShouldBroadcastNow
{
    public function __construct(public ?string $userId = null, public ?string $batchId = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->userId === null ? 'notifications.admin' : 'users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notifications.changed';
    }

    public function broadcastWith(): array
    {
        // Fetch the authenticated feed; never expose operational records in a socket payload.
        return ['batch_id' => $this->batchId];
    }
}
