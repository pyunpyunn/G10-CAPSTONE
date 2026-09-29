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
            'data' => $this->workspacePayload($event, $this->getBroadcastsForEvent($eventId)),
        ]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type_id' => ['required', 'integer', 'exists:disaster_types,type_id'],
            'severity_level_id' => ['required', 'integer', 'exists:severity_levels,severity_id'],
            'started_at' => ['required', 'date'],
        ]);

        $activeEvent = $this->getActiveEvent();
        if ($activeEvent) {
            return response()->json([
                'message' => 'An active disaster event is already ongoing. Close it first before declaring a new one.',
            ], 422);
        }

        $eventId = (string) Str::uuid();

        DisasterEvent::create([
            'event_id' => $eventId,
            'name' => $validated['name'],
            'type_id' => $validated['type_id'],
            'severity_level_id' => $validated['severity_level_id'],
            'started_at' => $validated['started_at'],
        ]);

        return response()->json([
            'message' => 'Disaster event has been declared.',
            'data' => $this->workspacePayload($this->findEvent($eventId), []),
        ], 201);
    }

    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        $event = DisasterEvent::where('event_id', $eventId)->first();

        if (! $event) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'type_id' => ['sometimes', 'required', 'integer', 'exists:disaster_types,type_id'],
            'severity_level_id' => ['sometimes', 'required', 'integer', 'exists:severity_levels,severity_id'],
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Disaster event details have been updated.',
            'data' => $this->workspacePayload($this->findEvent($eventId), $this->getBroadcastsForEvent($eventId)),
        ]);
    }

    public function storeBroadcast(Request $request, string $eventId): JsonResponse
    {
        $event = DisasterEvent::where('event_id', $eventId)->first();

        if (! $event) {
            return response()->json([
                'message' => 'Disaster event record was not found.',
            ], 404);
        }

        $validated = $this->validateBroadcast($request);

        $broadcastId = (string) Str::uuid();
        $adminId = auth()->id() ?? 'USR-HQ-SUPERADMIN-1789925937';

        $metadata = $this->broadcastMetadata($validated);

        $broadcastData = [
            'broadcast_id' => $broadcastId,
            'broadcast_title' => $validated['broadcast_title'],
            'disaster_id' => $eventId,
            'sent_by_admin_id' => $adminId,
            'severity_id' => $validated['severity_id'] ?? $event->severity_level_id,
            'scope_type' => $validated['scope_type'],
            'target_area_label' => $validated['target_area'] ?? null,
            'allowed_statuses' => $this->jsonText($metadata['statuses']),
            'allowed_statuses_json' => $this->jsonText($metadata['statuses']),
            'direct_impact_puroks_json' => $this->jsonText($metadata['puroks']),
            'recipient_count' => $this->recipientCount($validated['scope_type'], $metadata['puroks']),
            'message' => $validated['message'],
            'channel' => $validated['channel'] ?? 'push_and_sms',
            'push_status' => 'sent',
            'sms_status' => 'queued',
            'status' => 'active',
            'sent_at' => now(),
        ];

        if (Schema::hasColumn('disaster_broadcasts', 'attached_evacuation_center_id')) {
            $broadcastData['attached_evacuation_center_id'] = $metadata['attached_evacuation_center_id'];
        }

        if (Schema::hasColumn('disaster_broadcasts', 'attached_affected_area_ids_json')) {
            $broadcastData['attached_affected_area_ids_json'] = $this->jsonText($metadata['attached_affected_area_ids']);
        }

        if (Schema::hasColumn('disaster_broadcasts', 'evacuation_route_json')) {
            $broadcastData['evacuation_route_json'] = $this->jsonText($this->calculateEvacuationRoute($metadata['attached_evacuation_center_id'], $metadata['attached_affected_area_ids'], $metadata['puroks']) ?? []);
        }

        $broadcast = DisasterBroadcast::create($broadcastData);

        return response()->json([
            'message' => 'Disaster broadcast has been sent.',
            'data' => $this->workspacePayload($event, $this->getBroadcastsForEvent($eventId)),
        ], 201);
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
            'attach_route' => ['nullable', 'boolean'],
            'attached_evacuation_center_id' => ['nullable', 'string', 'max:255'],
            'attached_affected_area_ids' => ['nullable', 'array'],
            'attached_affected_area_ids.*' => ['nullable', 'integer'],
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

        if (! empty($validated['attached_evacuation_center_id']) && Schema::hasTable('evacuation_centers')) {
            $center = DB::table('evacuation_centers')
                ->where('evacuation_center_id', $validated['attached_evacuation_center_id'])
                ->first(['status']);

            if (! $center) {
                throw ValidationException::withMessages([
                    'attached_evacuation_center_id' => ['The selected evacuation center record was not found.'],
                ]);
            }

            $centerStatus = strtolower(trim((string) ($center->status ?? '')));
            if (! in_array($centerStatus, ['active', 'open', 'available'], true)) {
                throw ValidationException::withMessages([
                    'attached_evacuation_center_id' => ['Only active evacuation centers can be attached to a disaster broadcast route.'],
                ]);
            }
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
            'route' => (bool) ($validated['attach_route'] ?? false),
            'attached_evacuation_center_id' => $validated['attached_evacuation_center_id'] ?? null,
            'attached_affected_area_ids' => array_filter(array_map('intval', $validated['attached_affected_area_ids'] ?? [])),
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
            'affected_areas' => $this->getAffectedAreas(),
            'evacuation_centers' => $this->getActiveEvacuationCenters(),
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
            ->get()
            ->map(fn (object $event): array => $this->formatEvent($event))
            ->values()
            ->all();
    }

    private function getBroadcastsForEvent(string $eventId): array
    {
        return DisasterBroadcast::query()
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_broadcasts.severity_id')
            ->where('disaster_broadcasts.disaster_id', $eventId)
            ->whereNull('disaster_broadcasts.deleted_at')
            ->orderByDesc('disaster_broadcasts.sent_at')
            ->select([
                'disaster_broadcasts.*',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->get()
            ->map(fn (object $broadcast): array => $this->formatBroadcast($broadcast))
            ->values()
            ->all();
    }

    private function formatEvent(object $event): array
    {
        $status = $event->ended_at ? 'closed' : 'active';

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type_id' => $event->type_id,
            'type_code' => $event->type_code,
            'type_name' => $event->type_name ?? 'General Hazard',
            'severity_level_id' => $event->severity_level_id,
            'severity_key' => $event->severity_key ?? 'medium',
            'severity_label' => $event->severity_label ?? 'Medium',
            'status' => $status,
            'started_at' => $this->formatDateTime($event->started_at),
            'started_date' => $event->started_at ? Carbon::parse($event->started_at)->format('Y-m-d') : null,
            'started_time' => $event->started_at ? Carbon::parse($event->started_at)->format('H:i') : null,
            'ended_at' => $this->formatDateTime($event->ended_at),
            'weather' => $this->latestWeather($event->event_id),
        ];
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
        $centerId = $broadcast->attached_evacuation_center_id ?? $metadata['attached_evacuation_center_id'] ?? null;
        $affectedAreaIds = $this->decodeJsonArray($broadcast->attached_affected_area_ids_json ?? null);
        if (empty($affectedAreaIds)) {
            $affectedAreaIds = $metadata['attached_affected_area_ids'] ?? [];
        }
        $route = $this->decodeMetadata($broadcast->evacuation_route_json ?? null);
        if (empty($route) && ! empty($metadata['route']) && $centerId) {
            $route = $this->calculateEvacuationRoute($centerId, $affectedAreaIds, $directPuroks);
        }

        $attachedCenter = $centerId ? $this->findEvacuationCenter($centerId) : null;

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
            'attach_route' => (bool) ($metadata['route'] ?? false || ! empty($route)),
            'attached_evacuation_center_id' => $centerId,
            'attached_affected_area_ids' => $affectedAreaIds,
            'attached_evacuation_center' => $attachedCenter,
            'evacuation_route' => $route,
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

    private function getAffectedAreas(): array
    {
        if (! Schema::hasTable('affected_areas')) {
            return [];
        }

        $query = DB::table('affected_areas as aa')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'aa.severity_level_id');

        if (Schema::hasColumn('affected_areas', 'deleted_at')) {
            $query->whereNull('aa.deleted_at');
        }

        return $query->select([
            'aa.*',
            'sl.severity_key',
            'sl.severity_label',
        ])
        ->get()
        ->map(function (object $row): array {
            $purokName = $row->purok_name ?? null;
            $recipients = 0;

            if (Schema::hasTable('households') && Schema::hasTable('addresses')) {
                $recipients = DB::table('households as h')
                    ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
                    ->where(function ($sub) use ($row, $purokName) {
                        if ($row->purok_id) {
                            $sub->where('a.purok_id', $row->purok_id);
                        }
                        if ($purokName) {
                            $sub->orWhere('a.purok_sitio', $purokName);
                        }
                    })
                    ->count();
            }

            return [
                'affected_area_id' => (int) $row->affected_area_id,
                'area_name' => $row->area_name ?: $purokName,
                'purok_name' => $purokName,
                'purok_id' => $row->purok_id ? (int) $row->purok_id : null,
                'hazard_type' => $row->hazard_type ?: 'General Hazard',
                'description' => $row->description,
                'severity_key' => $row->severity_key ?: 'medium',
                'severity_label' => $row->severity_label ?: 'Medium',
                'latitude' => $row->latitude ? (float) $row->latitude : null,
                'longitude' => $row->longitude ? (float) $row->longitude : null,
                'boundary_geojson' => $row->boundary_geojson ? json_decode((string) $row->boundary_geojson, true) : null,
                'status' => $row->status ?: 'active',
                'recipient_count' => $recipients,
            ];
        })
        ->values()
        ->all();
    }

    private function getActiveEvacuationCenters(): array
    {
        if (! Schema::hasTable('evacuation_centers')) {
            return [];
        }

        $query = DB::table('evacuation_centers')
            ->whereIn(DB::raw('LOWER(status)'), ['active', 'open', 'available']);

        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy('name')
            ->get([
                'evacuation_center_id',
                'name',
                'center_type',
                'latitude',
                'longitude',
                'capacity',
                'current_occupancy',
                'contact_person',
                'contact_number',
                'osm_address',
                'status',
            ])
            ->map(function (object $center): array {
                $capacity = max(0, (int) ($center->capacity ?? 0));
                $occupancy = max(0, (int) ($center->current_occupancy ?? 0));

                return [
                    'evacuation_center_id' => (string) $center->evacuation_center_id,
                    'name' => $center->name ?: 'Evacuation Center',
                    'center_type' => $center->center_type ?: 'General Evacuation Center',
                    'latitude' => $center->latitude ? (float) $center->latitude : 10.2922,
                    'longitude' => $center->longitude ? (float) $center->longitude : 123.8763,
                    'capacity' => $capacity,
                    'current_occupancy' => $occupancy,
                    'available_capacity' => max(0, $capacity - $occupancy),
                    'contact_person' => $center->contact_person,
                    'contact_number' => $center->contact_number,
                    'osm_address' => $center->osm_address,
                    'status' => $center->status ?: 'active',
                    'is_active' => true,
                ];
            })
            ->values()
            ->all();
    }

    private function findEvacuationCenter(string $centerId): ?array
    {
        if (! Schema::hasTable('evacuation_centers')) {
            return null;
        }

        $center = DB::table('evacuation_centers')
            ->where('evacuation_center_id', $centerId)
            ->first([
                'evacuation_center_id',
                'name',
                'center_type',
                'latitude',
                'longitude',
                'capacity',
                'current_occupancy',
                'contact_person',
                'contact_number',
                'osm_address',
                'status',
            ]);

        if (! $center) {
            return null;
        }

        $capacity = max(0, (int) ($center->capacity ?? 0));
        $occupancy = max(0, (int) ($center->current_occupancy ?? 0));
        $statusKey = strtolower(trim((string) ($center->status ?? '')));

        return [
            'evacuation_center_id' => (string) $center->evacuation_center_id,
            'name' => $center->name ?: 'Evacuation Center',
            'center_type' => $center->center_type ?: 'Evacuation Center',
            'latitude' => $center->latitude ? (float) $center->latitude : 10.2922,
            'longitude' => $center->longitude ? (float) $center->longitude : 123.8763,
            'capacity' => $capacity,
            'current_occupancy' => $occupancy,
            'available_capacity' => max(0, $capacity - $occupancy),
            'contact_person' => $center->contact_person,
            'contact_number' => $center->contact_number,
            'osm_address' => $center->osm_address,
            'status' => $center->status ?: 'active',
            'is_active' => in_array($statusKey, ['active', 'open', 'available'], true),
        ];
    }

    private function calculateEvacuationRoute(?string $centerId, array $affectedAreaIds, ?array $directPuroks): ?array
    {
        if (! $centerId) {
            return null;
        }

        $center = $this->findEvacuationCenter($centerId);
        if (! $center || ! $center['is_active']) {
            return null;
        }

        $destLat = $center['latitude'];
        $destLng = $center['longitude'];

        $origins = [];
        if (! empty($affectedAreaIds) && Schema::hasTable('affected_areas')) {
            $areas = DB::table('affected_areas')
                ->whereIn('affected_area_id', $affectedAreaIds)
                ->get(['affected_area_id', 'area_name', 'latitude', 'longitude']);

            foreach ($areas as $a) {
                $origins[] = [
                    'id' => (string) $a->affected_area_id,
                    'name' => $a->area_name ?: "Area {$a->affected_area_id}",
                    'lat' => $a->latitude ? (float) $a->latitude : 10.2922,
                    'lng' => $a->longitude ? (float) $a->longitude : 123.8763,
                ];
            }
        }

        if (empty($origins) && ! empty($directPuroks)) {
            foreach ($directPuroks as $idx => $p) {
                $origins[] = [
                    'id' => "purok-{$idx}",
                    'name' => $p['name'] ?? 'Affected Purok',
                    'lat' => 10.2922 + ($idx * 0.0008),
                    'lng' => 123.8763 + ($idx * 0.0008),
                ];
            }
        }

        if (empty($origins)) {
            $origins[] = [
                'id' => 'default-origin',
                'name' => 'Affected Area',
                'lat' => 10.2922,
                'lng' => 123.8763,
            ];
        }

        $primaryOrigin = $origins[0];
        $distanceKm = round($this->haversineDistance($primaryOrigin['lat'], $primaryOrigin['lng'], $destLat, $destLng), 2);
        $estMinutes = max(5, (int) round($distanceKm * 12 + 5));

        $waypoints = [
            [$primaryOrigin['lat'], $primaryOrigin['lng']],
            [($primaryOrigin['lat'] + $destLat) / 2, ($primaryOrigin['lng'] + $destLng) / 2],
            [$destLat, $destLng],
        ];

        return [
            'route_title' => "Evacuation Route to {$center['name']}",
            'destination' => $center,
            'origins' => $origins,
            'primary_origin' => $primaryOrigin,
            'distance_km' => $distanceKm,
            'estimated_minutes' => $estMinutes,
            'waypoints' => $waypoints,
            'is_active_destination' => true,
        ];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
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
