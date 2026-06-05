<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BarangayProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ArchiveController extends Controller
{
    private const CATEGORIES = [
        'disaster-events' => 'Disaster Event',
        'household-status-logs' => 'Household Status Logs',
        'dispatch-logs' => 'Rescue Dispatch Logs',
        'resource-requests' => 'Resources & Requests',
        'situation-reports' => 'Situation Reporting',
    ];

    private BarangayProfileService $barangayProfile;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
    }

    public function disasterEvents(Request $request): JsonResponse
    {
        [$paginator, $records] = $this->disasterEventRows($request);

        return $this->archiveResponse('disaster-events', $paginator, $records);
    }

    public function householdStatusLogs(Request $request): JsonResponse
    {
        [$paginator, $records] = $this->householdStatusRows($request);

        return $this->archiveResponse('household-status-logs', $paginator, $records);
    }

    public function dispatchLogs(Request $request): JsonResponse
    {
        [$paginator, $records] = $this->dispatchRows($request);

        return $this->archiveResponse('dispatch-logs', $paginator, $records);
    }

    public function resourceRequests(Request $request): JsonResponse
    {
        [$paginator, $records] = $this->resourceRequestRows($request);

        return $this->archiveResponse('resource-requests', $paginator, $records);
    }

    public function situationReports(Request $request): JsonResponse
    {
        [$paginator, $records] = $this->situationReportRows($request);

        return $this->archiveResponse('situation-reports', $paginator, $records);
    }

    public function export(Request $request): Response|JsonResponse
    {
        $category = (string) $request->query('category', 'disaster-events');
        $type = strtolower((string) $request->query('type', 'csv'));

        if (! array_key_exists($category, self::CATEGORIES)) {
            return response()->json([
                'success' => false,
                'message' => 'Select a valid archive category before exporting.',
            ], 422);
        }

        if ($type === 'pdf') {
            return response()->json([
                'success' => false,
                'message' => 'Archive PDF export is reserved for the PDF package step. Use CSV export for now.',
            ], 501);
        }

        if ($type !== 'csv') {
            return response()->json([
                'success' => false,
                'message' => 'Only CSV export is available in this version.',
            ], 422);
        }

        [$paginator, $records] = $this->categoryRows($category, $request, 1000);
        $headers = $this->csvHeaders($category);
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_values($headers), ',', '"', '');

        foreach ($records as $record) {
            $exportRow = $record['export'] ?? [];
            $line = [];

            foreach (array_keys($headers) as $key) {
                $line[] = $exportRow[$key] ?? '';
            }

            fputcsv($handle, $line, ',', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'resqperation-'.$category.'-archive-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Archive-Total' => (string) $paginator->total(),
        ]);
    }

    private function categoryRows(string $category, Request $request, int $perPage): array
    {
        return match ($category) {
            'household-status-logs' => $this->householdStatusRows($request, $perPage),
            'dispatch-logs' => $this->dispatchRows($request, $perPage),
            'resource-requests' => $this->resourceRequestRows($request, $perPage),
            'situation-reports' => $this->situationReportRows($request, $perPage),
            default => $this->disasterEventRows($request, $perPage),
        };
    }

    private function archiveResponse(string $category, object $paginator, array $records): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => self::CATEGORIES[$category].' archive loaded.',
            'data' => [
                'barangay_profile' => $this->barangayProfile->current(),
                'category' => $category,
                'category_label' => self::CATEGORIES[$category],
                'records' => [
                    'data' => $records,
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'filters' => [
                    'events' => $this->eventOptions(),
                    'puroks' => $this->purokOptions(),
                    'statuses' => $this->statusOptions(),
                ],
                'note' => 'Archive reads existing disaster, household, dispatch, request, and SitRep history. No duplicate archive copy is created here.',
            ],
        ]);
    }

    private function disasterEventRows(Request $request, int $perPage = 25): array
    {
        $query = DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'de.ended_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ]);

        $this->applySearch($query, $request, [
            'de.event_id',
            'de.name',
            'dt.type_name',
            'sl.severity_label',
        ]);
        $this->applyEventFilter($query, $request, 'de.event_id');
        $this->applyDisasterStatusFilter($query, $request);
        $this->applyDisasterPurokFilter($query, $request);

        $paginator = $query
            ->orderByDesc('de.started_at')
            ->paginate($this->perPage($request, $perPage));

        $records = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatDisasterEvent($row))
            ->values()
            ->all();

        return [$paginator, $records];
    }

    private function householdStatusRows(Request $request, int $perPage = 25): array
    {
        $query = DB::table('household_status_logs as hsl')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'hsl.disaster_id')
            ->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'hsl.responder_id')
            ->select([
                'hsl.*',
                'de.name as event_name',
                'de.started_at as event_started_at',
                'de.ended_at as event_ended_at',
                'h.household_code',
                'h.household_name',
                'a.purok_sitio',
                'hs.status_key',
                'hs.status_label',
                'r.full_name as responder_name',
            ]);

        $this->applySearch($query, $request, [
            'hsl.status_log_id',
            'de.name',
            'h.household_code',
            'h.household_name',
            'hsl.location_label',
            'hsl.notes',
            'hsl.source',
            'hs.status_label',
        ]);
        $this->applyEventFilter($query, $request, 'hsl.disaster_id');
        $this->applyPurokFilter($query, $request, 'a.purok_sitio');
        $this->applyHouseholdStatusFilter($query, $request);

        $paginator = $query
            ->orderByDesc('hsl.submitted_at')
            ->orderByDesc('hsl.status_log_id')
            ->paginate($this->perPage($request, $perPage));

        $records = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatHouseholdStatus($row))
            ->values()
            ->all();

        return [$paginator, $records];
    }

    private function dispatchRows(Request $request, int $perPage = 25): array
    {
        $query = DB::table('responder_assignments as ra')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'ra.disaster_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->select([
                'ra.*',
                'de.name as event_name',
                'de.started_at as event_started_at',
                'de.ended_at as event_ended_at',
                'rt.team_name',
                'rt.team_code',
                'r.full_name as responder_name',
            ]);

        $this->applySearch($query, $request, [
            'ra.assignment_code',
            'de.name',
            'rt.team_name',
            'rt.team_code',
            'r.full_name',
            'ra.assigned_area',
            'ra.status',
            'ra.dispatch_notes',
            'ra.outcome_notes',
        ]);
        $this->applyEventFilter($query, $request, 'ra.disaster_id');
        $this->applyPurokFilter($query, $request, 'ra.assigned_area');
        $this->applyDispatchStatusFilter($query, $request);

        $paginator = $query
            ->orderByDesc('ra.assigned_at')
            ->orderByDesc('ra.assignment_id')
            ->paginate($this->perPage($request, $perPage));

        $records = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatDispatch($row))
            ->values()
            ->all();

        return [$paginator, $records];
    }

    private function resourceRequestRows(Request $request, int $perPage = 25): array
    {
        $query = DB::table('resource_requests as rr')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'rr.source_reference')
            ->leftJoin('evacuation_centers as ec', 'ec.evacuation_center_id', '=', 'rr.evacuation_center_id')
            ->leftJoin('urgency_levels as ul', 'ul.urgency_id', '=', 'rr.urgency_id')
            ->select([
                'rr.*',
                'de.name as event_name',
                'de.started_at as event_started_at',
                'de.ended_at as event_ended_at',
                'ec.name as evacuation_center_name',
                'ec.osm_address as evacuation_center_address',
                'ec.current_event_id as evacuation_event_id',
                'ul.urgency_key',
                'ul.urgency_label',
            ]);

        $this->applySearch($query, $request, [
            'rr.request_id',
            'rr.request_source',
            'rr.source_reference',
            'rr.requested_by',
            'rr.resource_type',
            'rr.item_name',
            'rr.description',
            'rr.validation_status',
            'rr.tracking_reference',
            'ec.name',
            'ec.osm_address',
        ]);
        $this->applyEventFilter($query, $request, 'rr.source_reference');
        $this->applyResourcePurokFilter($query, $request);
        $this->applyResourceStatusFilter($query, $request);

        $paginator = $query
            ->orderByDesc('rr.created_at')
            ->paginate($this->perPage($request, $perPage));

        $records = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatResourceRequest($row))
            ->values()
            ->all();

        return [$paginator, $records];
    }

    private function situationReportRows(Request $request, int $perPage = 25): array
    {
        $query = DB::table('situation_reports as sr')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'sr.disaster_id')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->select([
                'sr.*',
                'de.name as event_name',
                'de.started_at as event_started_at',
                'de.ended_at as event_ended_at',
                'dt.type_name',
            ]);

        $this->applySearch($query, $request, [
            'sr.sit_rep_id',
            'sr.report_number',
            'sr.summary',
            'sr.report_status',
            'sr.escalated_to',
            'de.name',
            'dt.type_name',
        ]);
        $this->applyEventFilter($query, $request, 'sr.disaster_id');
        $this->applySituationPurokFilter($query, $request);
        $this->applySituationStatusFilter($query, $request);

        $paginator = $query
            ->orderByDesc('sr.generated_at')
            ->orderByDesc('sr.sit_rep_id')
            ->paginate($this->perPage($request, $perPage));

        $records = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatSituationReport($row))
            ->values()
            ->all();

        return [$paginator, $records];
    }

    private function formatDisasterEvent(object $row): array
    {
        $weather = $this->latestWeather($row->event_id);
        $broadcastCount = DB::table('disaster_broadcasts')->where('disaster_id', $row->event_id)->count();
        $latestBroadcast = DB::table('disaster_broadcasts')
            ->where('disaster_id', $row->event_id)
            ->orderByDesc('sent_at')
            ->first();
        $householdScope = DB::table('household_disasters')->where('disaster_id', $row->event_id)->count();
        $scopeText = $this->eventPurokScope($row->event_id);
        $archive = DB::table('incident_archives')
            ->where('disaster_id', $row->event_id)
            ->orderByDesc('archived_at')
            ->first();
        $status = $row->ended_at ? 'closed' : 'active';
        $isCritical = $this->isCriticalSeverity($row->severity_key, $row->severity_label);

        $record = [
            'id' => $row->event_id,
            'event_id' => $row->event_id,
            'event_name' => $row->name,
            'purok_text' => $scopeText,
            'status_key' => trim($status.' '.($isCritical ? 'critical' : '')),
            'event' => [
                'title' => $row->name,
                'meta' => 'Event ID '.$row->event_id,
            ],
            'disaster' => [
                'title' => ($row->type_name ?: 'Disaster event').' - '.($weather['condition'] ?: 'Weather not saved'),
                'meta' => $weather['meta'] ?: ($row->severity_label ?: 'Severity not recorded'),
            ],
            'period' => [
                'title' => $this->formatDateTime($row->started_at).' - '.($row->ended_at ? $this->formatDateTime($row->ended_at) : 'Ongoing'),
                'meta' => $archive?->archive_note ?: ($row->ended_at ? 'Closed event log' : 'Active event log'),
            ],
            'broadcasts' => [
                'title' => $broadcastCount.' broadcast'.($broadcastCount === 1 ? '' : 's'),
                'meta' => $latestBroadcast ? 'Latest: '.$latestBroadcast->broadcast_title.' - '.$this->formatTime($latestBroadcast->sent_at) : 'No broadcast saved',
            ],
            'scope' => [
                'title' => $householdScope.' HH in scope',
                'meta' => $scopeText,
            ],
            'status' => [
                'label' => $row->ended_at ? 'Closed' : 'Active',
                'tone' => $row->ended_at ? 'gray' : ($isCritical ? 'red' : 'blue'),
            ],
        ];

        $record['details'] = $this->details([
            'Event' => $record['event']['title'],
            'Event ID' => $row->event_id,
            'Disaster / weather' => $record['disaster']['title'],
            'Declared / finished' => $record['period']['title'],
            'Broadcast logs' => $record['broadcasts']['title'].' - '.$record['broadcasts']['meta'],
            'Household scope' => $record['scope']['title'].' - '.$record['scope']['meta'],
            'Status' => $record['status']['label'],
        ]);
        $record['export'] = [
            'event' => $row->name,
            'reference' => $row->event_id,
            'type_weather' => $record['disaster']['title'],
            'period' => $record['period']['title'],
            'broadcasts' => $record['broadcasts']['title'],
            'scope' => $record['scope']['title'],
            'status' => $record['status']['label'],
        ];

        return $record;
    }

    private function formatHouseholdStatus(object $row): array
    {
        $statusLabel = $row->status_label ?: $this->label($row->status_key);
        $statusKey = $this->statusKey($row->status_key ?: $statusLabel);
        $purok = $row->purok_sitio ?: 'Unassigned';
        $householdName = $row->household_name ?: $row->household_code ?: 'Household';
        $source = $this->label($row->source ?: 'mobile_report');
        $meta = $row->location_label ?: 'No location label';

        if ($row->location_accuracy_m !== null) {
            $meta .= ' - '.$row->location_accuracy_m.'m accuracy';
        }

        $record = [
            'id' => $row->status_log_id,
            'event_id' => $row->disaster_id,
            'event_name' => $row->event_name ?: 'Disaster event',
            'purok_text' => $purok,
            'status_key' => trim($statusKey.' '.$this->eventStatusKey($row->event_ended_at)),
            'datetime' => $this->formatDateTime($row->submitted_at),
            'event' => $row->event_name ?: 'Disaster event',
            'household' => [
                'title' => $householdName,
                'meta' => $meta,
            ],
            'purok' => $purok,
            'status_change' => [
                'label' => 'Reported '.$statusLabel,
                'tone' => $this->statusTone($statusKey),
            ],
            'source' => [
                'title' => $source,
                'meta' => $row->notes ?: ($row->responder_name ? 'Logged by '.$row->responder_name : 'No remarks'),
            ],
        ];

        $record['details'] = $this->details([
            'Date / time' => $record['datetime'],
            'Event' => $record['event'],
            'Household / geotag' => $householdName.' - '.$meta,
            'Purok' => $purok,
            'Status change' => $record['status_change']['label'],
            'Source / remarks' => $source.' - '.$record['source']['meta'],
            'Battery level' => $row->battery_level !== null ? $row->battery_level.'%' : 'Not recorded',
            'Coordinates' => $row->latitude && $row->longitude ? $row->latitude.', '.$row->longitude : 'Not recorded',
        ]);
        $record['export'] = [
            'datetime' => $record['datetime'],
            'event' => $record['event'],
            'reference' => (string) $row->status_log_id,
            'household' => $householdName,
            'purok' => $purok,
            'status' => $record['status_change']['label'],
            'source' => $source,
        ];

        return $record;
    }

    private function formatDispatch(object $row): array
    {
        $route = $this->decodeJson($row->route_notes);
        $outcome = $this->decodeJson($row->outcome_notes);
        $teamName = $row->team_name ?: $row->responder_name ?: 'Assigned responder';
        $area = $row->assigned_area ?: ($route['area'] ?? 'Area not recorded');
        $status = $this->statusKey($row->status ?: 'assigned');
        $outcomeText = $this->dispatchOutcomeText($outcome, $row->outcome_notes);
        $purok = $this->purokFromText($area);

        $record = [
            'id' => $row->assignment_id,
            'event_id' => $row->disaster_id,
            'event_name' => $row->event_name ?: 'Disaster event',
            'purok_text' => $purok,
            'status_key' => trim($status.' '.$this->eventStatusKey($row->event_ended_at)),
            'datetime' => $this->formatDateTime($row->assigned_at),
            'event' => $row->event_name ?: 'Disaster event',
            'team_route' => [
                'title' => $teamName,
                'meta' => $area,
            ],
            'purok' => $purok,
            'status' => [
                'label' => $this->label($status),
                'tone' => $this->statusTone($status),
            ],
            'outcome' => [
                'title' => $outcomeText,
                'meta' => $row->dispatch_notes ?: ($row->assignment_code ?: 'No dispatch note'),
            ],
        ];

        $record['details'] = $this->details([
            'Date / time' => $record['datetime'],
            'Event' => $record['event'],
            'Team / route' => $teamName.' - '.$area,
            'Purok' => $purok,
            'Status' => $record['status']['label'],
            'Outcome entry' => $outcomeText,
            'Assignment code' => $row->assignment_code ?: 'Not recorded',
            'Priority' => $row->priority_level ?: 'Not recorded',
        ]);
        $record['export'] = [
            'datetime' => $record['datetime'],
            'event' => $record['event'],
            'reference' => $row->assignment_code ?: (string) $row->assignment_id,
            'team_route' => $teamName.' - '.$area,
            'purok' => $purok,
            'status' => $record['status']['label'],
            'outcome' => $outcomeText,
        ];

        return $record;
    }

    private function formatResourceRequest(object $row): array
    {
        $validation = $this->statusKey($row->validation_status ?: 'needs_validation');
        $quantity = trim((string) ($row->quantity ?? '').' '.(string) ($row->unit ?? ''));
        $requestTitle = $row->request_id;
        $requestMeta = ($row->item_name ?: $row->resource_type ?: 'Request').' - '.($quantity !== '' ? $quantity : 'No quantity');
        $site = $row->evacuation_center_name ?: $row->evacuation_center_id ?: 'Area not recorded';
        $event = $row->event_name ?: ($row->source_reference ?: 'Shared DB request');

        $record = [
            'id' => $row->request_id,
            'event_id' => $row->source_reference,
            'event_name' => $event,
            'purok_text' => $site.' '.$row->evacuation_center_address.' '.$row->description,
            'status_key' => trim($validation.' '.$this->eventStatusKey($row->event_ended_at)),
            'datetime' => $this->formatDateTime($row->created_at),
            'event' => $event,
            'request' => [
                'title' => $requestTitle,
                'meta' => $requestMeta,
            ],
            'purok_site' => [
                'title' => $site,
                'meta' => $row->evacuation_center_address ?: $row->description ?: 'No area note',
            ],
            'validation' => [
                'label' => $this->label($validation),
                'tone' => $this->statusTone($validation),
            ],
            'handoff' => [
                'title' => $row->tracking_reference ?: ($validation === 'verified' ? 'Ready for TrackingAid' : 'Not forwarded'),
                'meta' => $row->released_for_tracking_at ? $this->formatDateTime($row->released_for_tracking_at) : ($row->validation_notes ?: 'Validation record only'),
            ],
        ];

        $record['details'] = $this->details([
            'Date / time' => $record['datetime'],
            'Event' => $event,
            'Request' => $requestTitle.' - '.$requestMeta,
            'Purok / site' => $record['purok_site']['title'].' - '.$record['purok_site']['meta'],
            'Validation' => $record['validation']['label'],
            'Release / handoff' => $record['handoff']['title'].' - '.$record['handoff']['meta'],
            'Requested by' => $row->requested_by ?: 'Not recorded',
            'Source' => $this->label($row->request_source ?: 'shared_db'),
        ]);
        $record['export'] = [
            'datetime' => $record['datetime'],
            'event' => $event,
            'reference' => $requestTitle,
            'request' => $requestMeta,
            'purok_site' => $record['purok_site']['title'],
            'status' => $record['validation']['label'],
            'handoff' => $record['handoff']['title'],
        ];

        return $record;
    }

    private function formatSituationReport(object $row): array
    {
        $summary = $this->decodeJson($row->summary);
        $report = $summary['report'] ?? [];
        $household = $summary['household'] ?? [];
        $casualties = $summary['casualties'] ?? [];
        $event = $summary['event'] ?? [];
        $reportNumber = $row->report_number ?: 'SITREP-'.$row->sit_rep_id;
        $eventName = $row->event_name ?: ($event['name'] ?? 'Disaster event');
        $period = ($report['period_start'] ?? $this->formatDateTime($row->event_started_at)).' - '.($report['period_end'] ?? $this->formatDateTime($row->generated_at));
        $population = (int) ($household['total'] ?? 0).' HH - '.(int) ($household['evacuated'] ?? 0).' evacuated';
        $casualtyMeta = (int) ($casualties['deaths'] ?? 0).' deaths - '.(int) ($casualties['missing'] ?? 0).' missing - '.(int) ($casualties['injured'] ?? 0).' injured';
        $status = $this->statusKey($row->report_status ?: 'generated');

        $record = [
            'id' => $row->sit_rep_id,
            'event_id' => $row->disaster_id,
            'event_name' => $eventName,
            'purok_text' => $event['scope'] ?? ($summary['event']['scope'] ?? 'Barangay scope'),
            'status_key' => trim($status.' generated '.$this->eventStatusKey($row->event_ended_at)),
            'sitrep' => [
                'title' => $reportNumber,
                'meta' => 'Generated '.$this->formatDateTime($row->generated_at),
            ],
            'event' => [
                'title' => $eventName,
                'meta' => ($row->type_name ?: ($event['type'] ?? 'Disaster event')).' - '.($event['severity'] ?? 'Severity not recorded'),
            ],
            'period' => [
                'title' => $period,
                'meta' => $event['scope'] ?? 'Barangay scope',
            ],
            'submitted_by' => [
                'title' => $report['prepared_by'] ?? 'HQ/Admin Desk',
                'meta' => 'Reviewed by '.($report['reviewed_by'] ?? $row->escalated_to ?? 'Incident Commander'),
            ],
            'population' => [
                'title' => $population,
                'meta' => $casualtyMeta,
            ],
            'status' => [
                'label' => $this->label($status),
                'tone' => $status === 'generated' ? 'purple' : $this->statusTone($status),
            ],
        ];

        $record['details'] = $this->details([
            'SitRep No.' => $reportNumber,
            'Event / disaster type' => $record['event']['title'].' - '.$record['event']['meta'],
            'Reporting period' => $period,
            'Submitted by' => $record['submitted_by']['title'].' - '.$record['submitted_by']['meta'],
            'Population / casualties' => $population.' - '.$casualtyMeta,
            'Status' => $record['status']['label'],
            'Generated at' => $this->formatDateTime($row->generated_at),
        ]);
        $record['export'] = [
            'datetime' => $this->formatDateTime($row->generated_at),
            'event' => $eventName,
            'reference' => $reportNumber,
            'period' => $period,
            'submitted_by' => $record['submitted_by']['title'],
            'population' => $population,
            'status' => $record['status']['label'],
        ];

        return $record;
    }

    private function applySearch(object $query, Request $request, array $columns): void
    {
        $search = trim((string) $request->query('search', ''));

        if ($search === '') {
            return;
        }

        $query->where(function ($inner) use ($columns, $search): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function applyEventFilter(object $query, Request $request, string $column): void
    {
        $eventId = trim((string) $request->query('event_id', 'all'));

        if ($eventId !== '' && $eventId !== 'all') {
            $query->where($column, $eventId);
        }
    }

    private function applyPurokFilter(object $query, Request $request, string $column): void
    {
        $purok = trim((string) $request->query('purok', 'all'));

        if ($purok !== '' && $purok !== 'all') {
            $query->where($column, 'like', "%{$purok}%");
        }
    }

    private function applyDisasterPurokFilter(object $query, Request $request): void
    {
        $purok = trim((string) $request->query('purok', 'all'));

        if ($purok === '' || $purok === 'all') {
            return;
        }

        $query->whereExists(function ($subQuery) use ($purok): void {
            $subQuery->selectRaw('1')
                ->from('household_disasters as hd')
                ->join('households as h', 'h.household_id', '=', 'hd.household_id')
                ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
                ->whereColumn('hd.disaster_id', 'de.event_id')
                ->where('a.purok_sitio', 'like', "%{$purok}%");
        });
    }

    private function applyResourcePurokFilter(object $query, Request $request): void
    {
        $purok = trim((string) $request->query('purok', 'all'));

        if ($purok === '' || $purok === 'all') {
            return;
        }

        $query->where(function ($inner) use ($purok): void {
            $inner->where('ec.name', 'like', "%{$purok}%")
                ->orWhere('ec.osm_address', 'like', "%{$purok}%")
                ->orWhere('rr.description', 'like', "%{$purok}%")
                ->orWhere('rr.evacuation_center_id', 'like', "%{$purok}%");
        });
    }

    private function applySituationPurokFilter(object $query, Request $request): void
    {
        $purok = trim((string) $request->query('purok', 'all'));

        if ($purok !== '' && $purok !== 'all') {
            $query->where('sr.summary', 'like', "%{$purok}%");
        }
    }

    private function applyDisasterStatusFilter(object $query, Request $request): void
    {
        $status = $this->statusKey((string) $request->query('status', 'all'));

        if ($status === 'all') {
            return;
        }

        if ($status === 'active') {
            $query->whereNull('de.ended_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('de.ended_at');
        } elseif ($status === 'critical') {
            $query->where(function ($inner): void {
                $inner->whereIn('sl.severity_key', ['high', 'critical', 'severe'])
                    ->orWhere('sl.severity_label', 'like', '%High%')
                    ->orWhere('sl.severity_label', 'like', '%Critical%');
            });
        }
    }

    private function applyHouseholdStatusFilter(object $query, Request $request): void
    {
        $status = $this->statusKey((string) $request->query('status', 'all'));

        if ($status === 'all') {
            return;
        }

        if ($status === 'active') {
            $query->whereNull('de.ended_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('de.ended_at');
        } elseif ($status === 'critical') {
            $query->whereIn('hs.status_key', ['unsafe', 'injured', 'missing', 'not_evacuated', 'displaced']);
        } else {
            $query->where('hs.status_key', $status);
        }
    }

    private function applyDispatchStatusFilter(object $query, Request $request): void
    {
        $status = $this->statusKey((string) $request->query('status', 'all'));

        if ($status === 'all') {
            return;
        }

        if ($status === 'active') {
            $query->whereNull('de.ended_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('de.ended_at');
        } elseif ($status === 'critical') {
            $query->whereIn('ra.priority_level', ['high', 'critical', 'urgent']);
        } elseif ($status === 'completed') {
            $query->where('ra.status', 'completed');
        } else {
            $query->where('ra.status', $status);
        }
    }

    private function applyResourceStatusFilter(object $query, Request $request): void
    {
        $status = $this->statusKey((string) $request->query('status', 'all'));

        if ($status === 'all') {
            return;
        }

        if ($status === 'active') {
            $query->whereNull('de.ended_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('de.ended_at');
        } elseif ($status === 'critical') {
            $query->whereIn('ul.urgency_key', ['high', 'critical', 'urgent']);
        } else {
            $query->where('rr.validation_status', $status);
        }
    }

    private function applySituationStatusFilter(object $query, Request $request): void
    {
        $status = $this->statusKey((string) $request->query('status', 'all'));

        if ($status === 'all') {
            return;
        }

        if ($status === 'active') {
            $query->whereNull('de.ended_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('de.ended_at');
        } elseif ($status === 'generated') {
            $query->whereIn('sr.report_status', ['generated', 'reviewed', 'archived']);
        } else {
            $query->where('sr.report_status', $status);
        }
    }

    private function latestWeather(string $eventId): array
    {
        $weather = DB::table('weather_logs')
            ->where('disaster_id', $eventId)
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $weather) {
            return [
                'condition' => null,
                'meta' => null,
            ];
        }

        $parts = [];

        if ($weather->wind_speed !== null) {
            $parts[] = 'Wind '.$weather->wind_speed.' km/h '.($weather->wind_direction ?: '');
        }

        if ($weather->rainfall_mm !== null) {
            $parts[] = 'Rainfall '.$weather->rainfall_mm.' mm';
        }

        if ($weather->temperature !== null) {
            $parts[] = 'Temp '.$weather->temperature.' C';
        }

        return [
            'condition' => $weather->condition_name ?: $weather->advisory_title,
            'meta' => trim(implode(' - ', $parts)) ?: ($weather->source_name ?: 'Weather snapshot saved'),
        ];
    }

    private function eventPurokScope(string $eventId): string
    {
        $puroks = DB::table('household_disasters as hd')
            ->join('households as h', 'h.household_id', '=', 'hd.household_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->where('hd.disaster_id', $eventId)
            ->whereNotNull('a.purok_sitio')
            ->where('a.purok_sitio', '<>', '')
            ->select('a.purok_sitio')
            ->distinct()
            ->orderBy('a.purok_sitio')
            ->pluck('a.purok_sitio')
            ->values()
            ->all();

        if (empty($puroks)) {
            return 'Barangay scope';
        }

        return implode(', ', $puroks);
    }

    private function eventOptions(): array
    {
        return DB::table('disaster_events')
            ->whereNull('deleted_at')
            ->orderByDesc('started_at')
            ->limit(80)
            ->get(['event_id', 'name', 'started_at', 'ended_at'])
            ->map(fn (object $event): array => [
                'event_id' => $event->event_id,
                'name' => $event->name,
                'label' => $event->name.' - '.$this->formatDate($event->started_at),
                'status' => $event->ended_at ? 'Closed' : 'Active',
            ])
            ->values()
            ->all();
    }

    private function purokOptions(): array
    {
        $puroks = DB::table('addresses')
            ->whereNotNull('purok_sitio')
            ->where('purok_sitio', '<>', '')
            ->select('purok_sitio')
            ->distinct()
            ->orderBy('purok_sitio')
            ->pluck('purok_sitio')
            ->values()
            ->all();

        return empty($puroks) ? ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5'] : $puroks;
    }

    private function statusOptions(): array
    {
        return [
            ['key' => 'active', 'label' => 'Active'],
            ['key' => 'critical', 'label' => 'Critical'],
            ['key' => 'closed', 'label' => 'Closed'],
            ['key' => 'generated', 'label' => 'Generated'],
            ['key' => 'verified', 'label' => 'Verified'],
            ['key' => 'forwarded', 'label' => 'Forwarded'],
            ['key' => 'returned', 'label' => 'Returned'],
            ['key' => 'completed', 'label' => 'Completed'],
        ];
    }

    private function csvHeaders(string $category): array
    {
        return match ($category) {
            'household-status-logs' => [
                'datetime' => 'Date / time',
                'event' => 'Event',
                'reference' => 'Reference',
                'household' => 'Household',
                'purok' => 'Purok',
                'status' => 'Status',
                'source' => 'Source',
            ],
            'dispatch-logs' => [
                'datetime' => 'Date / time',
                'event' => 'Event',
                'reference' => 'Reference',
                'team_route' => 'Team / route',
                'purok' => 'Purok',
                'status' => 'Status',
                'outcome' => 'Outcome',
            ],
            'resource-requests' => [
                'datetime' => 'Date / time',
                'event' => 'Event',
                'reference' => 'Reference',
                'request' => 'Request',
                'purok_site' => 'Purok / site',
                'status' => 'Status',
                'handoff' => 'Handoff',
            ],
            'situation-reports' => [
                'datetime' => 'Generated at',
                'event' => 'Event',
                'reference' => 'SitRep No.',
                'period' => 'Reporting period',
                'submitted_by' => 'Submitted by',
                'population' => 'Population',
                'status' => 'Status',
            ],
            default => [
                'event' => 'Event',
                'reference' => 'Reference',
                'type_weather' => 'Disaster / weather',
                'period' => 'Date declared / finished',
                'broadcasts' => 'Broadcast logs',
                'scope' => 'Household scope',
                'status' => 'Status',
            ],
        };
    }

    private function details(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $value, string $label): array => [
                'label' => $label,
                'value' => $value ?: 'Not recorded',
            ])
            ->values()
            ->all();
    }

    private function dispatchOutcomeText(array $outcome, ?string $rawNotes): string
    {
        if (! empty($outcome)) {
            $safe = (int) ($outcome['safe_count'] ?? 0);
            $evacuated = (int) ($outcome['evacuated_count'] ?? 0);
            $unsafe = (int) ($outcome['unsafe_count'] ?? 0);
            $injured = (int) ($outcome['injured_count'] ?? 0);
            $missing = (int) ($outcome['missing_count'] ?? 0);

            return $safe.' safe - '.$evacuated.' evacuated - '.$unsafe.' unsafe - '.$injured.' injured - '.$missing.' missing';
        }

        return $rawNotes ?: 'No outcome logged yet';
    }

    private function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function perPage(Request $request, int $fallback): int
    {
        return min(1000, max(10, (int) $request->query('per_page', $fallback)));
    }

    private function statusKey(?string $status): string
    {
        $key = strtolower(trim((string) $status));
        $key = str_replace([' ', '-'], '_', $key);

        return match ($key) {
            '', 'all_statuses' => 'all',
            'on_scene', 'onscene' => 'on_scene',
            'needs_validation', 'pending', 'needs_validation_' => 'needs_validation',
            'validated', 'approved' => 'verified',
            'filed' => 'archived',
            default => $key,
        };
    }

    private function statusTone(?string $status): string
    {
        $key = $this->statusKey($status);

        return match ($key) {
            'safe', 'evacuated', 'verified', 'forwarded', 'fulfilled', 'completed', 'on_scene', 'returned_home', 'generated' => 'green',
            'active', 'dispatched', 'en_route', 'needs_validation', 'pending', 'assigned' => 'amber',
            'unsafe', 'injured', 'missing', 'not_evacuated', 'displaced', 'returned', 'cancelled', 'failed', 'critical' => 'red',
            'reviewed', 'archived' => 'purple',
            default => 'gray',
        };
    }

    private function eventStatusKey(?string $endedAt): string
    {
        return $endedAt ? 'closed' : 'active';
    }

    private function isCriticalSeverity(?string $key, ?string $label): bool
    {
        $text = strtolower(trim((string) $key.' '.(string) $label));

        return str_contains($text, 'high')
            || str_contains($text, 'critical')
            || str_contains($text, 'severe');
    }

    private function purokFromText(?string $text): string
    {
        $value = trim((string) $text);

        if ($value === '') {
            return 'Unassigned';
        }

        if (preg_match('/purok\s*[0-9a-z-]+/i', $value, $matches)) {
            return $matches[0];
        }

        return $value;
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    private function formatDateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : 'Not recorded';
    }

    private function formatDate(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('M Y') : 'No date';
    }

    private function formatTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : 'No time';
    }
}
