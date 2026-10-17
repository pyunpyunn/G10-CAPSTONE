<?php

namespace App\Queries;

use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\HouseholdDisaster;
use App\Models\ResponderAssignment;
use App\Models\ResourceRequest;
use App\Models\SituationReport;
use App\Models\WeatherLog;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class SituationReportQuery
{
    public function sitioOptions()
    {
        $barangayId = app(\App\Services\Shared\BarangayProfileService::class)->current()['barangay_id'] ?? null;
        return DB::table('sitios')->where('barangay_id', $barangayId)
            ->when($barangayId === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('sitio_name')->orderBy('sitio_id')->get(['sitio_id', 'sitio_name']);
    }

    public function eventOptions()
    {
        $query = DisasterEvent::query()->from('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->orderByDesc('de.started_at')->limit(50)
            ->select(['de.event_id', 'de.name', 'de.started_at', 'de.ended_at', 'dt.type_name', 'sl.severity_label', 'sl.severity_key']);
        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }
        return $query->get();
    }

    public function findEvent(string $eventId): ?object
    {
        $query = DisasterEvent::query()->from('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->where('de.event_id', $eventId)
            ->select(['de.event_id', 'de.name', 'de.started_at', 'de.ended_at', 'dt.type_name', 'sl.severity_label', 'sl.severity_key']);
        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }
        return $query->first();
    }

    public function findReport(int $sitRepId): ?object
    {
        return DB::table('situation_reports')->leftJoin('disaster_events as de', 'de.event_id', '=', 'situation_reports.disaster_id')
            ->where('situation_reports.sit_rep_id', $sitRepId)
            ->select('situation_reports.*', 'de.name as event_name')->first();
    }

    public function savedReports()
    {
        return SituationReport::query()->from('situation_reports as sr')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'sr.disaster_id')
            ->orderByDesc('sr.generated_at')->limit(20)->select('sr.*', 'de.name as event_name')->get();
    }

    public function summarySources(string $eventId, ?string $sitioId = null): array
    {
        $households = Household::query()->whereIn('households.household_id', HouseholdEligibilityQuery::ids());
        if ($sitioId !== null) {
            $households->whereIn('households.address_id', DB::table('addresses as scoped_address')
                ->join('puroks as scoped_purok', 'scoped_purok.purok_id', '=', 'scoped_address.purok_id')
                ->where('scoped_purok.sitio_id', $sitioId)->select('scoped_address.address_id'));
        }
        if (Schema::hasColumn('households', 'deleted_at')) {
            $households->whereNull('deleted_at');
        }
        // The report and household pages share the latest valid event report and registered area IDs.
        $base = Household::query()->from('households as h')
            ->whereIn('h.household_id', HouseholdEligibilityQuery::ids())
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->leftJoinSub(HouseholdEventStatusQuery::latestReports($eventId), 'latest_report', 'latest_report.household_id', '=', 'h.household_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'latest_report.status_id');
        if ($sitioId !== null) $base->where('s.sitio_id', $sitioId);
        $counts = (clone $base)->select('hs.status_key', DB::raw('COUNT(DISTINCT h.household_id) as total'))
            ->groupBy('hs.status_key')->pluck('total', 'status_key');
        $reported = (clone $base)->whereNotNull('hs.status_key')->distinct()->count('h.household_id');
        $safeKeys = "'".implode("','", HouseholdEventStatusQuery::SAFE_KEYS)."'";
        $unsafeKeys = "'".implode("','", HouseholdEventStatusQuery::UNSAFE_KEYS)."'";
        $members = Schema::hasColumn('households', 'member_count') ? 'h.member_count' : 'NULL';
        $puroks = (clone $base)->select('p.purok_id', 's.sitio_id', 's.sitio_name', 'p.purok_name as purok')
            ->selectRaw('COUNT(DISTINCT h.household_id) as total')
            ->selectRaw("CASE WHEN COUNT({$members}) = COUNT(*) THEN SUM({$members}) ELSE NULL END as persons")
            ->selectRaw("CASE WHEN COUNT(CASE WHEN hs.status_key IN ({$unsafeKeys}, 'evacuated', 'relocated') THEN 1 END) = COUNT(CASE WHEN hs.status_key IN ({$unsafeKeys}, 'evacuated', 'relocated') THEN {$members} END) THEN COALESCE(SUM(CASE WHEN hs.status_key IN ({$unsafeKeys}, 'evacuated', 'relocated') THEN {$members} ELSE 0 END), 0) ELSE NULL END as affected_persons")
            ->selectRaw("COUNT(DISTINCT CASE WHEN hs.status_key IN ({$safeKeys}) THEN h.household_id END) as safe")
            ->selectRaw("COUNT(DISTINCT CASE WHEN hs.status_key IN ('evacuated', 'relocated') THEN h.household_id END) as evacuated")
            ->selectRaw("COUNT(DISTINCT CASE WHEN hs.status_key IN ({$unsafeKeys}) THEN h.household_id END) as unsafe")
            ->selectRaw('COUNT(DISTINCT CASE WHEN hs.status_key IS NOT NULL THEN h.household_id END) as reported')
            ->groupBy('p.purok_id', 's.sitio_id', 's.sitio_name', 'p.purok_name')->orderBy('s.sitio_name')->orderBy('p.purok_name')->get();

        $endedAt = Schema::hasTable('disaster_events') ? DB::table('disaster_events')->where('event_id', $eventId)->value('ended_at') : null;
        $weather = WeatherLog::query()
            ->when($endedAt, fn ($query) => $query->where('observed_at', '<=', $endedAt))
            ->orderByDesc('observed_at')->orderByDesc('created_at')->orderByDesc('weather_log_id')->first();
        $assignments = ResponderAssignment::query()->from('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->where('ra.disaster_id', $eventId)
            ->when($sitioId !== null, fn ($q) => $this->scopeAssignments($q, 'ra', $sitioId))
            ->orderByDesc('ra.assigned_at')->orderByDesc('ra.assignment_id')
            ->select(['ra.assignment_id', 'ra.route_notes', 'ra.outcome_notes', 'ra.assigned_at', 'ra.assigned_area', 'ra.status', 'rt.team_name', 'r.full_name as responder_name'])->get();
        $assignmentOutcomes = ResponderAssignment::query()->where('disaster_id', $eventId)
            ->when($sitioId !== null, fn ($q) => $this->scopeAssignments($q, 'responder_assignments', $sitioId))
            ->select(['assignment_id', 'status', 'outcome_notes'])->lazyById(500, 'assignment_id');
        $broadcasts = DisasterBroadcast::query()->where('disaster_id', $eventId)
            ->when($sitioId !== null, function ($q) use ($sitioId): void {
                $q->where(function ($area) use ($sitioId): void {
                    if (Schema::hasColumn('disaster_broadcasts', 'target_purok_id')) {
                        $area->orWhereIn('target_purok_id', DB::table('puroks')->where('sitio_id', $sitioId)->select('purok_id'));
                    }
                    if (Schema::hasColumn('disaster_broadcasts', 'target_area_id') && Schema::hasTable('affected_areas')) {
                        $area->orWhereIn('target_area_id', $this->sitioAreaIds($sitioId));
                    }
                    $area->orWhereRaw('1 = 0');
                });
            })->orderBy('sent_at')->get();
        $requests = ResourceRequest::query()->where('source_reference', $eventId)
            // Resource requests currently have no registered sitio ownership.
            ->when($sitioId !== null, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('created_at')->get();

        return [
            'household_total' => $households->count(), 'household_counts' => $counts,
            'household_reported' => $reported, 'purok_rows' => $puroks,
            'weather' => $weather, 'assignments' => $assignments,
            'assignment_outcomes' => $assignmentOutcomes,
            'broadcasts' => $broadcasts, 'requests' => $requests,
        ];
    }

    private function sitioAreaIds(string $sitioId)
    {
        return DB::table('affected_areas')->where(fn ($q) => $q->where('sitio_id', $sitioId)
            ->orWhereIn('purok_id', DB::table('puroks')->where('sitio_id', $sitioId)->select('purok_id')))->select('affected_area_id');
    }

    private function scopeAssignments($query, string $alias, string $sitioId): void
    {
        $query->where(function ($area) use ($alias, $sitioId): void {
            if (Schema::hasColumn('responder_assignments', 'household_id')) {
                $area->orWhereIn($alias.'.household_id', DB::table('households as scoped_household')
                    ->join('addresses as scoped_address', 'scoped_address.address_id', '=', 'scoped_household.address_id')
                    ->join('puroks as scoped_purok', 'scoped_purok.purok_id', '=', 'scoped_address.purok_id')
                    ->where('scoped_purok.sitio_id', $sitioId)->select('scoped_household.household_id'));
            }
            if (Schema::hasColumn('responder_assignments', 'affected_area_id') && Schema::hasTable('affected_areas')) {
                $area->orWhereIn($alias.'.affected_area_id', $this->sitioAreaIds($sitioId));
            }
            $area->orWhereRaw('1 = 0');
        });
    }

    public function nextReportNumber(): string
    {
        $prefix = 'SITREP-'.now()->format('Y').'-';
        $count = SituationReport::query()->where('report_number', 'like', $prefix.'%')->count() + 1;
        return $prefix.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    public function nextId(string $table, string $column): int
    {
        return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table, $column);
    }
}


