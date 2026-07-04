<?php

namespace App\Services;

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
    public function __construct(private OneSignalNotificationService $oneSignal) {}

    public function index(): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();
        $eventId = $activeEvent?->event_id;

        return response()->json([
            'data' => [
                'active_event' => $activeEvent ? $this->formatEvent($activeEvent) : null,
                'events' => $this->getEventHistory(),
                'broadcasts' => $eventId ? $this->getBroadcastsForEvent($eventId) : [],
                'disaster_types' => $this->getDisasterTypes(),
                'severity_levels' => $this->getSeverityLevels(),
                'puroks' => $this->getPuroks(),
                'status_options' => $this->statusOptions(),
            ],
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

            DB::table('disaster_events')->insert([
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
            'data' => [
                'active_event' => $this->formatEvent($event),
                'events' => $this->getEventHistory(),
            ],
        ], 201);
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

            DB::table('disaster_broadcasts')->insert($data);

            return $broadcastId;
        });

        $pushResult = $this->sendBroadcastPush($broadcastId, $validated, $metadata, $event);
        $this->updateBroadcastPushStatus($broadcastId, $pushResult);

        return response()->json([
            'message' => $this->broadcastSaveMessage($pushResult),
            'data' => [
                'broadcast' => $this->findBroadcast($broadcastId),
                'broadcasts' => $this->getBroadcastsForEvent($event->event_id),
                'push_delivery' => $pushResult,
            ],
        ], 201);
    }

    private function sendBroadcastPush(int $broadcastId, array $validated, array $metadata, object $event): array
    {
        $scope = $validated['scope_type'];
        $purokNames = in_array($scope, ['selected_puroks', 'local_direct_impact'], true)
            ? collect($metadata['puroks'])->pluck('name')->filter()->values()->all()
            : [];

        $roles = match ($scope) {
            'rescuers_only' => ['rescuer'],
            'selected_puroks', 'local_direct_impact' => ['household'],
            default => ['household', 'rescuer'],
        };

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
                ],
            ]
        );
    }

    private function updateBroadcastPushStatus(int $broadcastId, array $pushResult): void
    {
        if (! Schema::hasTable('disaster_broadcasts') || ! Schema::hasColumn('disaster_broadcasts', 'push_status')) {
            return;
        }

        DB::table('disaster_broadcasts')
            ->where('broadcast_id', $broadcastId)
            ->update($this->filterColumns('disaster_broadcasts', [
                'push_status' => 'onesignal_'.$pushResult['status'],
                'channel' => 'onesignal',
                'updated_at' => now(),
            ]));
    }

    private function broadcastSaveMessage(array $pushResult): string
    {
        return match ($pushResult['status']) {
            'sent' => 'Broadcast saved and sent through OneSignal to '.$pushResult['sent_count'].' device(s).',
            'partial' => 'Broadcast saved. OneSignal sent to '.$pushResult['sent_count'].' of '.$pushResult['recipient_count'].' device(s).',
            'no_recipients' => 'Broadcast saved, but no OneSignal-ready mobile devices were found.',
            'not_configured' => 'Broadcast saved, but OneSignal credentials are not configured.',
            default => 'Broadcast saved, but OneSignal delivery failed. Check backend logs and OneSignal credentials.',
        };
    }

    private function validateBroadcast(Request $request): array
    {
        $validated = $request->validate([
            'broadcast_title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'severity_id' => ['nullable', 'integer', 'exists:severity_levels,severity_id'],
            'scope_type' => ['required', 'string', Rule::in(['barangay_wide', 'selected_puroks', 'local_direct_impact', 'rescuers_only'])],
            'target_area' => ['nullable', 'string', 'max:150'],
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'attach_route' => ['nullable', 'boolean'],
            'channel' => ['nullable', 'string', 'max:50'],
            'allowed_statuses' => ['required', 'array', 'size:4'],
            'allowed_statuses.*' => ['required', 'string', 'max:40'],
            'direct_puroks' => ['nullable', 'array', 'max:5'],
            'direct_puroks.*.name' => ['required_with:direct_puroks', 'string', 'max:80'],
            'direct_puroks.*.priority' => ['required_with:direct_puroks', 'string', Rule::in(['critical', 'high', 'watch', 'monitor'])],
        ]);

        $statusKeys = array_values($validated['allowed_statuses']);

        if (count(array_unique($statusKeys)) !== 4) {
            throw ValidationException::withMessages([
                'allowed_statuses' => ['Select four different household mobile status buttons.'],
            ]);
        }

        if (in_array($validated['scope_type'], ['selected_puroks', 'local_direct_impact'], true)
            && empty($validated['direct_puroks'])) {
            throw ValidationException::withMessages([
                'direct_puroks' => ['Select at least one directly affected purok for this broadcast scope.'],
            ]);
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
            'route' => (bool) ($validated['attach_route'] ?? false),
        ];
    }

    private function getActiveEvent(): ?object
    {
        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->whereNull('de.ended_at')
            ->orderByDesc('de.started_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.type_id',
                'de.severity_level_id',
                'de.started_at',
                'de.ended_at',
                'dt.type_code',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function findEvent(string $eventId): ?object
    {
        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->where('de.event_id', $eventId)
            ->whereNull('de.deleted_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.type_id',
                'de.severity_level_id',
                'de.started_at',
                'de.ended_at',
                'dt.type_code',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function getEventHistory(): array
    {
        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->orderByDesc('de.started_at')
            ->limit(20)
            ->get([
                'de.event_id',
                'de.name',
                'de.type_id',
                'de.severity_level_id',
                'de.started_at',
                'de.ended_at',
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
        return DB::table('disaster_broadcasts as db')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'db.severity_id')
            ->where('db.disaster_id', $eventId)
            ->orderByDesc('db.sent_at')
            ->limit(30)
            ->get($this->broadcastSelectColumns())
            ->map(fn (object $broadcast): array => $this->formatBroadcast($broadcast))
            ->values()
            ->all();
    }

    private function findBroadcast(int $broadcastId): array
    {
        $broadcast = DB::table('disaster_broadcasts as db')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'db.severity_id')
            ->where('db.broadcast_id', $broadcastId)
            ->first($this->broadcastSelectColumns());

        return $this->formatBroadcast($broadcast);
    }

    private function broadcastSelectColumns(): array
    {
        $columns = [
            'db.broadcast_id',
            'db.broadcast_title',
            'db.disaster_id',
            'db.sent_by_admin_id',
            'db.severity_id',
            'db.scope_type',
            'db.message',
            'db.allowed_statuses',
            'db.channel',
            'db.status',
            'db.sent_at',
            'sl.severity_key',
            'sl.severity_label',
        ];

        foreach ([
            'target_area_label',
            'direct_impact_puroks_json',
            'allowed_statuses_json',
            'recipient_count',
            'push_status',
        ] as $column) {
            if (Schema::hasColumn('disaster_broadcasts', $column)) {
                $columns[] = 'db.'.$column;
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

        $columns = Schema::getColumnListing('weather_logs');
        $eventColumn = collect(['disaster_id', 'event_id'])
            ->first(fn (string $column): bool => in_array($column, $columns, true));

        if (! $eventColumn) {
            return [
                'condition' => 'Weather not linked',
                'temperature' => null,
                'wind_speed' => null,
                'rainfall' => null,
            ];
        }

        $orderColumn = collect(['logged_at', 'created_at', 'weather_log_id', 'id'])
            ->first(fn (string $column): bool => in_array($column, $columns, true));

        $query = DB::table('weather_logs')
            ->where($eventColumn, $eventId)
            ->when($orderColumn, fn ($weatherQuery) => $weatherQuery->orderByDesc($orderColumn));

        $row = $query->first();

        if (! $row) {
            return [
                'condition' => 'No weather snapshot',
                'temperature' => null,
                'wind_speed' => null,
                'rainfall' => null,
            ];
        }

        return [
            'condition' => $row->condition ?? $row->weather_condition ?? $row->summary ?? 'Weather saved',
            'temperature' => $row->temperature_c ?? $row->temperature ?? null,
            'wind_speed' => $row->wind_speed_kmh ?? $row->wind_speed ?? null,
            'rainfall' => $row->rainfall_mm ?? $row->rainfall ?? null,
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
            'attach_route' => (bool) ($metadata['route'] ?? false),
            'recipient_count' => $recipientCount === null
                ? $this->recipientCount($broadcast->scope_type, $directPuroks)
                : (int) $recipientCount,
            'message' => $broadcast->message,
            'channel' => $broadcast->channel,
            'push_status' => $broadcast->push_status ?? null,
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

    private function decodeJsonArray(?string $text): array
    {
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
            'route' => $metadata['route'],
        ]);
    }

    private function getDisasterTypes(): array
    {
        $query = DB::table('disaster_types');

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
        $query = DB::table('severity_levels');

        if (Schema::hasColumn('severity_levels', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

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
        $puroks = collect();

        if (Schema::hasTable('puroks') && Schema::hasColumn('puroks', 'purok_name')) {
            $query = DB::table('puroks')
                ->whereNotNull('purok_name');

            if (Schema::hasColumn('puroks', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $puroks = $query->orderBy('purok_name')
                ->get(['purok_id', 'purok_name'])
                ->map(fn (object $purok): array => [
                    'purok_id' => $purok->purok_id,
                    'name' => $purok->purok_name,
                    'source' => 'puroks',
                ]);
        }

        if ($puroks->isEmpty() && Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'purok_sitio')) {
            $puroks = DB::table('addresses')
                ->whereNotNull('purok_sitio')
                ->where('purok_sitio', '<>', '')
                ->select('purok_sitio')
                ->distinct()
                ->orderBy('purok_sitio')
                ->get()
                ->map(fn (object $address): array => [
                    'purok_id' => null,
                    'name' => $address->purok_sitio,
                    'source' => 'addresses',
                ]);
        }

        if ($puroks->isEmpty()) {
            $puroks = collect(range(1, 30))->map(fn (int $number): array => [
                'purok_id' => null,
                'name' => 'Purok '.$number,
                'source' => 'prototype_reference',
            ]);
        }

        return $puroks->values()->all();
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

        if ($scopeType === 'rescuers_only') {
            return $rescuers;
        }

        $householdQuery = DB::table('households');

        if (Schema::hasColumn('households', 'deleted_at')) {
            $householdQuery->whereNull('deleted_at');
        }

        if (in_array($scopeType, ['selected_puroks', 'local_direct_impact'], true) && Schema::hasTable('addresses')) {
            $purokNames = collect($directPuroks)->pluck('name')->filter()->values()->all();

            if (! empty($purokNames)) {
                $householdQuery
                    ->leftJoin('addresses as a', 'a.address_id', '=', 'households.address_id')
                    ->whereIn('a.purok_sitio', $purokNames);
            }
        }

        return $householdQuery->count() + $rescuers;
    }

    private function scopeLabel(string $scopeType): string
    {
        return match ($scopeType) {
            'selected_puroks' => 'Selected puroks',
            'local_direct_impact' => 'Direct-impact puroks',
            'rescuers_only' => 'Responders only',
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
