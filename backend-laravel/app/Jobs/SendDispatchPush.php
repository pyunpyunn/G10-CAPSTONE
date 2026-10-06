<?php

namespace App\Jobs;

use App\Services\Shared\OneSignalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDispatchPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public array $responderIds, public int $assignmentId, public string $code, public string $area, public string $eventId) {}

    public function handle(OneSignalNotificationService $oneSignal): void
    {
        $result = $oneSignal->sendToResponderIds($this->responderIds, 'New rescue dispatch',
            'Assignment '.$this->code.' for '.$this->area.'.', [
                'type' => 'rescue_dispatch',
                'assignment_id' => (string) $this->assignmentId,
                'assignment_code' => $this->code,
                'event_id' => $this->eventId,
            ]);
        if ($result['status'] === 'failed') throw new \RuntimeException('Dispatch push delivery failed; queued retry requested.');
    }
}
