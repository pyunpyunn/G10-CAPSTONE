<?php

namespace App\Services;

use App\Services\BarangayProfileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SituationReportService
{
    private BarangayProfileService $barangayProfile;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'barangay_profile' => $this->barangayProfile->current(),
                'events' => $this->eventOptions(),
                'reports' => $this->savedReports(),
                'note' => 'Select a disaster event first. The SitRep preview is generated from the selected event snapshot only.',
            ],
        ]);
    }

    public function eventSummary(string $eventId): JsonResponse
    {
        $event = $this->findEvent($eventId);

        if (! $event) {
            return response()->json([
                'message' => 'Disaster event was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'summary' => $this->buildSummary($event),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:255'],
            'report_number' => ['nullable', 'string', 'max:80'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'prepared_by' => ['nullable', 'string', 'max:150'],
            'reviewed_by' => ['nullable', 'string', 'max:150'],
            'report_status' => ['nullable', 'string', 'in:draft,generated,reviewed,archived'],
        ], [
            'event_id.required' => 'Select a disaster event before generating a SitRep.',
            'period_end.after_or_equal' => 'Period end must be after the period start.',
        ]);

        $event = $this->findEvent($validated['event_id']);

        if (! $event) {
            return response()->json([
                'message' => 'Disaster event was not found.',
            ], 404);
        }

        $report = DB::transaction(function () use ($request, $validated, $event): array {
            $now = now();
            $sitRepId = $this->nextId('situation_reports', 'sit_rep_id');
            $reportNumber = $validated['report_number'] ?: $this->nextReportNumber();
            $summary = $this->buildSummary($event, [
                'report_number' => $reportNumber,
                'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null,
                'prepared_by' => $validated['prepared_by'] ?? 'HQ/Admin Desk',
                'reviewed_by' => $validated['reviewed_by'] ?? 'Incident Commander',
                'generated_at' => $now->toDateTimeString(),
            ]);

            DB::table('situation_reports')->insert([
                'sit_rep_id' => $sitRepId,
                'report_number' => $reportNumber,
                'disaster_id' => $event->event_id,
                'created_by_admin_id' => $request->user()?->user_id,
                'household_id' => null,
                'summary' => json_encode($summary, JSON_UNESCAPED_SLASHES),
                'report_status' => $validated['report_status'] ?? 'generated',
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'escalated_to' => $validated['reviewed_by'] ?? 'Incident Commander',
                'is_archived' => 0,
                'generated_at' => $now,
                'archived_at' => null,
                'updated_at' => $now,
            ]);

            $this->writeAuditLog($request, 'generate', (string) $sitRepId, $summary);

            return $this->formatSavedReport(DB::table('situation_reports')->where('sit_rep_id', $sitRepId)->first(), true);
        });

        return response()->json([
            'message' => 'SitRep snapshot generated and locked.',
            'data' => [
                'report' => $report,
            ],
        ], 201);
    }

    public function show(int $sitRepId): JsonResponse
    {
        $report = DB::table('situation_reports')->where('sit_rep_id', $sitRepId)->first();

        if (! $report) {
            return response()->json([
                'message' => 'Situation report was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'report' => $this->formatSavedReport($report, true),
            ],
        ]);
    }

    public function pdf(int $sitRepId): JsonResponse
    {
        $report = DB::table('situation_reports')->where('sit_rep_id', $sitRepId)->first();

        if (! $report) {
            return response()->json([
                'message' => 'Situation report was not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'PDF export is reserved for the PDF package step. Use the saved report snapshot for now.',
            'data' => [
                'report' => $this->formatSavedReport($report, true),
            ],
        ], 501);
    }

    private function eventOptions(): array
    {
        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->orderByDesc('de.started_at')
            ->limit(50)
            ->get([
                'de.event_id',
                'de.name',
                'de.started_at',
                'de.ended_at',
                'dt.type_name',
                'sl.severity_label',
                'sl.severity_key',
            ])
            ->map(fn (object $event): array => $this->formatEventOption($event))
            ->values()
            ->all();
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
                'de.started_at',
                'de.ended_at',
                'dt.type_name',
                'sl.severity_label',
                'sl.severity_key',
            ])
            ->first();
    }

    private function buildSummary(object $event, array $meta = []): array
    {
        $household = $this->householdSummary($event->event_id);
        $dispatch = $this->dispatchSummary($event->event_id);
        $weather = $this->weatherSummary($event->event_id);
        $resources = $this->resourceSummary($event->event_id);
        $evacuation = $this->evacuationSummary($event->event_id);
        $casualties = $this->casualtySummary($dispatch);

        return [
            'report' => [
                'report_number' => $meta['report_number'] ?? $this->previewReportNumber(),
                'period_start' => $this->formatDateTime($meta['period_start'] ?? $event->started_at),
                'period_end' => $this->formatDateTime($meta['period_end'] ?? now()->toDateTimeString()),
                'prepared_by' => $meta['prepared_by'] ?? 'HQ/Admin Desk',
                'reviewed_by' => $meta['reviewed_by'] ?? 'Incident Commander',
                'generated_at' => $this->formatDateTime($meta['generated_at'] ?? now()->toDateTimeString()),
                'next_report' => $event->ended_at ? 'Final report filed' : $this->formatDateTime(now()->addHours(2)->toDateTimeString()),
            ],
            'barangay_profile' => $this->barangayProfile->current(),
            'event' => [
                'event_id' => $event->event_id,
                'name' => $event->name,
                'type' => $event->type_name ?? 'Disaster event',
                'severity' => $event->severity_label ?? 'Unspecified',
                'severity_key' => $event->severity_key ?? 'medium',
                'declared_at' => $this->formatDateTime($event->started_at),
                'declared_time' => $this->formatTime($event->started_at),
                'finished_at' => $event->ended_at ? $this->formatDateTime($event->ended_at) : 'Ongoing',
                'status' => $event->ended_at ? 'Closed' : 'Active',
                'situation_status' => $event->ended_at ? 'Final monitoring closed' : 'Active monitoring',
                'scope' => 'All registered households in barangay scope',
                'coverage' => $household['total'].' households in scope',
            ],
            'weather' => $weather,
            'household' => $household,
            'casualties' => $casualties,
            'evacuation' => $evacuation,
            'dispatch' => $dispatch,
            'damage' => [
                'partial' => 'For validation',
                'total' => 'For validation',
                'cost' => 'For validation',
            ],
            'resources' => $resources,
            'actions' => $this->actionRecommendations($household, $dispatch, $resources),
        ];
    }

    private function householdSummary(string $eventId): array
    {
        $total = DB::table('households')->whereNull('deleted_at')->count();

        $counts = DB::table('household_disasters as hd')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('hd.disaster_id', $eventId)
            ->select('hs.status_key', DB::raw('COUNT(*) as total'))
            ->groupBy('hs.status_key')
            ->pluck('total', 'status_key');

        $safeOnly = $this->sumStatusKeys($counts, ['active', 'returned', 'safe']);
        $evacuated = $this->sumStatusKeys($counts, ['evacuated', 'relocated']);
        $unsafe = $this->sumStatusKeys($counts, ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured']);
        $reported = DB::table('household_disasters')
            ->where('disaster_id', $eventId)
            ->whereNotNull('current_status_id')
            ->distinct()
            ->count('household_id');

        $safeTotal = $safeOnly + $evacuated;
        $unchecked = max($total - $reported, 0);
        $progress = $total > 0 ? round(($reported / $total) * 100) : 0;

        return [
            'total' => $total,
            'reported' => $reported,
            'progress_percent' => $progress,
            'progress_text' => $progress.'%',
            'progress_sub' => $reported.' / '.$total,
            'safe_total' => $safeTotal,
            'safe_only' => $safeOnly,
            'evacuated' => $evacuated,
            'unsafe' => $unsafe,
            'unchecked' => $unchecked,
            'safe_percent' => $this->percent($safeTotal, $total),
            'evacuated_percent' => $this->percent($evacuated, $total),
            'unsafe_percent' => $this->percent($unsafe, $total),
            'unchecked_percent' => $this->percent($unchecked, $total),
            'puroks' => $this->purokRows($eventId),
        ];
    }

    private function purokRows(string $eventId): array
    {
        $rows = DB::table('households as h')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_disasters as hd', function ($join) use ($eventId): void {
                $join->on('hd.household_id', '=', 'h.household_id')
                    ->where('hd.disaster_id', '=', $eventId);
            })
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->whereNull('h.deleted_at')
            ->get([
                'h.household_id',
                'a.purok_sitio',
                'hs.status_key',
            ])
            ->groupBy(fn (object $row): string => $row->purok_sitio ?: 'Unassigned');

        return $rows->map(function ($items, string $purok): array {
            $total = $items->count();
            $safe = $items->filter(fn (object $item): bool => in_array($item->status_key, ['active', 'returned', 'safe'], true))->count();
            $evacuated = $items->filter(fn (object $item): bool => in_array($item->status_key, ['evacuated', 'relocated'], true))->count();
            $unsafe = $items->filter(fn (object $item): bool => in_array($item->status_key, ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured'], true))->count();
            $reported = $items->whereNotNull('status_key')->count();

            return [
                'purok' => $purok,
                'total' => $total,
                'safe' => $safe,
                'evacuated' => $evacuated,
                'unsafe' => $unsafe,
                'unchecked' => max($total - $reported, 0),
            ];
        })->sortKeys()->values()->all();
    }

    private function weatherSummary(string $eventId): array
    {
        $weather = DB::table('weather_logs')
            ->where(function ($query) use ($eventId): void {
                $query->where('disaster_id', $eventId)
                    ->orWhereNull('disaster_id');
            })
            ->orderByRaw('CASE WHEN disaster_id = ? THEN 0 ELSE 1 END', [$eventId])
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $weather) {
            return [
                'condition' => 'No weather snapshot yet',
                'wind' => 'For update',
                'rainfall' => 'For update',
                'temperature' => 'For update',
                'source' => 'Confirm through PAGASA',
                'advisory' => 'Confirm official warnings through PAGASA before broadcasting.',
            ];
        }

        return [
            'condition' => $weather->condition_name ?: 'Weather monitoring',
            'wind' => $weather->wind_speed !== null ? $weather->wind_speed.' km/h '.$weather->wind_direction : 'For update',
            'rainfall' => $weather->rainfall_mm !== null ? $weather->rainfall_mm.' mm' : 'For update',
            'temperature' => $weather->temperature !== null ? $weather->temperature.' C' : 'For update',
            'source' => trim($weather->source_name.' - '.$this->formatTime($weather->observed_at)),
            'advisory' => $weather->advisory_text ?: 'Confirm official warnings through PAGASA before broadcasting.',
        ];
    }

    private function evacuationSummary(string $eventId): array
    {
        return DB::table('evacuation_centers')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($eventId): void {
                $query->where('current_event_id', $eventId)
                    ->orWhereNull('current_event_id');
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(function (object $center): array {
                $capacity = (int) ($center->capacity ?? 0);
                $occupancy = (int) ($center->current_occupancy ?? 0);
                $remaining = max($capacity - $occupancy, 0);

                return [
                    'name' => $center->name ?: $center->evacuation_center_id,
                    'type' => $center->center_type ?: 'Evacuation site',
                    'status' => $center->status ?: 'active',
                    'families' => 'For update',
                    'persons' => $occupancy > 0 ? $occupancy.' persons' : 'Occupancy not encoded',
                    'capacity_status' => $capacity > 0 ? $remaining.' slots left' : 'Capacity not encoded',
                    'capacity_tone' => $capacity > 0 && $remaining <= 10 ? 'amber' : 'green',
                ];
            })
            ->values()
            ->all();
    }

    private function dispatchSummary(string $eventId): array
    {
        $assignments = DB::table('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->where('ra.disaster_id', $eventId)
            ->orderByDesc('ra.assigned_at')
            ->get([
                'ra.*',
                'rt.team_name',
                'rt.team_code',
                'r.full_name as responder_name',
            ]);

        $rows = $assignments->take(8)->map(function (object $assignment): array {
            $route = $this->decodeJson($assignment->route_notes);
            $outcomes = $this->decodeJson($assignment->outcome_notes);

            return [
                'team' => $assignment->team_name ?: $assignment->responder_name ?: 'Assigned responder',
                'deployed_at' => $this->formatTime($assignment->assigned_at),
                'area' => $assignment->assigned_area ?: 'Area not encoded',
                'households_reached' => $this->reachedHouseholds($outcomes).' / '.(int) ($route['households_to_cover'] ?? 0),
                'outcomes' => $this->outcomeText($outcomes),
                'status' => $this->label($assignment->status ?: 'assigned'),
                'status_tone' => $this->statusTone($assignment->status),
                'outcome_counts' => $outcomes,
            ];
        })->values()->all();

        $timeline = $this->timelineRows($eventId, $assignments);

        return [
            'total' => $assignments->count(),
            'dispatched' => $assignments->filter(fn (object $item): bool => in_array($item->status, ['dispatched', 'en_route', 'accepted'], true))->count(),
            'on_scene' => $assignments->filter(fn (object $item): bool => in_array($item->status, ['on_scene', 'onscene'], true))->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'rows' => $rows,
            'timeline' => $timeline,
            'raw_outcomes' => $assignments->map(fn (object $assignment): array => $this->decodeJson($assignment->outcome_notes))->all(),
        ];
    }

    private function timelineRows(string $eventId, $assignments): array
    {
        $broadcasts = DB::table('disaster_broadcasts')
            ->where('disaster_id', $eventId)
            ->orderBy('sent_at')
            ->limit(4)
            ->get();

        $timeline = collect();

        foreach ($broadcasts as $broadcast) {
            $timeline->push([
                'time' => $this->formatTime($broadcast->sent_at),
                'title' => 'Public broadcast',
                'actor' => $broadcast->broadcast_title ?: 'HQ/Admin desk',
                'detail' => $broadcast->scope_type ? $this->label($broadcast->scope_type).' alert sent' : 'Alert sent',
                'status' => $this->label($broadcast->status ?: 'sent'),
                'tone' => 'green',
            ]);
        }

        foreach ($assignments->take(4) as $assignment) {
            $timeline->push([
                'time' => $this->formatTime($assignment->assigned_at),
                'title' => 'Dispatch assignment',
                'actor' => $assignment->team_name ?: $assignment->responder_name ?: 'Response team',
                'detail' => $assignment->assigned_area ?: 'No area encoded',
                'status' => $this->label($assignment->status ?: 'assigned'),
                'tone' => $this->statusTone($assignment->status),
            ]);
        }

        return $timeline->sortBy('time')->values()->all();
    }

    private function casualtySummary(array $dispatch): array
    {
        $safe = 0;
        $evacuated = 0;
        $unsafe = 0;
        $injured = 0;
        $missing = 0;

        foreach ($dispatch['raw_outcomes'] as $outcomes) {
            $safe += (int) ($outcomes['safe_count'] ?? 0);
            $evacuated += (int) ($outcomes['evacuated_count'] ?? 0);
            $unsafe += (int) ($outcomes['unsafe_count'] ?? 0);
            $injured += (int) ($outcomes['injured_count'] ?? 0);
            $missing += (int) ($outcomes['missing_count'] ?? 0);
        }

        return [
            'deaths' => 0,
            'missing' => $missing,
            'injured' => $injured,
            'rescued' => $safe + $evacuated,
            'unsafe' => $unsafe,
        ];
    }

    private function resourceSummary(string $eventId): array
    {
        $requests = DB::table('resource_requests')
            ->where(function ($query) use ($eventId): void {
                $query->where('source_reference', $eventId)
                    ->orWhereNull('source_reference');
            })
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return [
            'needs_validation' => $requests->where('validation_status', 'needs_validation')->count(),
            'verified' => $requests->where('validation_status', 'verified')->count(),
            'forwarded' => $requests->where('validation_status', 'forwarded')->count(),
            'returned' => $requests->where('validation_status', 'returned')->count(),
            'rows' => $requests->map(fn (object $request): array => [
                'item' => $request->item_name ?: $request->resource_type ?: 'Request',
                'quantity' => trim(($request->quantity ?? 0).' '.($request->unit ?? '')),
                'source' => $request->request_source ? $this->label($request->request_source) : 'Shared DB',
                'status' => $this->label($request->validation_status ?: 'needs_validation'),
                'status_tone' => $this->statusTone($request->validation_status),
            ])->values()->all(),
        ];
    }

    private function actionRecommendations(array $household, array $dispatch, array $resources): array
    {
        return [
            [
                'unit' => 'BDRRMC EOC',
                'action' => $household['unchecked'] > 0 ? 'Continue household status monitoring' : 'Maintain event watch',
            ],
            [
                'unit' => 'Search and rescue',
                'action' => $dispatch['total'] > 0 ? 'Update dispatch outcome counts' : 'Prepare dispatch teams for priority areas',
            ],
            [
                'unit' => 'Resources desk',
                'action' => $resources['needs_validation'] > 0 ? 'Validate pending requests before handoff' : 'Monitor TrackingAid handoff references',
            ],
        ];
    }

    private function savedReports(): array
    {
        return DB::table('situation_reports as sr')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'sr.disaster_id')
            ->orderByDesc('sr.generated_at')
            ->limit(20)
            ->get([
                'sr.*',
                'de.name as event_name',
            ])
            ->map(fn (object $report): array => $this->formatSavedReport($report))
            ->values()
            ->all();
    }

    private function formatSavedReport(?object $report, bool $includeSummary = false): ?array
    {
        if (! $report) {
            return null;
        }

        $summary = $this->decodeJson($report->summary);

        $data = [
            'sit_rep_id' => $report->sit_rep_id,
            'report_number' => $report->report_number,
            'event_id' => $report->disaster_id,
            'event_name' => $report->event_name ?? ($summary['event']['name'] ?? 'Disaster event'),
            'report_status' => $this->label($report->report_status),
            'generated_at' => $this->formatDateTime($report->generated_at),
            'generated_time' => $this->formatTime($report->generated_at),
            'is_archived' => (bool) $report->is_archived,
        ];

        if ($includeSummary) {
            $data['summary'] = $summary;
        }

        return $data;
    }

    private function formatEventOption(object $event): array
    {
        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'label' => $event->name.' - '.$this->formatDate($event->started_at),
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Unspecified',
            'declared_at' => $this->formatDateTime($event->started_at),
            'finished_at' => $event->ended_at ? $this->formatDateTime($event->ended_at) : 'Ongoing',
            'status' => $event->ended_at ? 'Closed' : 'Active',
            'scope' => 'All registered households',
        ];
    }

    private function previewReportNumber(): string
    {
        return 'SITREP-DRAFT-'.now()->format('Ymd');
    }

    private function nextReportNumber(): string
    {
        $prefix = 'SITREP-'.now()->format('Y').'-';
        $count = DB::table('situation_reports')
            ->where('report_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function nextId(string $table, string $column): int
    {
        return ((int) DB::table($table)->lockForUpdate()->max($column)) + 1;
    }

    private function sumStatusKeys(object $counts, array $keys): int
    {
        $total = 0;

        foreach ($keys as $key) {
            $total += (int) ($counts[$key] ?? 0);
        }

        return $total;
    }

    private function percent(int $value, int $total): int
    {
        return $total > 0 ? round(($value / $total) * 100) : 0;
    }

    private function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function reachedHouseholds(array $outcomes): int
    {
        return (int) ($outcomes['safe_count'] ?? 0)
            + (int) ($outcomes['evacuated_count'] ?? 0)
            + (int) ($outcomes['unsafe_count'] ?? 0)
            + (int) ($outcomes['injured_count'] ?? 0)
            + (int) ($outcomes['missing_count'] ?? 0);
    }

    private function outcomeText(array $outcomes): string
    {
        $parts = [
            'safe '.$this->countValue($outcomes, 'safe_count'),
            'evac '.$this->countValue($outcomes, 'evacuated_count'),
            'unsafe '.$this->countValue($outcomes, 'unsafe_count'),
            'injured '.$this->countValue($outcomes, 'injured_count'),
            'missing '.$this->countValue($outcomes, 'missing_count'),
        ];

        return implode(' / ', $parts);
    }

    private function countValue(array $items, string $key): int
    {
        return (int) ($items[$key] ?? 0);
    }

    private function statusTone(?string $status): string
    {
        $key = str_replace('-', '_', strtolower((string) $status));

        return match ($key) {
            'completed', 'verified', 'forwarded', 'sent', 'on_scene', 'onscene' => 'green',
            'needs_validation', 'pending', 'dispatched', 'en_route' => 'amber',
            'returned', 'cancelled', 'failed' => 'red',
            default => 'gray',
        };
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    private function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    private function formatDate(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M Y') : null;
    }

    private function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }

    private function writeAuditLog(Request $request, string $action, string $reportId, array $summary): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'situation_reporting',
            'action' => $action,
            'reference_table' => 'situation_reports',
            'reference_id' => $reportId,
            'old_values' => null,
            'new_values' => json_encode([
                'report_number' => $summary['report']['report_number'] ?? null,
                'event_id' => $summary['event']['event_id'] ?? null,
            ], JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
