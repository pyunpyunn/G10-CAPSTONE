<?php

namespace App\Queries;

use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\HouseholdDisaster;
use App\Models\HouseholdStatusLog;
use App\Models\ResourceRequest;
use App\Models\ResponderAssignment;
use App\Models\RescueTeam;
use App\Models\WeatherLog;
use App\Presenters\DashboardPresenter;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class DashboardQuery
{
    public function latestBroadcast(string $eventId): ?object
    {
        return DB::table('disaster_broadcasts')->where('disaster_id', $eventId)
            ->orderByDesc('sent_at')->first(['broadcast_title', 'scope_type', 'sent_at']);
    }

    public function __construct(private DashboardPresenter $presenter) {}
    public function getActiveEvent(): ?object
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

    public function getHouseholdSummary(?string $eventId): array
    {
        $total = 0;

        if (Schema::hasTable('households')) {
            $total = Household::query()
                ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->count();
        }

        if (! $eventId || ! Schema::hasTable('household_disasters') || ! Schema::hasTable('household_statuses')) {
            return [
                'total' => $total,
                'reported' => 0,
                'reporting_percent' => 0,
                'unchecked' => $total,
                'safe_total' => 0,
                'safe_only' => 0,
                'evacuated' => 0,
                'unsafe' => 0,
                'bars' => $this->presenter->householdBars(0, 0, 0, 0, $total),
            ];
        }

        $counts = HouseholdDisaster::query()
            ->from('household_disasters as hd')
            ->join('households as h', 'h.household_id', '=', 'hd.household_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('hd.disaster_id', $eventId)
            ->whereNull('h.deleted_at')
            ->select('hs.status_key', DB::raw('COUNT(DISTINCT hd.household_id) as total'))
            ->groupBy('hs.status_key')
            ->pluck('total', 'status_key');

        $safeOnly = $this->presenter->sumStatusKeys($counts, ['active', 'returned', 'safe', 'safe_at_home']);
        $evacuated = $this->presenter->sumStatusKeys($counts, ['evacuated', 'relocated']);
        $unsafe = $this->presenter->sumStatusKeys($counts, ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased']);
        $safeTotal = $safeOnly + $evacuated;

        $reportedRows = HouseholdDisaster::query()
            ->from('household_disasters as hd')
            ->join('households as h', 'h.household_id', '=', 'hd.household_id')
            ->where('hd.disaster_id', $eventId)
            ->whereNotNull('hd.current_status_id')
            ->whereNull('h.deleted_at')
            ->distinct()
            ->count('hd.household_id');

        $unknown = (int) ($counts['unknown'] ?? 0);
        $reported = max($reportedRows - $unknown, 0);
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
            'bars' => $this->presenter->householdBars($safeTotal, $safeOnly, $evacuated, $unsafe, $unchecked),
        ];
    }


    public function getDispatchSummary(?string $eventId): array
    {
        if (! $eventId || ! Schema::hasTable('responder_assignments')) {
            return [
                'counts' => $this->presenter->dispatchBars(0, 0, 0),
                'teams' => [],
            ];
        }

        $statusCounts = ResponderAssignment::query()
            ->where('disaster_id', $eventId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $dispatched = $this->presenter->sumStatusKeys($statusCounts, ['dispatched', 'assigned', 'accepted', 'en_route']);
        $onScene = $this->presenter->sumStatusKeys($statusCounts, ['on_scene', 'on-scene', 'arrived']);
        $standby = Schema::hasTable('rescue_teams')
            ? RescueTeam::query()->where('duty_status', 'available')->count()
            : 0;

        $teams = ResponderAssignment::query()
            ->from('responder_assignments as ra')
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
                'status' => $this->presenter->label($team->status ?? 'assigned'),
                'status_key' => $this->presenter->statusKey($team->status ?? 'assigned'),
                'assigned_area' => $team->assigned_area ?? 'No area set',
                'assigned_time' => $this->presenter->formatTime($team->assigned_at),
                'priority_level' => $team->priority_level,
            ])
            ->values();

        return [
            'counts' => $this->presenter->dispatchBars($dispatched, $onScene, $standby),
            'teams' => $teams,
        ];
    }


    public function getWeatherSnapshot(?string $eventId): ?array
    {
        if (! $eventId || ! Schema::hasTable('weather_logs')) {
            return null;
        }

        $weather = WeatherLog::query()
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
            'observed_at' => $this->presenter->formatDateTime($weather->observed_at),
        ];
    }


    public function getRequestSummary(): array
    {
        if (! Schema::hasTable('resource_requests')) {
            return [
                'needs_validation' => 0,
                'validated' => 0,
                'released' => 0,
                'latest' => [],
            ];
        }

        $counts = ResourceRequest::query()
            ->select('validation_status', DB::raw('COUNT(*) as total'))
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        $latest = ResourceRequest::query()
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
                'validation_status' => $this->presenter->label($request->validation_status ?? 'needs_validation'),
                'status_key' => $this->presenter->statusKey($request->validation_status ?? 'needs_validation'),
            ])
            ->values();

        return [
            'needs_validation' => (int) ($counts['needs_validation'] ?? 0),
            'validated' => (int) ($counts['validated'] ?? 0),
            'released' => ResourceRequest::query()->whereNotNull('released_for_tracking_at')->count(),
            'latest' => $latest,
        ];
    }


    public function getMapSummary(?string $eventId, array $householdSummary): array
    {
        if (! $eventId || ! Schema::hasTable('evacuation_centers')) {
            return [
                'evacuation_sites' => 0,
                'unsafe_households' => 0,
                'unchecked_households' => 0,
            ];
        }

        $evacuationSites = EvacuationCenter::query()
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


    public function getRecentActivity(?string $eventId): array
    {
        if (! $eventId || ! Schema::hasTable('household_status_logs')) {
            return [];
        }

        return HouseholdStatusLog::query()
            ->from('household_status_logs as hsl')
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
                'time' => $this->presenter->formatTime($activity->submitted_at),
                'household_name' => $activity->household_name ?? $activity->household_id,
                'status' => $activity->status_label ?? 'Reported',
                'status_key' => $this->presenter->statusKey($activity->status_key ?? 'reported'),
                'source' => $this->presenter->label($activity->source),
                'battery_level' => $activity->battery_level,
                'location_label' => $activity->location_label,
            ])
            ->values()
            ->all();
    }


}




