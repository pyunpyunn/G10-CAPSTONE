<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PersonnelAccessChanged implements ShouldBroadcastNow
{
    public function __construct(public string $userId) {}
    public function broadcastOn(): array { return [new PrivateChannel('users.'.$this->userId)]; }
    public function broadcastAs(): string { return 'personnel-access.changed'; }
    public function broadcastWith(): array { return ['user_id' => $this->userId]; }
}
