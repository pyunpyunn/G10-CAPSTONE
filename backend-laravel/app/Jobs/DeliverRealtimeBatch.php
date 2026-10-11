<?php

namespace App\Jobs;

use App\Services\Shared\RealtimeOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeliverRealtimeBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public function __construct(public string $token) {}

    public function backoff(): array
    {
        return [1, 5, 10, 20];
    }

    public function handle(RealtimeOutbox $outbox): void
    {
        $outbox->deliver($this->token);
    }

    public function failed(?Throwable $exception): void
    {
        app(RealtimeOutbox::class)->release($this->token, 30);
    }
}
