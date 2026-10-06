<?php

namespace App\Jobs;

use App\Services\Shared\OneSignalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDispatchChangePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public array $responderIds,
        public int $assignmentId,
        public string $code,
        public string $eventId,
        public string $change,
    ) {}

    public function handle(OneSignalNotificationService $push): void
    {
        $message = match ($this->change) {
            'household_reported_safe' => 'Household reported safe. Assignment '.$this->code.' has been withdrawn; confirm your current location and await an updated task.',
            'completed' => 'Assignment '.$this->code.' has been completed. Open the app for the final outcome.',
            default => 'Assignment '.$this->code.' has been updated. Open the app for details.',
        };

        $result = $push->sendToResponderIds($this->responderIds, 'Dispatch update', $message, [
            'type' => 'rescue_dispatch_change',
            'change' => $this->change,
            'assignment_id' => (string) $this->assignmentId,
            'event_id' => $this->eventId,
        ]);

        if ($result['status'] === 'failed') {
            throw new \RuntimeException('Dispatch update push failed; retry requested.');
        }
    }
}
