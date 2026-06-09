<?php

namespace App\Services;

use App\Services\BarangayProfileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    private BarangayProfileService $barangayProfile;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
    }

    public function index(): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();
        $eventId = $activeEvent?->event_id;
        $householdSummary = $this->getHouseholdSummary($eventId);

        return response()->json([
            'data' => [
                'barangay_profile' => $this->barangayProfile->current(),
                'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                'households' => $householdSummary,
                'dispatch' => $this->getDispatchSummary($eventId),
                'weather' => $this->getWeatherSnapshot($eventId),
                'requests' => $this->getRequestSummary(),
                'map' => $this->getMapSummary($eventId, $householdSummary),
                'recent_activity' => $this->getRecentActivity($eventId),
            ],
        ]);
    }

    public function closeActiveEvent(Request $request): JsonResponse
    {
        $closedEvent = DB::transaction(function () use ($request): ?array {
            $event = DB::table('disaster_events')
                ->whereNull('deleted_at')
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if (! $event) {
                return null;
            }

            $endedAt = now();
            $closureNote = $this->buildClosureNote($event);
            $user = $request->user();

            DB::table('disaster_events')
                ->where('event_id', $event->event_id)
                ->update([
                    'ended_at' => $endedAt,
                    'updated_at' => $endedAt,
                ]);

            $this->archiveSituationReports($event->event_id, $endedAt);
            $this->writeIncidentArchive($event, $user?->user_id, $closureNote, $endedAt);
            $this->writeAuditLog($event, $user, $endedAt, $closureNote, $request);

            return [
                'event_id' => $event->event_id,
                'name' => $event->name,
                'ended_at' => $this->formatDateTime($endedAt->toDateTimeString()),
            ];
        });

        if (! $closedEvent) {
            return response()->json([
                'message' => 'There is no active disaster event to close.',
            ], 404);
        }

        $activeEvent = $this->getActiveEvent();
        $eventId = $activeEvent?->event_id;
        $householdSummary = $this->getHouseholdSummary($eventId);

        return response()->json([
            'message' => 'Active event closed and saved to the Disaster Event Log.',
            'data' => [
                'closed_event' => $closedEvent,
                'dashboard' => [
                    'barangay_profile' => $this->barangayProfile->current(),
                    'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                    'households' => $householdSummary,
                    'dispatch' => $this->getDispatchSummary($eventId),
                    'weather' => $this->getWeatherSnapshot($eventId),
                    'requests' => $this->getRequestSummary(),
                    'map' => $this->getMapSummary($eventId, $householdSummary),
                    'recent_activity' => $this->getRecentActivity($eventId),
                ],
            ],
        ]);
    }

    private function getActiveEvent(): ?object
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->whereNull('de.ended_at')
            ->orderByDesc('de.started_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function formatActiveEvent(object $event): array
    {
        $latestBroadcast = DB::table('disaster_broadcasts')
            ->where('disaster_id', $event->event_id)
            ->orderByDesc('sent_at')
            ->first(['broadcast_title', 'scope_type', 'sent_at']);

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Unspecified',
            'severity_key' => $event->severity_key ?? 'medium',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
            'latest_broadcast_title' => $latestBroadcast?->broadcast_title,
            'latest_broadcast_scope' => $latestBroadcast?->scope_type,
            'latest_broadcast_time' => $this->formatTime($latestBroadcast?->sent_at),
        ];
    }

    private function getHouseholdSummary(?string $eventId): array
    {
        $total = 0;

        if (Schema::hasTable('households')) {
            $total = DB::table('households')
                ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->count();
        }

        if (! $eventId || ! Schema::hasTable('household_disasters') || ! Schema::hasTable('household_statuses')) {
            return [
                'total' => $total,
                'reported' => 0,
                'reporting_percent' => 0,
                'unchecked' => 0,
                'safe_total' => 0,
                'safe_only' => 0,
                'evacuated' => 0,
                'unsafe' => 0,
                'bars' => $this->householdBars(0, 0, 0, 0, 0),
            ];
        }

        $counts = DB::table('household_disasters as hd')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('hd.disaster_id', $eventId)
            ->select('hs.status_key', DB::raw('COUNT(*) as total'))
            ->groupBy('hs.status_key')
            ->pluck('total', 'status_key');

        $safeOnly = $this->sumStatusKeys($counts, ['active', 'returned', 'safe']);
        $evacuated = $this->sumStatusKeys($counts, ['evacuated', 'relocated']);
        $unsafe = $this->sumStatusKeys($counts, ['not_evacuated', 'displaced', 'unsafe', 'missing']);
        $safeTotal = $safeOnly + $evacuated;

        $reported = DB::table('household_disasters')
            ->where('disaster_id', $eventId)
            ->whereNotNull('current_status_id')
            ->distinct()
            ->count('household_id');

        $unchecked = max($total - $reported, 0);
        $reportingPercent = $total > 0 ? round(($reported / $total) * 100) : 0;

        return [
            'total' => $total,
            'reported' => $reported,
            'reporting_percent' => $reportingPercent,
            'unchecked' => $unchecked,
            'safe_total' => $safeTotal,
            'safe_only' => $safeOnly,
            'evacuated' => $evacuated,
            'unsafe' => $unsafe,
            'bars' => $this->householdBars($safeTotal, $safeOnly, $evacuated, $unsafe, $unchecked),
        ];
    }

    private function getDispatchSummary(?string $eventId): array
    {
        if (! $eventId || ! Schema::hasTable('responder_assignments')) {
            return [
                'counts' => $this->dispatchBars(0, 0, 0),
                'teams' => [],
            ];
        }

        $statusCounts = DB::table('responder_assignments')
            ->where('disaster_id', $eventId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $dispatched = $this->sumStatusKeys($statusCounts, ['dispatched', 'assigned', 'accepted', 'en_route']);
        $onScene = $this->sumStatusKeys($statusCounts, ['on_scene', 'on-scene', 'arrived']);
        $standby = Schema::hasTable('rescue_teams')
            ? DB::table('rescue_teams')->where('duty_status', 'standby')->count()
            : 0;

        $teams = DB::table('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->where('ra.disaster_id', $eventId)
            ->orderByDesc('ra.assigned_at')
            ->limit(8)
            ->get([
                'rt.team_name',
                'rt.team_type',
                'ra.status',
                'ra.assigned_area',
                'ra.assigned_at',
                'ra.priority_level',
            ])
            ->map(fn (object $team): array => [
                'team_name' => $team->team_name ?? 'Unassigned team',
                'team_type' => $team->team_type ?? 'Response team',
                'status' => $this->label($team->status ?? 'assigned'),
                'status_key' => $this->statusKey($team->status ?? 'assigned'),
                'assigned_area' => $team->assigned_area ?? 'No area set',
                'assigned_time' => $this->formatTime($team->assigned_at),
                'priority_level' => $team->priority_level,
            ])
            ->values();

        return [
            'counts' => $this->dispatchBars($dispatched, $onScene, $standby),
            'teams' => $teams,
        ];
    }

    private function getWeatherSnapshot(?string $eventId): ?array
    {
        if (! $eventId || ! Schema::hasTable('weather_logs')) {
            return null;
        }

        $weather = DB::table('weather_logs')
            ->where('disaster_id', $eventId)
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $weather) {
            return null;
        }

        return [
            'source_name' => $weather->source_name,
            'condition_name' => $weather->condition_name,
            'temperature' => $weather->temperature,
            'rainfall_mm' => $weather->rainfall_mm,
            'wind_speed' => $weather->wind_speed,
            'advisory_title' => $weather->advisory_title,
            'advisory_text' => $weather->advisory_text,
            'observed_at' => $this->formatDateTime($weather->observed_at),
        ];
    }

    private function getRequestSummary(): array
    {
        if (! Schema::hasTable('resource_requests')) {
            return [
                'needs_validation' => 0,
                'validated' => 0,
                'released' => 0,
                'latest' => [],
            ];
        }

        $counts = DB::table('resource_requests')
            ->select('validation_status', DB::raw('COUNT(*) as total'))
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        $latest = DB::table('resource_requests')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get([
                'request_id',
                'requested_by',
                'resource_type',
                'item_name',
                'quantity',
                'unit',
                'validation_status',
            ])
            ->map(fn (object $request): array => [
                'request_id' => $request->request_id,
                'requested_by' => $request->requested_by ?? 'Unspecified',
                'item_name' => $request->item_name ?? $request->resource_type ?? 'Resource request',
                'quantity' => trim(($request->quantity ?? '0').' '.($request->unit ?? '')),
                'validation_status' => $this->label($request->validation_status ?? 'needs_validation'),
                'status_key' => $this->statusKey($request->validation_status ?? 'needs_validation'),
            ])
            ->values();

        return [
            'needs_validation' => (int) ($counts['needs_validation'] ?? 0),
            'validated' => (int) ($counts['validated'] ?? 0),
            'released' => DB::table('resource_requests')->whereNotNull('released_for_tracking_at')->count(),
            'latest' => $latest,
        ];
    }

    private function getMapSummary(?string $eventId, array $householdSummary): array
    {
        if (! $eventId || ! Schema::hasTable('evacuation_centers')) {
            return [
                'evacuation_sites' => 0,
                'unsafe_households' => 0,
                'unchecked_households' => 0,
            ];
        }

        $evacuationSites = DB::table('evacuation_centers')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($eventId): void {
                $query->where('current_event_id', $eventId)
                    ->orWhereNull('current_event_id');
            })
            ->count();

        return [
            'evacuation_sites' => $evacuationSites,
            'unsafe_households' => $householdSummary['unsafe'],
            'unchecked_households' => $householdSummary['unchecked'],
        ];
    }

    private function getRecentActivity(?string $eventId): array
    {
        if (! $eventId || ! Schema::hasTable('household_status_logs')) {
            return [];
        }

        return DB::table('household_status_logs as hsl')
            ->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->where('hsl.disaster_id', $eventId)
            ->orderByDesc('hsl.submitted_at')
            ->limit(20)
            ->get([
                'h.household_name',
                'hsl.household_id',
                'hs.status_key',
                'hs.status_label',
                'hsl.source',
                'hsl.battery_level',
                'hsl.location_label',
                'hsl.submitted_at',
            ])
            ->map(fn (object $activity): array => [
                'time' => $this->formatTime($activity->submitted_at),
                'household_name' => $activity->household_name ?? $activity->household_id,
                'status' => $activity->status_label ?? 'Reported',
                'status_key' => $this->statusKey($activity->status_key ?? 'reported'),
                'source' => $this->label($activity->source),
                'battery_level' => $activity->battery_level,
                'location_label' => $activity->location_label,
            ])
            ->values()
            ->all();
    }

    private function householdBars(int $safeTotal, int $safeOnly, int $evacuated, int $unsafe, int $unchecked): array
    {
        $values = [$safeTotal, $safeOnly, $evacuated, $unsafe, $unchecked];
        $max = max(max($values), 1);

        return [
            $this->bar('Safe total', $safeTotal, 'safe-total', $max),
            $this->bar('Safe only', $safeOnly, 'safe-only', $max),
            $this->bar('Evacuated', $evacuated, 'evacuated', $max),
            $this->bar('Unsafe', $unsafe, 'unsafe', $max),
            $this->bar('Unchecked', $unchecked, 'unchecked', $max),
        ];
    }

    private function dispatchBars(int $dispatched, int $onScene, int $standby): array
    {
        $max = max($dispatched, $onScene, $standby, 1);

        return [
            $this->bar('Dispatched', $dispatched, 'dispatched', $max),
            $this->bar('On-scene', $onScene, 'on-scene', $max),
            $this->bar('Stand-by', $standby, 'standby', $max),
        ];
    }

    private function bar(string $label, int $value, string $className, int $max): array
    {
        $height = $value > 0 ? max(8, round(($value / $max) * 100)) : 0;

        return [
            'label' => $label,
            'value' => $value,
            'height' => $height.'%',
            'class_name' => $className,
        ];
    }

    private function sumStatusKeys(object $counts, array $keys): int
    {
        $total = 0;

        foreach ($keys as $key) {
            $total += (int) ($counts[$key] ?? 0);
        }

        return $total;
    }

    private function statusKey(?string $value): string
    {
        return strtolower(str_replace([' ', '_'], '-', $value ?? 'unknown'));
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    private function buildClosureNote(object $event): string
    {
        $householdSummary = $this->getHouseholdSummary($event->event_id);
        $statusLogs = DB::table('household_status_logs')->where('disaster_id', $event->event_id)->count();
        $dispatches = DB::table('responder_assignments')->where('disaster_id', $event->event_id)->count();
        $requests = DB::table('resource_requests')->where('source_reference', $event->event_id)->count();
        $weatherSnapshots = DB::table('weather_logs')->where('disaster_id', $event->event_id)->count();
        $broadcasts = DB::table('disaster_broadcasts')->where('disaster_id', $event->event_id)->count();
        $situationReports = DB::table('situation_reports')->where('disaster_id', $event->event_id)->count();

        return sprintf(
            'Closed active event "%s". Summary: %s/%s households reported; safe total %s; evacuated %s; unsafe %s; status logs %s; dispatch assignments %s; resource requests %s; weather snapshots %s; broadcasts %s; situation reports %s.',
            $event->name,
            $householdSummary['reported'],
            $householdSummary['total'],
            $householdSummary['safe_total'],
            $householdSummary['evacuated'],
            $householdSummary['unsafe'],
            $statusLogs,
            $dispatches,
            $requests,
            $weatherSnapshots,
            $broadcasts,
            $situationReports
        );
    }

    private function writeIncidentArchive(object $event, ?string $userId, string $closureNote, Carbon $endedAt): void
    {
        $nextArchiveId = ((int) DB::table('incident_archives')->max('archive_id')) + 1;

        DB::table('incident_archives')->insert([
            'archive_id' => $nextArchiveId,
            'archive_type' => 'disaster_event_log',
            'disaster_id' => $event->event_id,
            'reference_table' => 'disaster_events',
            'reference_id' => $event->event_id,
            'archived_by_admin_id' => $userId,
            'archive_note' => $closureNote,
            'archived_at' => $endedAt,
            'created_at' => $endedAt,
        ]);
    }

    private function archiveSituationReports(string $eventId, Carbon $endedAt): void
    {
        DB::table('situation_reports')
            ->where('disaster_id', $eventId)
            ->where(function ($query): void {
                $query->whereNull('is_archived')
                    ->orWhere('is_archived', 0);
            })
            ->update([
                'is_archived' => 1,
                'archived_at' => $endedAt,
                'updated_at' => $endedAt,
            ]);
    }

    private function writeAuditLog(object $event, mixed $user, Carbon $endedAt, string $closureNote, Request $request): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user?->user_id,
            'role_key' => $user?->role?->role_key,
            'module' => 'dashboard',
            'action' => 'close_active_event',
            'reference_table' => 'disaster_events',
            'reference_id' => $event->event_id,
            'old_values' => json_encode([
                'ended_at' => $event->ended_at,
            ]),
            'new_values' => json_encode([
                'ended_at' => $endedAt->toDateTimeString(),
                'archive_note' => $closureNote,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => $endedAt,
        ]);
    }

    private function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    private function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }
}
