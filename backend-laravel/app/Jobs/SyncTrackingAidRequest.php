<?php

namespace App\Jobs;

use App\Models\ResourceRequest;
use App\Queries\ResourceRequestQuery;
use App\Services\Shared\TrackingAidForwardingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTrackingAidRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public string $requestId,
        public bool $forward = false,
        public ?string $trackingReference = null,
        public ?string $forwardedByUserId = null,
        public ?string $forwardedByName = null,
        public ?string $forwardedByRole = null,
        public ?string $validationNotes = null,
    ) {}

    public function handle(TrackingAidForwardingService $trackingAid, ResourceRequestQuery $queries): void
    {
        $request = ResourceRequest::query()->where('request_id', $this->requestId)->first();
        if ($request === null) {
            return;
        }

        if ($this->forward) {
            if ($request->validation_status !== 'forwarded' || $request->tracking_reference !== $this->trackingReference) {
                return;
            }

            $forwarder = (object) [
                'user_id' => $this->forwardedByUserId,
                'name' => $this->forwardedByName,
                'role_key' => $this->forwardedByRole,
            ];
            $trackingAid->forwardRequest($queries->find($this->requestId) ?? $request, $this->trackingReference, $forwarder, $this->validationNotes);
        }

        // Read the current local status so an older queued update cannot restore stale state.
        $trackingAid->syncRequestStatus($this->requestId, (string) $request->validation_status, now());
    }
}
