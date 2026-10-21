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

    public function __construct(public array $responderIds, public int $assignmentId, public string $code, public string $area, public string $eventId, public int $totalHouseholds = 0) {}

    public function handle(OneSignalNotificationService $oneSignal): void
    {
        $result = $oneSignal->sendToResponderIds($this->responderIds, 'New rescue dispatch',
            'Assigned sitio: '.$this->area.'. Total target households: '.$this->totalHouseholds.'. Assignment '.$this->code.'.', [
                'type' => 'rescue_dispatch',
                'assignment_id' => (string) $this->assignmentId,
                'assignment_code' => $this->code,
                'event_id' => $this->eventId,
                'assigned_sitio' => $this->area,
                'total_target_households' => $this->totalHouseholds,
                'alarm' => config('services.onesignal.dispatch_alarm'),
                'alarm_id' => 'dispatch:'.$this->assignmentId,
                'screen' => '/rescuer/navigation',
            ]);
        if ($result['status'] === 'failed') throw new \RuntimeException('Dispatch push delivery failed; queued retry requested.');
    }
}
