<?php

namespace App\Queries;

use App\Models\ResourceRequest;
use App\Presenters\ArchiveEventPresenter;
use App\Presenters\ArchivePresenter;
use App\Http\Requests\ListRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveQuery
{
    private const CATEGORIES = ['disaster-events' => 'Disaster Event', 'household-status-logs' => 'Household Status Logs', 'dispatch-logs' => 'Rescue Dispatch Logs', 'radio-communication-logs' => 'Radio Communication Logs', 'resource-requests' => 'Resources & Requests', 'situation-reports' => 'Situation Reporting'];

    public function __construct(private ArchivePresenter $presenter, private ArchiveEventPresenter $eventPresenter) {}

    public function categoryLabel(string $category): string { return self::CATEGORIES[$category] ?? self::CATEGORIES['disaster-events']; }

    public function categoryRows(string $category, Request $request, int $perPage, bool $asQuery = false): mixed
    {
        return match ($category) {
            'household-status-logs' => $this->householdStatusRows($request, $perPage, $asQuery),
            'dispatch-logs' => $this->dispatchRows($request, $perPage, $asQuery),
            'radio-communication-logs' => $this->radioCommunicationRows($request, $perPage, $asQuery),
            'resource-requests' => $this->resourceRequestRows($request, $perPage, $asQuery),
            'situation-reports' => $this->situationReportRows($request, $perPage, $asQuery),
            default => $this->disasterEventRows($request, $perPage, $asQuery),
        };
    }

    public function exportRows(string $category, Request $request): array
    {
        $query = $this->categoryRows($category, $request, 1000, true);
        $query->select($this->exportSelectColumns($category));
        $column = match ($category) {
            'household-status-logs' => 'hsl.status_log_id',
            'dispatch-logs' => 'ra.assignment_id',
            'radio-communication-logs' => 'rcl.communication_id',
            'resource-requests' => 'rr.request_id',
            'situation-reports' => 'sr.sit_rep_id',
            default => 'de.event_id',
        };
        if ($request->filled('ids')) $query->whereIn($column, $request->query('ids'));
        $dateColumn = match ($category) {
            'household-status-logs' => 'hsl.submitted_at', 'dispatch-logs' => 'ra.assigned_at',
            'radio-communication-logs' => 'rcl.timestamp', 'resource-requests' => 'rr.created_at',
            'situation-reports' => 'sr.generated_at', default => 'de.started_at',
        };
        if ($request->filled('start_date')) $query->whereDate($dateColumn, '>=', $request->query('start_date'));
        if ($request->filled('end_date')) $query->whereDate($dateColumn, '<=', $request->query('end_date'));
        $alias = substr($column, strpos($column, '.') + 1);
        $total = (clone $query)->count();

        // Read matching records in bounded chunks without truncating the export.
        $source = $query->lazyByIdDesc(500, $column, $alias);
        $rows = $category === 'disaster-events'
            ? $source->chunk(500)->flatMap(fn ($chunk) => $this->eventPresenter->presentBatch($chunk))
            : $source->map(function (object $row) use ($category): array {
                return match ($category) {
                    'household-status-logs' => $this->presenter->formatHouseholdStatus($row),
                    'dispatch-logs' => $this->presenter->formatDispatch($row),
                    'radio-communication-logs' => $this->presenter->formatRadioCommunication($row),
                    'resource-requests' => $this->presenter->formatResourceRequest($row),
                    'situation-reports' => $this->presenter->formatSituationReport($row),
                };
            });

        return [$total, $rows];
    }

    private function exportSelectColumns(string $category): array
    {
        return match ($category) {
            'household-status-logs' => [
                'hsl.status_log_id', 'hsl.disaster_id', 'hsl.location_label', 'hsl.location_accuracy_m',
                'hsl.source', 'hsl.notes', 'hsl.submitted_at', 'hsl.battery_level', 'hsl.latitude', 'hsl.longitude',
                'de.name as event_name', 'de.ended_at as event_ended_at', 'h.household_code', 'h.household_name',
                'a.purok_sitio', 'hs.status_key', 'hs.status_label', 'r.full_name as responder_name',
            ],
            'dispatch-logs' => [
                'ra.assignment_id', 'ra.disaster_id', 'ra.assigned_area', 'ra.status', 'ra.route_notes',
                'ra.outcome_notes', 'ra.assigned_at', 'ra.dispatch_notes', 'ra.assignment_code', 'ra.priority_level',
                'de.name as event_name', 'de.ended_at as event_ended_at', 'rt.team_name', 'r.full_name as responder_name',
            ],
            'radio-communication-logs' => [
                'rcl.communication_id', 'rcl.disaster_id', 'rcl.timestamp', 'rcl.message', 'rcl.team_name',
                'de.name as event_name', 'r.full_name as responder_name', 'r.responder_code', 'rt.team_code',
            ],
            'resource-requests' => [
                'rr.request_id', 'rr.source_reference', 'rr.validation_status', 'rr.quantity', 'rr.unit',
                'rr.item_name', 'rr.resource_type', 'rr.evacuation_center_id', 'rr.description', 'rr.created_at',
                'rr.tracking_reference', 'rr.released_for_tracking_at', 'rr.validation_notes', 'rr.requested_by',
                'rr.request_source', 'de.name as event_name', 'de.ended_at as event_ended_at',
                'ec.name as evacuation_center_name', 'ec.osm_address as evacuation_center_address',
            ],
            'situation-reports' => [
                'sr.sit_rep_id', 'sr.disaster_id', 'sr.summary', 'sr.report_number', 'sr.generated_at',
                'sr.escalated_to', 'sr.report_status', 'de.name as event_name', 'de.ended_at as event_ended_at',
                'de.started_at as event_started_at', 'dt.type_name',
            ],
            default => ['de.event_id', 'de.name', 'de.started_at', 'de.ended_at', 'dt.type_name', 'sl.severity_key', 'sl.severity_label'],
        };
    }

    public function options(): array { return ['events' => $this->eventOptions(), 'puroks' => $this->purokOptions(), 'statuses' => $this->statusOptions()]; }
    public function perPage(Request $request, int $fallback): int { return ListRequest::clampPerPage($request->query('per_page'), $fallback); }

    private function disasterEventRows(Request $request, int $perPage, bool $asQuery = false): mixed
    {
        $query = DB::table('disaster_events as de')->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')->whereNull('de.deleted_at')->select(['de.event_id', 'de.name', 'de.started_at', 'de.ended_at', 'dt.type_name', 'sl.severity_key', 'sl.severity_label']);
        $this->applySearch($query, $request, ['de.event_id', 'de.name', 'dt.type_name', 'sl.severity_label']); $this->applyEventFilter($query, $request, 'de.event_id'); $this->applyDisasterStatusFilter($query, $request); $this->applyDisasterPurokFilter($query, $request); if (!$request->filled('ids')) $this->excludeSavedGroupRecords($query, 'disaster-events', 'de.event_id');
        if ($asQuery) return $query;
        $paginator = $query->orderByDesc('de.started_at')->paginate($this->perPage($request, $perPage));
        return [$paginator, collect($paginator->items())->map(fn (object $row): array => $this->eventPresenter->present($row))->values()->all()];
    }

    private function householdStatusRows(Request $request, int $perPage, bool $asQuery = false): mixed
    {
        $query = DB::table('household_status_logs as hsl')->leftJoin('disaster_events as de', 'de.event_id', '=', 'hsl.disaster_id')->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id')->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')->leftJoin('responders as r', 'r.responder_id', '=', 'hsl.responder_id')->select(['hsl.*', 'de.name as event_name', 'de.started_at as event_started_at', 'de.ended_at as event_ended_at', 'h.household_code', 'h.household_name', 'a.purok_sitio', 'hs.status_key', 'hs.status_label', 'r.full_name as responder_name']);
        $this->applySearch($query, $request, ['hsl.status_log_id', 'de.name', 'h.household_code', 'h.household_name', 'hsl.location_label', 'hsl.notes', 'hsl.source', 'hs.status_label']); $this->applyEventFilter($query, $request, 'hsl.disaster_id'); $this->applyPurokFilter($query, $request, 'a.purok_sitio'); $this->applyHouseholdStatusFilter($query, $request); if (!$request->filled('ids')) $this->excludeSavedGroupRecords($query, 'household-status-logs', 'hsl.status_log_id');
        if ($asQuery) return $query;
        $paginator = $query->orderByDesc('hsl.submitted_at')->orderByDesc('hsl.status_log_id')->paginate($this->perPage($request, $perPage));
        return [$paginator, collect($paginator->items())->map(fn (object $row): array => $this->presenter->formatHouseholdStatus($row))->values()->all()];
    }

    private function dispatchRows(Request $request, int $perPage, bool $asQuery = false): mixed
    {
        $query = DB::table('responder_assignments as ra')->leftJoin('disaster_events as de', 'de.event_id', '=', 'ra.disaster_id')->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')->select(['ra.*', 'de.name as event_name', 'de.started_at as event_started_at', 'de.ended_at as event_ended_at', 'rt.team_name', 'rt.team_code', 'r.full_name as responder_name']);
        $this->applySearch($query, $request, ['ra.assignment_code', 'de.name', 'rt.team_name', 'rt.team_code', 'r.full_name', 'ra.assigned_area', 'ra.status', 'ra.dispatch_notes', 'ra.outcome_notes']); $this->applyEventFilter($query, $request, 'ra.disaster_id'); $this->applyPurokFilter($query, $request, 'ra.assigned_area'); $this->applyDispatchStatusFilter($query, $request); if (!$request->filled('ids')) $this->excludeSavedGroupRecords($query, 'dispatch-logs', 'ra.assignment_id');
        if ($asQuery) return $query;
        $paginator = $query->orderByDesc('ra.assigned_at')->orderByDesc('ra.assignment_id')->paginate($this->perPage($request, $perPage));
        return [$paginator, collect($paginator->items())->map(fn (object $row): array => $this->presenter->formatDispatch($row))->values()->all()];
    }

    private function resourceRequestRows(Request $request, int $perPage, bool $asQuery = false): mixed
    {
        $query = ResourceRequest::query()->from('resource_requests as rr')->leftJoin('disaster_events as de', 'de.event_id', '=', 'rr.source_reference')->leftJoin('evacuation_centers as ec', 'ec.evacuation_center_id', '=', 'rr.evacuation_center_id')->leftJoin('urgency_levels as ul', 'ul.urgency_id', '=', 'rr.urgency_id')->select(['rr.*', 'de.name as event_name', 'de.started_at as event_started_at', 'de.ended_at as event_ended_at', 'ec.name as evacuation_center_name', 'ec.osm_address as evacuation_center_address', 'ec.current_event_id as evacuation_event_id', 'ul.urgency_key', 'ul.urgency_label']);
        $this->applySearch($query, $request, ['rr.request_id', 'rr.request_source', 'rr.source_reference', 'rr.requested_by', 'rr.resource_type', 'rr.item_name', 'rr.description', 'rr.validation_status', 'rr.tracking_reference', 'ec.name', 'ec.osm_address']); $this->applyEventFilter($query, $request, 'rr.source_reference'); $this->applyResourcePurokFilter($query, $request); $this->applyResourceStatusFilter($query, $request); if (!$request->filled('ids')) $this->excludeSavedGroupRecords($query, 'resource-requests', 'rr.request_id');
        if ($asQuery) return $query;
        $paginator = $query->orderByDesc('rr.created_at')->paginate($this->perPage($request, $perPage));
        return [$paginator, collect($paginator->items())->map(fn (object $row): array => $this->presenter->formatResourceRequest($row))->values()->all()];
    }

    private function radioCommunicationRows(Request $request, int $perPage, bool $asQuery = false): mixed
    {
        $query = DB::table('responder_communication_logs as rcl')->leftJoin('disaster_events as de', 'de.event_id', '=', 'rcl.disaster_id')->leftJoin('responders as r', 'r.responder_id', '=', 'rcl.responder_id')->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'rcl.team_id')->select(['rcl.*', 'de.name as event_name', 'de.started_at as event_started_at', 'de.ended_at as event_ended_at', 'r.full_name as responder_name', 'r.responder_code', 'rt.team_code', 'rt.team_type']);
        $this->applySearch($query, $request, ['rcl.communication_id', 'rcl.team_name', 'rcl.message', 'de.name', 'r.full_name', 'r.responder_code', 'rt.team_code']); $this->applyEventFilter($query, $request, 'rcl.disaster_id'); $this->applyRadioStatusFilter($query, $request); if (!$request->filled('ids')) $this->excludeSavedGroupRecords($query, 'radio-communication-logs', 'rcl.communication_id');
        if ($asQuery) return $query;
        $paginator = $query->orderByDesc('rcl.timestamp')->orderByDesc('rcl.communication_id')->paginate($this->perPage($request, $perPage));
        return [$paginator, collect($paginator->items())->map(fn (object $row): array => $this->presenter->formatRadioCommunication($row))->values()->all()];
    }

    private function situationReportRows(Request $request, int $perPage, bool $asQuery = false): mixed
    {
        $query = DB::table('situation_reports as sr')->leftJoin('disaster_events as de', 'de.event_id', '=', 'sr.disaster_id')->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')->select(['sr.*', 'de.name as event_name', 'de.started_at as event_started_at', 'de.ended_at as event_ended_at', 'dt.type_name']);
        $this->applySearch($query, $request, ['sr.sit_rep_id', 'sr.report_number', 'sr.summary', 'sr.report_status', 'sr.escalated_to', 'de.name', 'dt.type_name']); $this->applyEventFilter($query, $request, 'sr.disaster_id'); $this->applySituationPurokFilter($query, $request); $this->applySituationStatusFilter($query, $request); if (!$request->filled('ids')) $this->excludeSavedGroupRecords($query, 'situation-reports', 'sr.sit_rep_id');
        if ($asQuery) return $query;
        $paginator = $query->orderByDesc('sr.generated_at')->orderByDesc('sr.sit_rep_id')->paginate($this->perPage($request, $perPage));
        return [$paginator, collect($paginator->items())->map(fn (object $row): array => $this->presenter->formatSituationReport($row))->values()->all()];
    }

    private function applySearch(object $query, Request $request, array $columns): void
    {
        $search = trim((string) $request->query('search', '')); if ($search === '') return;
        $query->where(function ($inner) use ($columns, $search): void { foreach ($columns as $i => $column) $inner->{$i === 0 ? 'where' : 'orWhere'}($column, 'like', "%{$search}%"); });
    }
    private function applyEventFilter(object $query, Request $request, string $column): void { $id = trim((string) $request->query('event_id', 'all')); if ($id !== '' && $id !== 'all') $query->where($column, $id); }
    private function applyPurokFilter(object $query, Request $request, string $column): void { $purok = trim((string) $request->query('purok', 'all')); if ($purok !== '' && $purok !== 'all') $query->where($column, 'like', "%{$purok}%"); }
    private function applyDisasterPurokFilter(object $query, Request $request): void
    {
        $purok = trim((string) $request->query('purok', 'all')); if ($purok === '' || $purok === 'all') return;
        $query->whereExists(function ($inner) use ($purok): void { $inner->selectRaw('1')->from('household_disasters as hd')->join('households as h', 'h.household_id', '=', 'hd.household_id')->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')->whereColumn('hd.disaster_id', 'de.event_id')->where('a.purok_sitio', 'like', "%{$purok}%"); });
    }
    private function applyResourcePurokFilter(object $query, Request $request): void
    {
        $purok = trim((string) $request->query('purok', 'all')); if ($purok === '' || $purok === 'all') return;
        $query->where(function ($inner) use ($purok): void { $inner->where('ec.name', 'like', "%{$purok}%")->orWhere('ec.osm_address', 'like', "%{$purok}%")->orWhere('rr.description', 'like', "%{$purok}%")->orWhere('rr.evacuation_center_id', 'like', "%{$purok}%"); });
    }
    private function applySituationPurokFilter(object $query, Request $request): void { $p = trim((string) $request->query('purok', 'all')); if ($p !== '' && $p !== 'all') $query->where('sr.summary', 'like', "%{$p}%"); }
    private function applyDisasterStatusFilter(object $query, Request $request): void
    {
        $s = $this->presenter->statusKey((string) $request->query('status', 'all')); if ($s === 'all') return;
        if ($s === 'active') $query->whereNull('de.ended_at'); elseif ($s === 'closed') $query->whereNotNull('de.ended_at'); elseif ($s === 'critical') $query->where(function ($q): void { $q->whereIn('sl.severity_key', ['high', 'critical', 'severe'])->orWhere('sl.severity_label', 'like', '%High%')->orWhere('sl.severity_label', 'like', '%Critical%'); });
    }
    private function applyHouseholdStatusFilter(object $query, Request $request): void
    {
        $s = $this->presenter->statusKey((string) $request->query('status', 'all')); if ($s === 'all') return;
        if ($s === 'active') $query->whereNull('de.ended_at'); elseif ($s === 'closed') $query->whereNotNull('de.ended_at'); elseif ($s === 'critical') $query->whereIn('hs.status_key', ['unsafe', 'injured', 'missing', 'not_evacuated', 'displaced']); else $query->where('hs.status_key', $s);
    }
    private function applyDispatchStatusFilter(object $query, Request $request): void
    {
        $s = $this->presenter->statusKey((string) $request->query('status', 'all')); if ($s === 'all') return;
        if ($s === 'active') $query->whereNull('de.ended_at'); elseif ($s === 'closed') $query->whereNotNull('de.ended_at'); elseif ($s === 'critical') $query->whereIn('ra.priority_level', ['high', 'critical', 'urgent']); elseif ($s === 'completed') $query->where('ra.status', 'completed'); else $query->where('ra.status', $s);
    }
    private function applyResourceStatusFilter(object $query, Request $request): void
    {
        $s = $this->presenter->statusKey((string) $request->query('status', 'all')); if ($s === 'all') return;
        if ($s === 'active') $query->whereNull('de.ended_at'); elseif ($s === 'closed') $query->whereNotNull('de.ended_at'); elseif ($s === 'critical') $query->whereIn('ul.urgency_key', ['high', 'critical', 'urgent']); else $query->where('rr.validation_status', $s);
    }
    private function applySituationStatusFilter(object $query, Request $request): void
    {
        $s = $this->presenter->statusKey((string) $request->query('status', 'all')); if ($s === 'all') return;
        if ($s === 'active') $query->whereNull('de.ended_at'); elseif ($s === 'closed') $query->whereNotNull('de.ended_at'); elseif ($s === 'generated') $query->whereIn('sr.report_status', ['generated', 'reviewed', 'archived']); else $query->where('sr.report_status', $s);
    }
    private function applyRadioStatusFilter(object $query, Request $request): void { $s = $this->presenter->statusKey($request->query('status', 'all')); if ($s !== 'all') $query->where('rcl.message', 'like', '%"type":"'.$s.'"%'); }
    private function eventOptions(): array { return DB::table('disaster_events')->whereNull('deleted_at')->orderByDesc('started_at')->limit(80)->get(['event_id', 'name', 'started_at', 'ended_at'])->map(fn (object $e): array => ['event_id' => $e->event_id, 'name' => $e->name, 'label' => $e->name.' - '.$this->presenter->formatDate($e->started_at), 'status' => $e->ended_at ? 'Closed' : 'Active'])->values()->all(); }
    private function purokOptions(): array
    {
        $p = DB::table('addresses')->whereNotNull('purok_sitio')->where('purok_sitio', '<>', '')->select('purok_sitio')->distinct()->orderBy('purok_sitio')->pluck('purok_sitio')->values()->all();
        return $p;
    }
    private function statusOptions(): array { return [['key'=>'active','label'=>'Active'],['key'=>'critical','label'=>'Critical'],['key'=>'closed','label'=>'Closed'],['key'=>'generated','label'=>'Generated'],['key'=>'verified','label'=>'Verified'],['key'=>'forwarded','label'=>'Forwarded'],['key'=>'returned','label'=>'Returned'],['key'=>'completed','label'=>'Completed']]; }
    private function excludeSavedGroupRecords(object $query, string $category, string $column): void { $ids=$this->savedGroupRecordIds($category); if ($ids) $query->whereNotIn($column,$ids); }
    private function savedGroupRecordIds(string $category): array
    {
        return app(\App\Services\Archive\ArchiveGroupManager::class)->savedRecordIds($category);
    }
}


