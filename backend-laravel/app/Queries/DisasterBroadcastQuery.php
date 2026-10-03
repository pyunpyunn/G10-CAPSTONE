<?php

namespace App\Queries;

use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\DisasterType;
use App\Models\Household;
use App\Models\SeverityLevel;
use App\Models\WeatherLog;
use App\Presenters\DisasterBroadcastPresenter;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class DisasterBroadcastQuery
{
    public function __construct(private DisasterBroadcastPresenter $presenter) {}

    public function getActiveEvent(): ?object
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

    public function getWorkspacePayload(?object $event, array $broadcasts): array
    {
        $formattedEvent = $event ? $this->presentEvent($event) : null;
        $globalActive = $this->getActiveEvent();
        $activeEvent = $formattedEvent && $formattedEvent['status'] === 'active'
            ? $formattedEvent
            : ($globalActive ? $this->presentEvent($globalActive) : null);

        return [
            'current_event' => $formattedEvent,
            'active_event' => $activeEvent,
            'events' => $this->getEventHistory(),
            'broadcasts' => $broadcasts,
            'disaster_types' => $this->getDisasterTypes(),
            'severity_levels' => $this->getSeverityLevels(),
            'puroks' => $this->getPuroks(),
            'status_options' => $this->presenter->statusOptions(),
        ];
    }

    public function findEvent(string $eventId): ?object
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

    public function getEventHistory(): array
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
            ->map(fn (object $event): array => $this->presentEvent($event))
            ->values()
            ->all();
    }

    public function getBroadcastsForEvent(string $eventId): array
    {
        return DisasterBroadcast::query()
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_broadcasts.severity_id')
            ->where('disaster_broadcasts.disaster_id', $eventId)
            ->orderByDesc('disaster_broadcasts.sent_at')
            ->limit(30)
            ->get($this->broadcastSelectColumns())
            ->map(fn (object $broadcast): array => $this->presentBroadcast($broadcast))
            ->values()
            ->all();
    }

    public function findBroadcast(int $broadcastId): array
    {
        $broadcast = DisasterBroadcast::query()
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_broadcasts.severity_id')
            ->where('disaster_broadcasts.broadcast_id', $broadcastId)
            ->first($this->broadcastSelectColumns());

        return $this->presentBroadcast($broadcast);
    }

    private function presentBroadcast(object $broadcast): array
    {
        if ($broadcast->recipient_count !== null) return $this->presenter->formatBroadcast($broadcast);
        $metadata = $this->presenter->decodeMetadata($broadcast->allowed_statuses);
        $puroks = $this->presenter->decodeJsonArray($broadcast->direct_impact_puroks_json ?? null);
        if ($puroks === []) $puroks = $metadata['puroks'] ?? [];
        return $this->presenter->formatBroadcast($broadcast, $this->recipientCount($broadcast->scope_type, $puroks));
    }

    public function broadcastSelectColumns(): array
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

    public function latestStatusCount(string $eventId): int
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

        $metadata = $this->presenter->decodeMetadata($broadcast->allowed_statuses);
        $statuses = $metadata['statuses'] ?? $this->presenter->legacyStatuses($broadcast->allowed_statuses);

        return count($statuses);
    }

    public function latestWeather(string $eventId): array
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

    public function getDisasterTypes(): array
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

    public function getSeverityLevels(): array
    {
        $query = SeverityLevel::query();

        return $query->orderByRaw("CASE severity_key WHEN 'low' THEN 0 WHEN 'medium' THEN 1 WHEN 'high' THEN 2 WHEN 'critical' THEN 3 ELSE 4 END")
            ->get(['severity_id', 'severity_key', 'severity_label'])
            ->map(fn (object $severity): array => [
                'severity_id' => $severity->severity_id,
                'severity_key' => $severity->severity_key,
                'severity_label' => $severity->severity_label,
            ])
            ->values()
            ->all();
    }

    public function getPuroks(): array
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

    public function firstPurokId(array $directPuroks): ?int
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

    public function recipientCount(string $scopeType, array $directPuroks): int
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

    public function nextId(string $table, string $column): int
    {
        return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table, $column);
    }

    public function filterColumns(string $table, array $data): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, string $key): bool => in_array($key, $columns, true))
            ->all();
    }

    private function presentEvent(object $event): array
    {
        return $this->presenter->formatEvent(
            $event,
            $this->latestStatusCount((string) $event->event_id),
            $this->latestWeather((string) $event->event_id),
        );
    }
}


