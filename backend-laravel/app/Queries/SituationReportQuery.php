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

    public function summarySources(string $eventId): array
    {
        $households = Household::query();
        if (Schema::hasColumn('households', 'deleted_at')) {
            $households->whereNull('deleted_at');
        }
        $counts = HouseholdDisaster::query()->from('household_disasters as hd')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('hd.disaster_id', $eventId)->select('hs.status_key', DB::raw('COUNT(DISTINCT hd.household_id) as total'))
            ->groupBy('hs.status_key')->pluck('total', 'status_key');
        $reported = HouseholdDisaster::query()->where('disaster_id', $eventId)->whereNotNull('current_status_id')->distinct('household_id')->count('household_id');
        $puroks = Household::query()->from('households as h')->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_disasters as hd', function ($join) use ($eventId): void { $join->on('hd.household_id', '=', 'h.household_id')->where('hd.disaster_id', '=', $eventId); })
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
            ->selectRaw("COALESCE(NULLIF(a.purok_sitio, ''), 'Unassigned') as purok")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN hs.status_key IN ('active', 'returned', 'safe') THEN 1 ELSE 0 END) as safe")
            ->selectRaw("SUM(CASE WHEN hs.status_key IN ('evacuated', 'relocated') THEN 1 ELSE 0 END) as evacuated")
            ->selectRaw("SUM(CASE WHEN hs.status_key IN ('not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured') THEN 1 ELSE 0 END) as unsafe")
            ->selectRaw('SUM(CASE WHEN hs.status_key IS NOT NULL THEN 1 ELSE 0 END) as reported')
            ->groupByRaw("COALESCE(NULLIF(a.purok_sitio, ''), 'Unassigned')")
            ->orderBy('purok')->get();

        $weather = WeatherLog::query()->where(fn ($query) => $query->where('disaster_id', $eventId)->orWhereNull('disaster_id'))
            ->orderByRaw('CASE WHEN disaster_id = ? THEN 0 ELSE 1 END', [$eventId])->orderByDesc('observed_at')->orderByDesc('created_at')->first();
        $evacuation = EvacuationCenter::query()->where(fn ($query) => $query->where('current_event_id', $eventId)->orWhereNull('current_event_id'))
            ->when(Schema::hasColumn('evacuation_centers', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))->orderBy('name')->limit(8)->get();
        $assignments = ResponderAssignment::query()->from('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->where('ra.disaster_id', $eventId)->orderByDesc('ra.assigned_at')->orderByDesc('ra.assignment_id')
            ->limit(8)->select(['ra.assignment_id', 'ra.route_notes', 'ra.outcome_notes', 'ra.assigned_at', 'ra.assigned_area', 'ra.status', 'rt.team_name', 'r.full_name as responder_name'])->get();
        $assignmentOutcomes = ResponderAssignment::query()->where('disaster_id', $eventId)
            ->select(['assignment_id', 'status', 'outcome_notes'])->lazyById(500, 'assignment_id');
        $broadcasts = DisasterBroadcast::query()->where('disaster_id', $eventId)->orderBy('sent_at')->limit(4)->get();
        $requests = ResourceRequest::query()->where(fn ($query) => $query->where('source_reference', $eventId)->orWhereNull('source_reference'))
            ->orderByDesc('created_at')->limit(12)->get();

        return [
            'household_total' => $households->count(), 'household_counts' => $counts,
            'household_reported' => $reported, 'purok_rows' => $puroks,
            'weather' => $weather, 'evacuation' => $evacuation, 'assignments' => $assignments,
            'assignment_outcomes' => $assignmentOutcomes,
            'broadcasts' => $broadcasts, 'requests' => $requests,
        ];
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


