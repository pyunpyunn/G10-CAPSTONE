<?php

namespace App\Services\Web;

use App\Jobs\DeliverDisasterBroadcast;
use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\RescuePrioritySetting;
use App\Queries\DisasterBroadcastQuery;
use App\Presenters\DisasterBroadcastPresenter;
use App\Services\Shared\RealtimeOutbox;
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
            do {
                $eventId = 'EVT-'.$now->format('Ymd').'-'.random_int(1000, 9999);
            } while (DisasterEvent::query()->where('event_id', $eventId)->exists());

            $event = [
                'event_id' => $eventId,
                'name' => $validated['name'],
                'type_id' => $validated['type_id'],
                'severity_level_id' => $validated['severity_level_id'] ?? null,
                'started_at' => $validated['started_at'] ?? $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
            if (Schema::hasColumn('disaster_events', 'rescue_priority_version')) {
                $event['rescue_priority_version'] = RescuePrioritySetting::query()->orderByDesc('version')->value('version');
            }
            DisasterEvent::query()->create($event);
            $this->syncEventTypeAssociation($eventId, (int) $validated['type_id']);

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
            $this->syncEventTypeAssociation($eventId, (int) $validated['type_id']);

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

        $validated = $this->validator->validateBroadcast($request, (int) $event->type_id);
        $metadata = $this->broadcastMetadata($validated);

        $broadcastId = DB::transaction(function () use ($request, $validated, &$metadata, $event): int {
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
                'channel' => 'onesignal',
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
                $data['direct_impact_puroks_json'] = $metadata['puroks'];
            }

            if (Schema::hasColumn('disaster_broadcasts', 'allowed_statuses_json')) {
                $data['allowed_statuses_json'] = $metadata['statuses'];
            }

            if (Schema::hasColumn('disaster_broadcasts', 'recipient_count')) {
                $data['recipient_count'] = $this->query->recipientCount($validated['scope_type'], $metadata['puroks']);
            }

            if (Schema::hasColumn('disaster_broadcasts', 'push_status')) {
                $data['push_status'] = 'pending_mobile_push';
            }

            if (Schema::hasColumn('disaster_broadcasts', 'status_options_json')) {
                $data['status_options_json'] = $this->jsonText($metadata['status_options']);
            }
            DisasterBroadcast::query()->create($data);
            // Persist the audience and UUIDs in the transactional outbox payload.
            // Queue retries must reuse exactly the same provider request.
            $metadata['push_plan'] = $this->oneSignal->prepareDelivery([
                'platforms' => ['android'],
                'roles' => ['household'],
                'household_puroks' => $validated['scope_type'] === 'selected_puroks'
                    ? collect($metadata['puroks'])->pluck('name')->filter()->values()->all() : [],
            ]);
            $metadata['rescuer_push_plan'] = $this->oneSignal->prepareDelivery(['roles' => ['rescuer'], 'platforms' => ['android']]);
            DeliverDisasterBroadcast::dispatch($broadcastId, $validated, $metadata, (string) $event->event_id)
                ->onConnection('operations_outbox')->onQueue('broadcasts');

            return $broadcastId;
        });

        app(RealtimeOutbox::class)->deliverPendingNow();
        $pushResult = ['status' => 'queued', 'recipient_count' => 0, 'sent_count' => 0];
        try {
            $pushResult = $this->sendBroadcastPush($broadcastId, $validated, $metadata, $event);
            $this->updateBroadcastPushStatus($broadcastId, $pushResult);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Immediate disaster broadcast push failed; queued delivery will retry.', [
                'broadcast_id' => $broadcastId,
                'message' => $exception->getMessage(),
            ]);
        }
        app(RealtimeOutbox::class)->deliverPendingNow();
        $smsResult = ['status' => 'queued', 'recipient_count' => 0, 'accepted_count' => 0];

        return response()->json([
            'message' => 'Broadcast saved. Mobile push was submitted immediately; SMS delivery is queued.',
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

        $roles = ['household'];
        $statusOptions = $metadata['status_options'] ?? $this->selectedStatusOptions($validated['allowed_statuses']);
        $reportPrompt = 'Please report status now';
        $statusLines = array_map(
            fn (array $option, int $index): string => ($index + 1).'. '.$option['label'],
            array_values($statusOptions),
            array_keys(array_values($statusOptions))
        );
        $pushMessage = $validated['message']."\n\n".$reportPrompt."\n".implode("\n", $statusLines);

        $householdResult = $this->oneSignal->sendToMobileDevices(
            $validated['broadcast_title'],
            $pushMessage,
            [
                'platforms' => ['android'],
                'roles' => $roles,
                ...(isset($metadata['push_plan']) ? ['delivery_plan' => $metadata['push_plan']] : []),
                'household_puroks' => $purokNames,
                'data' => [
                    'type' => 'disaster_broadcast',
                    'event_id' => $event->event_id,
                    'broadcast_id' => (string) $broadcastId,
                    'scope_type' => $scope,
                    'allowed_statuses' => array_values($validated['allowed_statuses']),
                    'status_options' => $statusOptions,
                    'report_status_prompt' => $reportPrompt,
                ],
            ]
        );
        $rescuerResult = $this->oneSignal->sendToMobileDevices($validated['broadcast_title'], $validated['message'], [
            'platforms' => ['android'],
            'roles' => ['rescuer'],
            ...(isset($metadata['rescuer_push_plan']) ? ['delivery_plan' => $metadata['rescuer_push_plan']] : []),
            'data' => [
                'type' => 'rescuer_broadcast', 'event_id' => $event->event_id,
                'broadcast_id' => (string) $broadcastId, 'scope_type' => $scope,
                'target_puroks' => $purokNames,
            ],
        ]);
        $results = [$householdResult, $rescuerResult];
        $sent = array_sum(array_column($results, 'sent_count'));
        $errors = array_merge(...array_column($results, 'errors'));
        $unsuccessful = array_filter($results, fn ($result) => ! in_array($result['status'], ['sent', 'no_recipients'], true));
        if ($sent === 0 && $unsuccessful === []) {
            $errors = array_values(array_unique(array_column($results, 'message')));
        }
        $status = $sent > 0
            ? ($unsuccessful ? 'partial' : 'sent')
            : ($unsuccessful ? $unsuccessful[array_key_first($unsuccessful)]['status'] : 'no_recipients');

        return [
            'status' => $status,
            'recipient_count' => array_sum(array_column($results, 'recipient_count')),
            'sent_count' => $sent, 'provider_ids' => array_merge(...array_column($results, 'provider_ids')),
            'errors' => $errors,
            'message' => $status === 'no_recipients'
                ? 'No eligible OneSignal mobile subscriptions are registered for household or rescuer accounts.'
                : 'Household and rescuer broadcast delivery submitted.',
        ];
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

        $updates = [
            'push_status' => 'onesignal_'.$pushResult['status'],
            'channel' => 'onesignal',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('disaster_broadcasts', 'push_accepted_count')) {
            $updates['push_accepted_count'] = (int) ($pushResult['sent_count'] ?? 0);
        }
        $broadcast = DisasterBroadcast::query()->where('broadcast_id', $broadcastId)->first();
        $existingIds = $this->presenter->decodeJsonArray($broadcast?->push_provider_ids_json);
        $providerIds = array_values(array_unique(array_filter([
            ...$existingIds, $broadcast?->onesignal_id, ...($pushResult['provider_ids'] ?? []),
        ], fn ($id) => is_string($id) && trim($id) !== '')));
        if (Schema::hasColumn('disaster_broadcasts', 'onesignal_id')) {
            $updates['onesignal_id'] = $providerIds[0] ?? null;
        }
        if (Schema::hasColumn('disaster_broadcasts', 'push_provider_ids_json')) {
            $updates['push_provider_ids_json'] = $this->jsonText($providerIds);
        }
        if (Schema::hasColumn('disaster_broadcasts', 'push_errors_json')) {
            $errors = $pushResult['errors'] ?? [];
            if ($errors === [] && ! in_array($pushResult['status'], ['sent', 'queued'], true)) {
                $errors = [$pushResult['message'] ?? 'OneSignal did not accept this notification.'];
            }
            $updates['push_errors_json'] = $this->jsonText(array_values($errors));
        }

        DisasterBroadcast::query()
            ->where('broadcast_id', $broadcastId)
            ->update($this->query->filterColumns('disaster_broadcasts', $updates));
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'notif_id')) {
            $hasSource = Schema::hasColumn('notifications', 'source_key');
            $sourceKey = 'broadcast-'.$broadcastId;
            $notificationId = $broadcast?->notification_id;
            $notificationValues = $this->query->filterColumns('notifications', [
                'source_key' => $sourceKey,
                'message' => $broadcast?->message,
                'sent_by' => $broadcast?->sent_by_admin_id,
                'created_at' => $broadcast?->created_at ?? $broadcast?->sent_at ?? now(),
                'channel' => 'onesignal',
                'status' => $pushResult['status'],
                'target_filter' => $broadcast?->scope_type,
                'onesignal_id' => $providerIds[0] ?? null,
                'onesignal_ids_json' => $this->jsonText($providerIds),
            ]);

            if ($hasSource) {
                DB::table('notifications')->insertOrIgnore($notificationValues);
                $notificationId = DB::table('notifications')
                    ->where('source_key', $sourceKey)
                    ->value('notif_id') ?? $notificationId;
            } elseif ($notificationId) {
                DB::table('notifications')->where('notif_id', $notificationId)->update($notificationValues);
            } elseif ($broadcast) {
                $notificationId = DB::table('notifications')->insertGetId($notificationValues, 'notif_id');
            }

            if ($notificationId && Schema::hasColumn('disaster_broadcasts', 'notification_id')) {
                DB::table('disaster_broadcasts')
                    ->where('broadcast_id', $broadcastId)
                    ->whereNull('notification_id')
                    ->update(['notification_id' => $notificationId]);
            }

            if ($notificationId) {
                $notification = DB::table('notifications')->where('notif_id', $notificationId)->first();
                if ($notification) {
                    $ids = array_values(array_unique([
                        ...$this->presenter->decodeJsonArray($notification->onesignal_ids_json ?? null),
                        ...array_filter([$notification->onesignal_id ?? null]),
                        ...$providerIds,
                    ]));
                    $updates = $this->query->filterColumns('notifications', [
                        'status' => $pushResult['status'],
                        'onesignal_id' => $ids[0] ?? null,
                        'onesignal_ids_json' => $this->jsonText($ids),
                    ]);
                    DB::table('notifications')->where('notif_id', $notificationId)->update($updates);
                }
            }
        }

    }

    public function broadcastMetadata(array $validated): array
    {
        return [
            'statuses' => array_values($validated['allowed_statuses']),
            'status_options' => $this->selectedStatusOptions($validated['allowed_statuses']),
            'puroks' => array_values($validated['direct_puroks'] ?? []),
            'area' => $validated['target_area'] ?? $this->presenter->scopeLabel($validated['scope_type']),
            'duration' => $validated['estimated_duration'] ?? null,
        ];
    }

    private function selectedStatusOptions(array $keys): array
    {
        $catalog = collect($this->presenter->statusOptions())->keyBy('key');
        return array_map(fn ($key) => $catalog->get($key) ?? [
            'key' => $key, 'label' => ucwords(str_replace('_', ' ', $key)),
            'safety_basis' => \App\Support\StatusSafety::basis($key),
        ], array_values($keys));
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

    private function syncEventTypeAssociation(string $eventId, int $typeId): void
    {
        if (! Schema::hasTable('disaster_event_types')
            || ! Schema::hasColumn('disaster_event_types', 'event_type_id')
            || ! Schema::hasColumn('disaster_event_types', 'event_id')
            || ! Schema::hasColumn('disaster_event_types', 'type_id')) {
            return;
        }

        $alreadyLinked = DB::table('disaster_event_types')
            ->where('event_id', $eventId)
            ->where('type_id', $typeId)
            ->exists();
        if ($alreadyLinked) return;

        DB::table('disaster_event_types')->where('event_id', $eventId)->delete();
        $unassigned = DB::table('disaster_event_types')
            ->whereNull('event_id')
            ->where('type_id', $typeId)
            ->lockForUpdate()
            ->first();

        if ($unassigned) {
            DB::table('disaster_event_types')
                ->where('event_type_id', $unassigned->event_type_id)
                ->update(['event_id' => $eventId]);
            return;
        }

        $eventTypeId = ((int) DB::table('disaster_event_types')->lockForUpdate()->max('event_type_id')) + 1;
        DB::table('disaster_event_types')->insert(['event_type_id' => $eventTypeId, 'event_id' => $eventId, 'type_id' => $typeId]);
    }
}





