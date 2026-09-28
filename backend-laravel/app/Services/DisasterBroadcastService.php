<?php

namespace App\Services;

use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\DisasterType;
use App\Models\Household;
use App\Models\SeverityLevel;
use App\Models\WeatherLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisasterBroadcastService
{
    public function __construct(
        private OneSignalNotificationService $oneSignal,
        private SmsGatewayService $smsGateway,
    ) {}

    public function index(): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();
        $eventId = $activeEvent?->event_id;

        return response()->json([
            'data' => $this->workspacePayload($activeEvent, $eventId ? $this->getBroadcastsForEvent($eventId) : []),
        ]);
    }

    public function show(string $eventId): JsonResponse
    {
        $event = $this->findEvent($eventId);

        if (! $event) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->workspacePayload($event, $this->getBroadcastsForEvent($event->event_id)),
        ]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type_id' => ['required', 'integer', 'exists:disaster_types,type_id'],
            'severity_level_id' => ['required', 'integer', 'exists:severity_levels,severity_id'],
            'started_at' => ['nullable', 'date'],
        ]);

        if ($this->getActiveEvent()) {
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

        $event = $this->findEvent($eventId);

        return response()->json([
            'message' => 'Disaster event declared. Household reporting can now start after the broadcast is saved.',
            'data' => $this->workspacePayload($event, []),
        ], 201);
    }

    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type_id' => ['required', 'integer', 'exists:disaster_types,type_id'],
            'severity_level_id' => ['required', 'integer', 'exists:severity_levels,severity_id'],
        ]);

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

        $event = $this->findEvent($eventId);

        return response()->json([
            'message' => 'Disaster event updated.',
            'data' => $this->workspacePayload($event, $this->getBroadcastsForEvent($eventId)),
        ]);
    }

    public function broadcasts(string $eventId): JsonResponse
    {
        if (! $this->findEvent($eventId)) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'broadcasts' => $this->getBroadcastsForEvent($eventId),
            ],
        ]);
    }

    public function storeBroadcast(Request $request, string $eventId): JsonResponse
    {
        $event = $this->findEvent($eventId);

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

        $validated = $this->validateBroadcast($request);
        $metadata = $this->broadcastMetadata($validated);

        $broadcastId = DB::transaction(function () use ($request, $validated, $metadata, $event): int {
            $now = now();
            $broadcastId = $this->nextId('disaster_broadcasts', 'broadcast_id');

            $data = [
                'broadcast_id' => $broadcastId,
                'broadcast_title' => $validated['broadcast_title'],
                'disaster_id' => $event->event_id,
                'sent_by_admin_id' => $request->user()?->user_id,
                'severity_id' => $validated['severity_id'] ?? $event->severity_level_id,
                'scope_type' => $validated['scope_type'],
                'target_purok_id' => $this->firstPurokId($validated['direct_puroks'] ?? []),
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
                $data['recipient_count'] = $this->recipientCount($validated['scope_type'], $metadata['puroks']);
            }

            if (Schema::hasColumn('disaster_broadcasts', 'push_status')) {
                $data['push_status'] = 'pending_mobile_push';
            }

            DisasterBroadcast::query()->create($data);

            return $broadcastId;
        });

        $pushResult = $this->sendBroadcastPush($broadcastId, $validated, $metadata, $event);
        $this->updateBroadcastPushStatus($broadcastId, $pushResult);
        $smsResult = $this->sendBroadcastSms($validated, $metadata);
        $this->updateBroadcastSmsStatus($broadcastId, $smsResult);

        return response()->json([
            'message' => $this->broadcastSaveMessage($pushResult, $smsResult),
            'data' => array_merge($this->workspacePayload($event, $this->getBroadcastsForEvent($event->event_id)), [
                'broadcast' => $this->findBroadcast($broadcastId),
                'push_delivery' => $pushResult,
                'sms_delivery' => $smsResult,
            ]),
        ], 201);
    }

    private function sendBroadcastSms(array $validated, array $metadata): array
    {
        $puroks = $validated['scope_type'] === 'selected_puroks'
            ? collect($metadata['puroks'])->pluck('name')->filter()->values()->all()
            : [];

        return $this->smsGateway->sendBroadcastSms(
            $validated['broadcast_title'].': '.$validated['message'],
            ['household_puroks' => $puroks]
        );
    }

    private function updateBroadcastSmsStatus(int $broadcastId, array $result): void
    {
        if (Schema::hasTable('disaster_broadcasts') && Schema::hasColumn('disaster_broadcasts', 'sms_status')) {
            DisasterBroadcast::query()->where('broadcast_id', $broadcastId)->update([
                'sms_status' => 'smsgate_'.$result['status'],
                'updated_at' => now(),
            ]);
        }
    }

    private function sendBroadcastPush(int $broadcastId, array $validated, array $metadata, object $event): array
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

    private function updateBroadcastPushStatus(int $broadcastId, array $pushResult): void
    {
        if (! Schema::hasTable('disaster_broadcasts') || ! Schema::hasColumn('disaster_broadcasts', 'push_status')) {
            return;
        }

        DisasterBroadcast::query()
            ->where('broadcast_id', $broadcastId)
            ->update($this->filterColumns('disaster_broadcasts', [
                'push_status' => 'onesignal_'.$pushResult['status'],
                'channel' => 'onesignal',
                'updated_at' => now(),
            ]));
    }

    private function broadcastSaveMessage(array $pushResult, array $smsResult): string
    {
        $pushMessage = match ($pushResult['status']) {
            'sent' => 'Broadcast saved and sent through OneSignal to '.$pushResult['sent_count'].' device(s).',
            'partial' => 'Broadcast saved. OneSignal sent to '.$pushResult['sent_count'].' of '.$pushResult['recipient_count'].' device(s).',
            'no_recipients' => 'Broadcast saved, but no OneSignal-ready mobile devices were found.',
            'not_configured' => 'Broadcast saved, but OneSignal credentials are not configured.',
            default => 'Broadcast saved, but OneSignal delivery failed. Check backend logs and OneSignal credentials.',
        };

        return $pushMessage.' SMS: '.$smsResult['status'].'.';
    }

    private function validateBroadcast(Request $request): array
    {
        $validated = $request->validate([
            'broadcast_title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'severity_id' => ['nullable', 'integer', 'exists:severity_levels,severity_id'],
            'scope_type' => ['required', 'string', Rule::in(['barangay_wide', 'selected_puroks'])],
            'target_area' => ['nullable', 'string', 'max:150'],
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'allowed_statuses' => ['required', 'array', 'size:4'],
            'allowed_statuses.*' => ['required', 'string', 'max:40'],
            'direct_puroks' => ['nullable', 'array', 'max:5'],
            'direct_puroks.*.name' => ['required_with:direct_puroks', 'string', 'max:80'],
            'direct_puroks.*.priority' => ['nullable', 'string', Rule::in(['critical', 'high', 'watch', 'monitor'])],
        ]);

        $statusKeys = array_values($validated['allowed_statuses']);

        if (count(array_unique($statusKeys)) !== 4) {
            throw ValidationException::withMessages([
                'allowed_statuses' => ['Select four different household mobile status buttons.'],
            ]);
        }

        if ($validated['scope_type'] === 'selected_puroks' && empty($validated['direct_puroks'])) {
            throw ValidationException::withMessages([
                'direct_puroks' => ['Select at least one directly affected purok for this broadcast scope.'],
            ]);
        }

        if ($validated['scope_type'] === 'selected_puroks') {
            if (! Schema::hasTable('households')
                || ! Schema::hasColumn('households', 'address_id')
                || ! Schema::hasTable('addresses')
                || ! Schema::hasColumn('addresses', 'purok_sitio')) {
                throw ValidationException::withMessages([
                    'direct_puroks' => ['Household purok locations are unavailable. Select Barangay-wide or contact the system administrator.'],
                ]);
            }

            $selectedNames = collect($validated['direct_puroks'])
                ->map(fn (array $purok): string => trim($purok['name']))
                ->unique()
                ->values();
            $knownNameQuery = DB::table('households as h')
                ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
                ->whereIn('a.purok_sitio', $selectedNames)
                ->whereNotNull('a.purok_sitio')
                ->where('a.purok_sitio', '<>', '');

            if (Schema::hasColumn('households', 'deleted_at')) {
                $knownNameQuery->whereNull('h.deleted_at');
            }

            if (Schema::hasColumn('addresses', 'deleted_at')) {
                $knownNameQuery->whereNull('a.deleted_at');
            }

            $knownNames = $knownNameQuery->distinct()->pluck('a.purok_sitio');

            if ($knownNames->count() !== $selectedNames->count()) {
                throw ValidationException::withMessages([
                    'direct_puroks' => ['One or more selected puroks are not in the database. Refresh and select them again.'],
                ]);
            }

            $validated['direct_puroks'] = $selectedNames
                ->map(fn (string $name): array => ['name' => $name])
                ->all();
        }

        return $validated;
    }

    private function broadcastMetadata(array $validated): array
    {
        return [
            'statuses' => array_values($validated['allowed_statuses']),
            'puroks' => array_values($validated['direct_puroks'] ?? []),
            'area' => $validated['target_area'] ?? $this->scopeLabel($validated['scope_type']),
            'duration' => $validated['estimated_duration'] ?? null,
        ];
    }

    private function workspacePayload(?object $event, array $broadcasts): array
    {
        $formattedEvent = $event ? $this->formatEvent($event) : null;
        $isRequestedEventActive = $formattedEvent && $formattedEvent['status'] === 'active';
        $globalActive = $this->getActiveEvent();
        $activeEvent = $isRequestedEventActive
            ? $formattedEvent
            : ($globalActive ? $this->formatEvent($globalActive) : null);

        return [
            'current_event' => $formattedEvent,
            'active_event' => $activeEvent,
            'events' => $this->getEventHistory(),
            'broadcasts' => $broadcasts,
            'disaster_types' => $this->getDisasterTypes(),
            'severity_levels' => $this->getSeverityLevels(),
            'puroks' => $this->getPuroks(),
            'status_options' => $this->statusOptions(),
        ];
    }

    private function getActiveEvent(): ?object
    {
        return DisasterEvent::query()
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'disaster_events.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_events.severity_level_id')
            ->whereNull('disaster_events.deleted_at')
            ->whereNull('disaster_events.ended_at')
            ->orderByDesc('disaster_events.started_at')
            ->select([
                'disaster_events.event_id',
                'disaster_events.name',
                'disaster_events.type_id',
                'disaster_events.severity_level_id',
                'disaster_events.started_at',
                'disaster_events.ended_at',
                'dt.type_code',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function findEvent(string $eventId): ?object
    {
        return DisasterEvent::query()
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'disaster_events.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_events.severity_level_id')
            ->where('disaster_events.event_id', $eventId)
            ->whereNull('disaster_events.deleted_at')
            ->select([
                'disaster_events.event_id',
                'disaster_events.name',
                'disaster_events.type_id',
                'disaster_events.severity_level_id',
                'disaster_events.started_at',
                'disaster_events.ended_at',
                'dt.type_code',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function getEventHistory(): array
    {
        return DisasterEvent::query()
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'disaster_events.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_events.severity_level_id')
            ->whereNull('disaster_events.deleted_at')
            ->orderByDesc('disaster_events.started_at')
            ->limit(20)
            ->get([
                'disaster_events.event_id',
                'disaster_events.name',
                'disaster_events.type_id',
                'disaster_events.severity_level_id',
                'disaster_events.started_at',
                'disaster_events.ended_at',
                'dt.type_code',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->map(fn (object $event): array => $this->formatEvent($event))
            ->values()
            ->all();
    }

    private function getBroadcastsForEvent(string $eventId): array
    {
        return DisasterBroadcast::query()
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_broadcasts.severity_id')
            ->where('disaster_broadcasts.disaster_id', $eventId)
            ->orderByDesc('disaster_broadcasts.sent_at')
            ->limit(30)
            ->get($this->broadcastSelectColumns())
            ->map(fn (object $broadcast): array => $this->formatBroadcast($broadcast))
            ->values()
            ->all();
    }

    private function findBroadcast(int $broadcastId): array
    {
        $broadcast = DisasterBroadcast::query()
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_broadcasts.severity_id')
            ->where('disaster_broadcasts.broadcast_id', $broadcastId)
            ->first($this->broadcastSelectColumns());

        return $this->formatBroadcast($broadcast);
    }

    private function broadcastSelectColumns(): array
    {
        $columns = [
            'disaster_broadcasts.broadcast_id',
            'disaster_broadcasts.broadcast_title',
            'disaster_broadcasts.disaster_id',
            'disaster_broadcasts.sent_by_admin_id',
            'disaster_broadcasts.severity_id',
            'disaster_broadcasts.scope_type',
            'disaster_broadcasts.message',
            'disaster_broadcasts.allowed_statuses',
            'disaster_broadcasts.channel',
            'disaster_broadcasts.status',
            'disaster_broadcasts.sent_at',
            'sl.severity_key',
            'sl.severity_label',
        ];

        foreach ([
            'target_area_label',
            'direct_impact_puroks_json',
            'allowed_statuses_json',
            'recipient_count',
            'push_status',
            'sms_status',
        ] as $column) {
            if (Schema::hasColumn('disaster_broadcasts', $column)) {
                $columns[] = 'disaster_broadcasts.'.$column;
            }
        }

        return $columns;
    }

    private function formatEvent(object $event): array
    {
        $isActive = ! $event->ended_at;

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type_id' => $event->type_id,
            'type_code' => $event->type_code,
            'type_name' => $event->type_name ?? 'Disaster event',
            'severity_level_id' => $event->severity_level_id,
            'severity_key' => $event->severity_key ?? 'medium',
            'severity_label' => $event->severity_label ?? 'Unspecified',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
            'ended_at' => $this->formatDateTime($event->ended_at),
            'ended_time' => $this->formatTime($event->ended_at),
            'duration_label' => $this->durationLabel($event->started_at, $event->ended_at),
            'status_sent_count' => $this->latestStatusCount($event->event_id),
            'latest_weather' => $this->latestWeather($event->event_id),
            'status' => $isActive ? 'active' : 'closed',
        ];
    }

    private function durationLabel(?string $startedAt, ?string $endedAt): string
    {
        if (! $startedAt) {
            return 'Not recorded';
        }

        $start = Carbon::parse($startedAt);
        $end = $endedAt ? Carbon::parse($endedAt) : now();

        return $start->diffForHumans($end, true).' '.($endedAt ? 'total' : 'active');
    }

    private function latestStatusCount(string $eventId): int
    {
        if (! Schema::hasTable('disaster_broadcasts')) {
            return 0;
        }

        $broadcast = DB::table('disaster_broadcasts')
            ->where('disaster_id', $eventId)
            ->orderByDesc('sent_at')
            ->first(['allowed_statuses']);

        if (! $broadcast) {
            return 0;
        }

        $metadata = $this->decodeMetadata($broadcast->allowed_statuses);
        $statuses = $metadata['statuses'] ?? $this->legacyStatuses($broadcast->allowed_statuses);

        return count($statuses);
    }

    private function latestWeather(string $eventId): array
    {
        if (! Schema::hasTable('weather_logs')) {
            return [
                'condition' => 'No weather snapshot',
                'temperature' => null,
                'wind_speed' => null,
                'rainfall' => null,
            ];
        }

        $row = WeatherLog::query()
            ->where('disaster_id', $eventId)
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return [
                'condition' => 'No weather snapshot',
                'temperature' => null,
                'wind_speed' => null,
                'rainfall' => null,
            ];
        }

        return [
            'condition' => $row->condition_name ?? $row->advisory_title ?? 'Weather saved',
            'temperature' => $row->temperature ?? null,
            'wind_speed' => $row->wind_speed ?? null,
            'rainfall' => $row->rainfall_mm ?? null,
        ];
    }

    private function formatBroadcast(object $broadcast): array
    {
        $metadata = $this->decodeMetadata($broadcast->allowed_statuses);
        $directPuroks = $this->decodeJsonArray($broadcast->direct_impact_puroks_json ?? null);
        $statusKeys = $this->decodeJsonArray($broadcast->allowed_statuses_json ?? null);

        if (empty($directPuroks)) {
            $directPuroks = $metadata['puroks'] ?? [];
        }

        if (empty($statusKeys)) {
            $statusKeys = $metadata['statuses'] ?? $this->legacyStatuses($broadcast->allowed_statuses);
        }

        $recipientCount = $broadcast->recipient_count ?? null;

        return [
            'broadcast_id' => $broadcast->broadcast_id,
            'broadcast_title' => $broadcast->broadcast_title,
            'disaster_id' => $broadcast->disaster_id,
            'sent_by_admin_id' => $broadcast->sent_by_admin_id,
            'severity_id' => $broadcast->severity_id,
            'severity_key' => $broadcast->severity_key ?? 'medium',
            'severity_label' => $broadcast->severity_label ?? 'Medium',
            'scope_type' => $broadcast->scope_type,
            'scope_label' => $this->scopeLabel($broadcast->scope_type),
            'target_area' => $broadcast->target_area_label ?? $metadata['area'] ?? $this->scopeLabel($broadcast->scope_type),
            'direct_puroks' => $directPuroks,
            'allowed_statuses' => $statusKeys,
            'estimated_duration' => $metadata['duration'] ?? null,
            'recipient_count' => $recipientCount === null
                ? $this->recipientCount($broadcast->scope_type, $directPuroks)
                : (int) $recipientCount,
            'message' => $broadcast->message,
            'channel' => $broadcast->channel,
            'push_status' => $broadcast->push_status ?? null,
            'sms_status' => $broadcast->sms_status ?? null,
            'status' => $broadcast->status,
            'sent_at' => $this->formatDateTime($broadcast->sent_at),
            'sent_time' => $this->formatTime($broadcast->sent_at),
        ];
    }

    private function decodeMetadata(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function legacyStatuses(?string $text): array
    {
        if (! $text) {
            return [];
        }

        return collect(explode(',', $text))
            ->map(fn (string $status): string => trim($status))
            ->filter()
            ->values()
            ->all();
    }

    private function decodeJsonArray(mixed $text): array
    {
        if (is_array($text)) {
            return $text;
        }

        if (! $text) {
            return [];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function jsonText(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    private function compactMetadataText(array $metadata): string
    {
        return $this->jsonText([
            'statuses' => $metadata['statuses'],
            'duration' => $metadata['duration'],
        ]);
    }

    private function getDisasterTypes(): array
    {
        $query = DisasterType::query();

        if (Schema::hasColumn('disaster_types', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('disaster_types', 'is_active')) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('type_name')
            ->get(['type_id', 'type_code', 'type_name'])
            ->map(fn (object $type): array => [
                'type_id' => $type->type_id,
                'type_code' => $type->type_code,
                'type_name' => $type->type_name,
            ])
            ->values()
            ->all();
    }

    private function getSeverityLevels(): array
    {
        $query = SeverityLevel::query();

        return $query->orderByRaw("FIELD(severity_key, 'low', 'medium', 'high', 'critical')")
            ->get(['severity_id', 'severity_key', 'severity_label'])
            ->map(fn (object $severity): array => [
                'severity_id' => $severity->severity_id,
                'severity_key' => $severity->severity_key,
                'severity_label' => $severity->severity_label,
            ])
            ->values()
            ->all();
    }

    private function getPuroks(): array
    {
        if (! Schema::hasTable('households')
            || ! Schema::hasColumn('households', 'address_id')
            || ! Schema::hasTable('addresses')
            || ! Schema::hasColumn('addresses', 'purok_sitio')) {
            return [];
        }

        $query = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->whereNotNull('a.purok_sitio')
            ->where('a.purok_sitio', '<>', '');

        if (Schema::hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        if (Schema::hasColumn('addresses', 'deleted_at')) {
            $query->whereNull('a.deleted_at');
        }

        return $query
            ->select('a.purok_sitio')
            ->distinct()
            ->orderBy('a.purok_sitio')
            ->get()
            ->map(fn (object $purok): array => [
                'purok_id' => null,
                'name' => $purok->purok_sitio,
                'source' => 'household_addresses',
            ])
            ->values()
            ->all();
    }

    private function statusOptions(): array
    {
        return [
            ['key' => 'safe', 'label' => 'Safe'],
            ['key' => 'evacuated', 'label' => 'Evacuated'],
            ['key' => 'need_help', 'label' => 'Need help'],
            ['key' => 'unsafe', 'label' => 'Unsafe'],
            ['key' => 'injured', 'label' => 'Injured'],
            ['key' => 'missing_contact', 'label' => 'Missing contact'],
            ['key' => 'needs_supplies', 'label' => 'Needs supplies'],
            ['key' => 'follow_up', 'label' => 'Follow-up'],
        ];
    }

    private function firstPurokId(array $directPuroks): ?int
    {
        $firstName = $directPuroks[0]['name'] ?? null;

        if (! $firstName || ! Schema::hasTable('addresses') || ! Schema::hasColumn('addresses', 'purok_id')) {
            return null;
        }

        return DB::table('addresses')
            ->where('purok_sitio', $firstName)
            ->whereNotNull('purok_id')
            ->value('purok_id');
    }

    private function recipientCount(string $scopeType, array $directPuroks): int
    {
        $rescuers = 0;

        if (Schema::hasTable('responders')) {
            $responderQuery = DB::table('responders');

            if (Schema::hasColumn('responders', 'deleted_at')) {
                $responderQuery->whereNull('deleted_at');
            }

            $rescuers = $responderQuery->count();
        }

        $householdQuery = Household::query();

        if (Schema::hasColumn('households', 'deleted_at')) {
            $householdQuery->whereNull('households.deleted_at');
        }

        if ($scopeType === 'selected_puroks') {
            $purokNames = collect($directPuroks)->pluck('name')->filter()->values()->all();

            if ($purokNames === []
                || ! Schema::hasTable('addresses')
                || ! Schema::hasColumn('addresses', 'purok_sitio')) {
                return 0;
            }

            $householdQuery
                ->join('addresses as a', 'a.address_id', '=', 'households.address_id')
                ->whereIn('a.purok_sitio', $purokNames);

            if (Schema::hasColumn('addresses', 'deleted_at')) {
                $householdQuery->whereNull('a.deleted_at');
            }

            return $householdQuery
                ->distinct()
                ->count('households.household_id');
        }

        return $householdQuery->count() + $rescuers;
    }

    private function scopeLabel(string $scopeType): string
    {
        return match ($scopeType) {
            'selected_puroks' => 'Selected puroks',
            default => 'Barangay-wide',
        };
    }

    private function nextId(string $table, string $column): int
    {
        $currentMax = DB::table($table)
            ->lockForUpdate()
            ->max($column);

        return ((int) $currentMax) + 1;
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, string $key): bool => in_array($key, $columns, true))
            ->all();
    }

    private function formatDateTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('M d, Y h:i A');
    }

    private function formatTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('h:i A');
    }
}
