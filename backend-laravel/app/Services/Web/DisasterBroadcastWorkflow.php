<?php

namespace App\Services\Web;

use App\Jobs\DeliverDisasterBroadcast;
use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Queries\DisasterBroadcastQuery;
use App\Presenters\DisasterBroadcastPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;

class DisasterBroadcastWorkflow
{
    public function __construct(private DisasterBroadcastQuery $query, private DisasterBroadcastPresenter $presenter, private DisasterBroadcastRequestValidator $validator, private \App\Services\Shared\OneSignalNotificationService $oneSignal, private \App\Services\Shared\SmsGatewayService $smsGateway) {}

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $this->validator->validateEvent($request);

        if ($this->query->getActiveEvent()) {
            return response()->json([
                'message' => 'There is already an active disaster event. Close the active event before declaring a new one.',
            ], 409);
        }

        $eventId = DB::transaction(function () use ($validated): string {
            $now = now();
            $eventId = 'EVT-'.$now->format('Ymd').'-'.Str::upper(Str::random(5));

            DisasterEvent::query()->create([
                'event_id' => $eventId,
                'name' => $validated['name'],
                'type_id' => $validated['type_id'],
                'severity_level_id' => $validated['severity_level_id'],
                'started_at' => $validated['started_at'] ?? $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            return $eventId;
        });

        $event = $this->query->findEvent($eventId);

        return response()->json([
            'message' => 'Disaster event declared. Household reporting can now start after the broadcast is saved.',
            'data' => $this->query->getWorkspacePayload($event, []),
        ], 201);
    }

    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        $validated = $this->validator->validateEventUpdate($request);

        $result = DB::transaction(function () use ($eventId, $validated): string {
            $event = DisasterEvent::query()
                ->where('event_id', $eventId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $event) {
                return 'not_found';
            }

            if ($event->ended_at) {
                return 'closed';
            }

            $event->fill($validated);
            $event->save();

            return 'updated';
        });

        if ($result !== 'updated') {
            return response()->json([
                'message' => $result === 'closed'
                    ? 'This disaster event is already closed and cannot be updated.'
                    : 'Disaster event record was not found.',
            ], $result === 'closed' ? 409 : 404);
        }

        $event = $this->query->findEvent($eventId);

        return response()->json([
            'message' => 'Disaster event updated.',
            'data' => $this->query->getWorkspacePayload($event, $this->query->getBroadcastsForEvent($eventId)),
        ]);
    }

    public function storeBroadcast(Request $request, string $eventId): JsonResponse
    {
        $event = $this->query->findEvent($eventId);

        if (! $event) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        if ($event->ended_at) {
            return response()->json([
                'message' => 'This disaster event is already closed. Create a new event before sending another broadcast.',
            ], 409);
        }

        $validated = $this->validator->validateBroadcast($request);
        $metadata = $this->broadcastMetadata($validated);

        $broadcastId = DB::transaction(function () use ($request, $validated, $metadata, $event): int {
            $lockedEvent = DisasterEvent::query()->where('event_id', $event->event_id)->lockForUpdate()->first();
            abort_if(! $lockedEvent || $lockedEvent->ended_at || $lockedEvent->deleted_at, 409, 'The disaster event is no longer active.');
            $now = now();
            $broadcastId = $this->query->nextId('disaster_broadcasts', 'broadcast_id');

            $data = [
                'broadcast_id' => $broadcastId,
                'broadcast_title' => $validated['broadcast_title'],
                'disaster_id' => $event->event_id,
                'sent_by_admin_id' => $request->user()?->user_id,
                'severity_id' => $validated['severity_id'] ?? $event->severity_level_id,
                'scope_type' => $validated['scope_type'],
                'target_purok_id' => $this->query->firstPurokId($validated['direct_puroks'] ?? []),
                'target_area_id' => null,
                'message' => $validated['message'],
                'allowed_statuses' => $this->compactMetadataText($metadata),
                'channel' => $validated['channel'] ?? 'mobile_app_pending_push',
                'status' => 'saved',
                'notification_id' => null,
                'weather_log_id' => null,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('disaster_broadcasts', 'target_area_label')) {
                $data['target_area_label'] = $metadata['area'];
            }

            if (Schema::hasColumn('disaster_broadcasts', 'direct_impact_puroks_json')) {
                $data['direct_impact_puroks_json'] = $this->jsonText($metadata['puroks']);
            }

            if (Schema::hasColumn('disaster_broadcasts', 'allowed_statuses_json')) {
                $data['allowed_statuses_json'] = $this->jsonText($metadata['statuses']);
            }

            if (Schema::hasColumn('disaster_broadcasts', 'recipient_count')) {
                $data['recipient_count'] = $this->query->recipientCount($validated['scope_type'], $metadata['puroks']);
            }

            if (Schema::hasColumn('disaster_broadcasts', 'push_status')) {
                $data['push_status'] = 'pending_mobile_push';
            }

            DisasterBroadcast::query()->create($data);
            // Persist the audience and UUIDs in the transactional outbox payload.
            // Queue retries must reuse exactly the same provider request.
            $metadata['push_plan'] = $this->oneSignal->prepareDelivery([
                'roles' => $validated['scope_type'] === 'selected_puroks' ? ['household'] : ['household', 'rescuer'],
                'household_puroks' => $validated['scope_type'] === 'selected_puroks'
                    ? collect($metadata['puroks'])->pluck('name')->filter()->values()->all() : [],
            ]);
            DeliverDisasterBroadcast::dispatch($broadcastId, $validated, $metadata, (string) $event->event_id)
                ->onConnection('operations_outbox')->onQueue('operations');

            return $broadcastId;
        });

        $pushResult = ['status' => 'queued', 'recipient_count' => 0, 'sent_count' => 0];
        $smsResult = ['status' => 'queued', 'recipient_count' => 0, 'accepted_count' => 0];

        return response()->json([
            'message' => 'Broadcast saved. Push and SMS delivery are queued.',
            'data' => array_merge($this->query->getWorkspacePayload($event, $this->query->getBroadcastsForEvent($event->event_id)), [
                'broadcast' => $this->query->findBroadcast($broadcastId),
                'push_delivery' => $pushResult,
                'sms_delivery' => $smsResult,
            ]),
        ], 201);
    }

    public function deliverBroadcast(int $broadcastId, array $validated, array $metadata, string $eventId): void
    {
        $broadcast = DisasterBroadcast::query()->where('broadcast_id', $broadcastId)->first();
        $event = $this->query->findEvent($eventId);
        if (! $broadcast || ! $event) return;

        $pushFailed = false;
        $smsFailed = false;
        if (in_array($broadcast->push_status ?? 'pending_mobile_push', ['pending_mobile_push', 'onesignal_failed', 'onesignal_partial'], true)) {
            $push = $this->sendBroadcastPush($broadcastId, $validated, $metadata, $event);
            $this->updateBroadcastPushStatus($broadcastId, $push);
            $pushFailed = in_array($push['status'], ['failed', 'partial'], true);
        }
        if (in_array($broadcast->sms_status ?? 'queued', ['queued', 'smsgate_failed'], true)) {
            $sms = $this->sendBroadcastSms($validated, $metadata);
            $this->updateBroadcastSmsStatus($broadcastId, $sms);
            $smsFailed = $sms['status'] === 'failed';
        }
        if ($pushFailed || $smsFailed) throw new \RuntimeException('Broadcast provider delivery failed; queued retry requested.');
    }

    public function sendBroadcastSms(array $validated, array $metadata): array
    {
        $puroks = $validated['scope_type'] === 'selected_puroks'
            ? collect($metadata['puroks'])->pluck('name')->filter()->values()->all()
            : [];

        return $this->smsGateway->sendBroadcastSms(
            $validated['broadcast_title'].': '.$validated['message'],
            ['household_puroks' => $puroks]
        );
    }

    public function updateBroadcastSmsStatus(int $broadcastId, array $result): void
    {
        if (Schema::hasTable('disaster_broadcasts') && Schema::hasColumn('disaster_broadcasts', 'sms_status')) {
            DisasterBroadcast::query()->where('broadcast_id', $broadcastId)->update([
                'sms_status' => 'smsgate_'.$result['status'],
                'updated_at' => now(),
            ]);
        }
    }

    public function sendBroadcastPush(int $broadcastId, array $validated, array $metadata, object $event): array
    {
        $scope = $validated['scope_type'];
        $purokNames = $scope === 'selected_puroks'
            ? collect($metadata['puroks'])->pluck('name')->filter()->values()->all()
            : [];

        $roles = $scope === 'selected_puroks' ? ['household'] : ['household', 'rescuer'];

        return $this->oneSignal->sendToMobileDevices(
            $validated['broadcast_title'],
            $validated['message'],
            [
                'roles' => $roles,
                ...(isset($metadata['push_plan']) ? ['delivery_plan' => $metadata['push_plan']] : []),
                'household_puroks' => $purokNames,
                'data' => [
                    'type' => 'disaster_broadcast',
                    'event_id' => $event->event_id,
                    'broadcast_id' => (string) $broadcastId,
                    'scope_type' => $scope,
                    'allowed_statuses' => array_values($validated['allowed_statuses']),
                ],
            ]
        );
    }

    public function updateBroadcastPushStatus(int $broadcastId, array $pushResult): void
    {
        \Illuminate\Support\Facades\Log::info('Disaster broadcast push submission completed', [
            'broadcast_id' => $broadcastId,
            'status' => $pushResult['status'],
            'recipient_count' => $pushResult['recipient_count'],
            'accepted_count' => $pushResult['sent_count'],
            'provider_ids' => $pushResult['provider_ids'] ?? [],
            'errors' => $pushResult['errors'] ?? [],
        ]);
        if (! Schema::hasTable('disaster_broadcasts') || ! Schema::hasColumn('disaster_broadcasts', 'push_status')) {
            return;
        }

        DisasterBroadcast::query()
            ->where('broadcast_id', $broadcastId)
            ->update($this->query->filterColumns('disaster_broadcasts', [
                'push_status' => 'onesignal_'.$pushResult['status'],
                'channel' => 'onesignal',
                'updated_at' => now(),
            ]));
    }

    public function broadcastMetadata(array $validated): array
    {
        return [
            'statuses' => array_values($validated['allowed_statuses']),
            'puroks' => array_values($validated['direct_puroks'] ?? []),
            'area' => $validated['target_area'] ?? $this->presenter->scopeLabel($validated['scope_type']),
            'duration' => $validated['estimated_duration'] ?? null,
        ];
    }

    public function compactMetadataText(array $metadata): string
    {
        return $this->jsonText([
            'statuses' => $metadata['statuses'],
            'duration' => $metadata['duration'],
        ]);
    }

    public function jsonText(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}







