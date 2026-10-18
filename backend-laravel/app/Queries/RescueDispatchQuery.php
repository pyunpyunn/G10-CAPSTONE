<?php

namespace App\Queries;

use App\Models\DisasterEvent;
use App\Models\Household;
use App\Models\Responder;
use App\Models\ResponderAssignment;
use App\Models\ResponderRoute;
use App\Models\RescueTeam;
use App\Models\RouteCoordinate;
use App\Presenters\RescueDispatchPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescueDispatchQuery
{
    public function __construct(private RescueDispatchPresenter $presenter) {}

    public function activeEvent(): ?object
    {
        return DisasterEvent::query()->with(['type', 'severity'])->when(Schema::hasColumn('disaster_events', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->whereNull('ended_at')->orderByDesc('started_at')->first();
    }

    public function dispatchRecord(int $id): ?object
    {
        return ResponderAssignment::query()->from('responder_assignments as ra')->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')->where('ra.assignment_id', $id)->select(['ra.*', 'rt.team_name', 'rt.team_code', 'rt.team_type', 'r.full_name as responder_name', 'r.contact_number as responder_contact', 'r.user_id as responder_user_id'])->first();
    }

    public function reportOutcomes(object $dispatch): ?array
    {
        if (!Schema::hasTable('household_status_logs') || !Schema::hasTable('household_statuses') || !Schema::hasColumn('household_status_logs', 'source')) return null;
        $route = $this->presenter->decodeJson($dispatch->route_notes);
        $ids = array_values(array_unique(array_filter(array_merge([$dispatch->household_id], $route['household_ids'] ?? []))));
        $counts = ['safe_count'=>0,'evacuated_count'=>0,'unsafe_count'=>0,'pending_count'=>0,'report_count'=>0];
        if (!$ids) return $counts;
        $rows = DB::table('household_status_logs as log')->leftJoin('household_statuses as status', 'status.status_id', '=', 'log.status_id')
            ->where('log.disaster_id', $dispatch->disaster_id)->where('log.source', 'responder_field_report')->whereIn('log.household_id', $ids)
            ->orderByDesc('log.submitted_at')->orderByDesc('log.status_log_id')->get(['log.household_id','status.status_key'])->unique('household_id');
        foreach ($rows as $row) {
            $key = match ($row->status_key) {
                'safe','active','returned' => 'safe_count', 'evacuated','relocated' => 'evacuated_count',
                'unsafe','not_evacuated','displaced','needs_help','need_help','needs_assistance','missing','injured' => 'unsafe_count', default => 'pending_count',
            };
            $counts[$key]++; $counts['report_count']++;
        }
        return $counts;
    }

    public function coverageHouseholds(object $dispatch): array
    {
        if (!Schema::hasTable('households')) return [];
        $route = $this->presenter->decodeJson($dispatch->route_notes);
        $ids = array_values(array_unique(array_filter(array_merge([$dispatch->household_id], $route['household_ids'] ?? []))));
        if (!$ids) return [];
        $query = Household::query()->withoutGlobalScopes()->from('households as h')->whereIn('h.household_id', HouseholdEligibilityQuery::ids())->whereIn('h.household_id', $ids);
        $columns = ['h.household_id'];
        foreach (['household_name','household_code','member_count'] as $column) $columns[] = Schema::hasColumn('households', $column) ? 'h.'.$column : DB::raw('NULL as '.$column);
        if (Schema::hasTable('household_disasters') && Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_disasters as hd', fn ($join) => $join->on('hd.household_id','=','h.household_id')->where('hd.disaster_id',$dispatch->disaster_id))
                ->leftJoin('household_statuses as hs','hs.status_id','=','hd.current_status_id');
            $columns[] = 'hs.status_key';
        }
        $contacts = $this->contactChannels($ids);
        return $query->get($columns)->unique('household_id')->map(fn ($row) => ['household_id'=>$row->household_id,'household_name'=>$row->household_name ?? $row->household_code ?? $row->household_id,
            'member_count'=>$row->member_count,'has_contact_channel'=>$contacts[$row->household_id] ?? null,'reported_unsafe_count'=>in_array($row->status_key ?? null,['unsafe','not_evacuated','displaced','needs_help','need_help','missing','injured'],true) ? 1 : 0])->values()->all();
    }

    private function contactChannels(array $ids): array
    {
        if (!$ids || !Schema::hasColumn('households', 'contact_number') || !Schema::hasTable('device_tokens') || !Schema::hasTable('trusted_households')) return [];
        $reachable = DB::table('households as contact')->whereIn('contact.household_id', $ids)->where(function ($query) {
            $query->where(fn ($phone) => $phone->whereNotNull('contact.contact_number')->where('contact.contact_number','<>',''))
                ->orWhereExists(fn ($device) => $device->selectRaw('1')->from('device_tokens as device')->whereColumn('device.household_id','contact.household_id')->where('device.is_active',true));
        })->pluck('contact.household_id')->all();
        foreach ([['requesting_household_id','trusted_household_id'],['trusted_household_id','requesting_household_id']] as [$owner,$other]) {
            $reachable = array_merge($reachable, DB::table('trusted_households as trust')->join('households as contact','contact.household_id','=','trust.'.$other)
                ->whereIn('trust.'.$owner,$ids)->whereIn('trust.validation_status',['validated','approved'])->where(function ($query) {
                    $query->where(fn ($phone) => $phone->whereNotNull('contact.contact_number')->where('contact.contact_number','<>',''))
                        ->orWhereExists(fn ($device) => $device->selectRaw('1')->from('device_tokens as device')->whereColumn('device.household_id','contact.household_id')->where('device.is_active',true));
                })->pluck('trust.'.$owner)->all());
        }
        $reachable = array_fill_keys($reachable, true);
        return array_combine($ids, array_map(fn ($id) => isset($reachable[$id]), $ids));
    }

    public function dispatches(Request $request, string $eventId): object
    {
        $query = ResponderAssignment::query()->from('responder_assignments as ra')->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')->where('ra.disaster_id', $eventId)->select(['ra.assignment_id', 'ra.assignment_code', 'ra.responder_id', 'ra.responder_count', 'ra.dispatch_type', 'ra.team_id', 'ra.disaster_id', 'ra.household_id', 'ra.assigned_area', 'ra.route_notes', 'ra.priority_level', 'ra.dispatch_notes', 'ra.status', 'ra.assigned_at', 'ra.accepted_at', 'ra.en_route_at', 'ra.arrived_at', 'ra.completed_at', 'ra.outcome_notes', 'ra.updated_at', 'rt.team_name', 'rt.team_code', 'rt.team_type', 'r.full_name as responder_name', 'r.contact_number as responder_contact']);
        $status = trim((string) $request->query('status', 'all')); $search = trim((string) $request->query('search', ''));
        if ($status === 'active') $query->whereIn('ra.status', ['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning']);
        elseif ($status === 'closed') $query->whereIn('ra.status', ['completed', 'cancelled']);
        elseif ($status !== '' && $status !== 'all') $query->where('ra.status', $this->presenter->statusKey($status));
        if ($search !== '') $query->where(function ($q) use ($search): void { $q->where('ra.assignment_code', 'like', "%{$search}%")->orWhere('ra.assigned_area', 'like', "%{$search}%")->orWhere('rt.team_name', 'like', "%{$search}%")->orWhere('r.full_name', 'like', "%{$search}%"); });
        return $query->orderByRaw('CASE WHEN ra.status IN ("on_scene", "onscene", "dispatched", "en_route") THEN 0 ELSE 1 END')->orderByDesc('ra.assigned_at')->orderByDesc('ra.assignment_id')->paginate(\App\Http\Requests\ListRequest::clampPerPage($request->query('per_page')));
    }

    public function activeDispatches(?string $eventId): array
    {
        if (!$eventId) return [];
        return ResponderAssignment::query()->from('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt','rt.team_id','=','ra.team_id')->leftJoin('responders as r','r.responder_id','=','ra.responder_id')
            ->where('ra.disaster_id',$eventId)->whereIn('ra.status',['dispatched','accepted','en_route','on_scene','onscene','returning'])
            ->orderByDesc('ra.assigned_at')->orderByDesc('ra.assignment_id')
            ->get(['ra.*','rt.team_name','rt.team_code','rt.team_type','r.full_name as responder_name','r.contact_number as responder_contact'])
            ->map(fn ($assignment) => $this->presenter->dispatch($assignment))->all();
    }

    public function route(int $assignmentId): ?array
    {
        $route = ResponderRoute::query()->where('assignment_id', $assignmentId)->orderByDesc('created_at')->first();
        if (! $route) return null;
        return ['route_id' => $route->route_id, 'route_name' => $route->route_name, 'route_status' => $route->route_status, 'estimated_distance_km' => $route->estimated_distance_km, 'estimated_duration_min' => $route->estimated_duration_min, 'coordinates' => RouteCoordinate::query()->where('route_id', $route->route_id)->orderBy('sequence_order')->get(['latitude', 'longitude', 'sequence_order', 'recorded_at', 'accuracy_m'])];
    }

    public function responders(?string $eventId)
    {
        $responders = Responder::query()->withoutGlobalScopes()->from('responders as r')->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id')->when(Schema::hasColumn('responders', 'deleted_at'), fn ($q) => $q->whereNull('r.deleted_at'))->orderBy('r.full_name')->get(['r.responder_id','r.user_id','r.team_id','r.full_name','r.title','r.contact_number','r.duty_status','r.is_deployed','r.last_active_at','rt.team_name','rt.team_code']);
        $activeByResponder = $eventId && $responders->isNotEmpty()
            ? ResponderAssignment::query()->where('disaster_id', $eventId)
                ->whereIn('responder_id', $responders->pluck('responder_id'))
                ->whereIn('status', ['dispatched','accepted','en_route','on_scene','onscene','returning'])
                ->orderByDesc('assigned_at')->get(['assignment_id','assignment_code','assigned_area','responder_id','status','assigned_at'])
                ->unique('responder_id')->keyBy('responder_id')
            : collect();
        return $responders->map(function (object $r) use ($eventId, $activeByResponder): array {
            $active = $activeByResponder->get($r->responder_id);
            $key=$this->presenter->statusKey($r->duty_status);
            $available = ! $active && ! (bool) $r->is_deployed && $key === 'available';
            return ['responder_id'=>$r->responder_id,'user_id'=>$r->user_id,'team_id'=>$r->team_id,'full_name'=>$r->full_name?:'Unnamed responder','title'=>$r->title?:'Responder','contact_number'=>$r->contact_number,'team_name'=>$r->team_name?:'Unassigned','team_code'=>$r->team_code,'status'=>$this->presenter->status($active?$active->status:($eventId?$r->duty_status:'available')),'is_available'=>$available,'active_assignment_id'=>$active?->assignment_id,'active_assignment_code'=>$active?->assignment_code,'active_assigned_area'=>$active?->assigned_area,'last_active_timestamp'=>$r->last_active_at ? \Carbon\Carbon::parse($r->last_active_at)->timestamp : 0,'last_active_at'=>$this->presenter->dateTime($r->last_active_at)];
        })->values();
    }

    public function riskAreas(?string $eventId)
    {
        if (!$eventId) return collect();
        $barangayId = app(\App\Services\Shared\BarangayProfileService::class)->current()['barangay_id'] ?? null;
        $areaLabel = DB::connection()->getDriverName() === 'sqlite'
            ? "(p.purok_name || ', ' || s.sitio_name)" : "CONCAT(p.purok_name, ', ', s.sitio_name)";
        $rows = Household::query()->withoutGlobalScopes()->from('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('sitios as s', 's.sitio_id', '=', DB::raw('COALESCE(p.sitio_id, a.sitio_id)'))
            ->leftJoin('household_disasters as hd', fn ($join) => $join->on('hd.household_id', '=', 'h.household_id')->where('hd.disaster_id', $eventId))
            ->leftJoinSub(HouseholdEventStatusQuery::latestReports($eventId), 'latest_report', 'latest_report.household_id', '=', 'h.household_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'latest_report.status_id')
            ->whereIn('a.purok_id', HouseholdEligibilityQuery::puroks($barangayId))
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($q) => $q->whereNull('h.deleted_at'))
            ->whereNotNull('h.household_id')
            ->select(['h.household_id', 'h.household_code', 'h.household_name', 'h.member_count',
                DB::raw('COALESCE(p.sitio_id, a.sitio_id) as sitio_id'), 'a.purok_id',
                'a.full_address', 'a.house_number',
                DB::raw("COALESCE({$areaLabel}, s.sitio_name, NULLIF(a.purok_sitio, ''), 'Unassigned') as area_name"),
                DB::raw("(SELECT gl.location_label FROM geotagged_locations gl WHERE gl.household_id=h.household_id AND gl.location_label IS NOT NULL ORDER BY COALESCE(gl.updated_at,gl.created_at) DESC LIMIT 1) as geotag_label"),
                'hs.status_key', 'hs.status_label', 'hd.needs_dispatch', 'hd.priority_level', 'hd.last_battery_level', 'hd.last_reported_at',
                DB::raw('EXISTS (SELECT 1 FROM geotagged_locations gl WHERE gl.household_id=h.household_id AND gl.latitude IS NOT NULL AND gl.longitude IS NOT NULL) as has_geotag')])->get();
        $contacts = $this->contactChannels($rows->pluck('household_id')->unique()->all());
        $busy = collect();
        foreach (ResponderAssignment::query()->where('disaster_id', $eventId)->whereIn('status', ['dispatched','accepted','en_route','on_scene','onscene','returning'])->get(['assignment_id','household_id','route_notes']) as $assignment) {
            $route = $this->presenter->decodeJson($assignment->route_notes);
            foreach (array_filter(array_merge([$assignment->household_id], $route['household_ids'] ?? [], array_merge([], ...array_values($route['responder_households'] ?? [])))) as $householdId) $busy[$householdId] = $assignment->assignment_id;
        }
        return $rows->groupBy(fn ($row) => $row->purok_id !== null ? 'purok:'.$row->purok_id : ($row->sitio_id !== null ? 'sitio:'.$row->sitio_id : 'unregistered:'.$row->area_name))->map(function($items,string $areaId)use($busy,$contacts):array{
            $area = $items->first()->area_name;
            $total=$items->count();$unsafe=$items->filter(fn($i)=>in_array($i->status_key,['not_evacuated','displaced','unsafe','needs_help','need_help','needs_assistance','missing','injured'],true)||(bool)$i->needs_dispatch)->count();$unchecked=$items->whereNull('status_key')->count();$safe=$items->filter(fn($i)=>in_array($i->status_key,['active','returned','safe'],true))->count()+$items->filter(fn($i)=>in_array($i->status_key,['evacuated','relocated'],true))->count();$cover=$unsafe+$unchecked;$priority=$unsafe?'critical':($unchecked?'high':'watch');
            $households=$items->sortBy(fn($i)=>sprintf('%d%d%s',$i->needs_dispatch?0:1,$i->has_geotag?0:1,strtolower($i->household_name?:$i->household_id)))->map(fn($i)=>array_merge($this->presenter->riskHousehold($i,$busy), ['has_contact_channel'=>$contacts[$i->household_id] ?? null]))->values();
            return ['sitio_id'=>$items->pluck('sitio_id')->filter()->unique()->count()===1?$items->pluck('sitio_id')->filter()->first():null,'purok_id'=>$items->pluck('purok_id')->filter()->unique()->count()===1?$items->pluck('purok_id')->filter()->first():null,'id'=>$areaId,'area_name'=>$area,'zone'=>$area,'context'=>$unsafe?'Unsafe households need dispatch focus.':'Unchecked households need field verification.','priority'=>$priority,'priority_label'=>$this->presenter->label($priority),'total_households'=>$total,'geotagged_households'=>$households->where('has_geotag',true)->count(),'unsafe_households'=>$unsafe,'unchecked_households'=>$unchecked,'safe_households'=>$safe,'to_cover'=>$cover,'households'=>$households,'recommended_households'=>$households->filter(fn($i)=>$i['needs_dispatch']||$i['status_key']==='unchecked'||in_array($i['status_key'],['not_evacuated','displaced','unsafe','needs_help','need_help','needs_assistance','missing','injured'],true))->take(10)->values()];
        })->filter(fn($area)=>$area['to_cover']>0)->sortByDesc('to_cover')->values();
    }

    public function activity(?string $eventId, int $limit = 10)
    {
        return ResponderAssignment::query()->from('responder_assignments as ra')->leftJoin('rescue_teams as rt','rt.team_id','=','ra.team_id')->leftJoin('responders as r','r.responder_id','=','ra.responder_id')->when($eventId,fn($q)=>$q->where('ra.disaster_id',$eventId))->orderByDesc('ra.updated_at')->orderByDesc('ra.assigned_at')->limit($limit)->get(['ra.assignment_id','ra.status','ra.assigned_area','ra.assigned_at','ra.updated_at','rt.team_name','r.full_name'])->map(fn($log)=>['assignment_id'=>$log->assignment_id,'time'=>$this->presenter->time($log->updated_at??$log->assigned_at),'team_name'=>$log->team_name?:$log->full_name?:'Responder','status'=>$this->presenter->status($log->status),'assigned_area'=>$log->assigned_area])->values();
    }

    public function summary(?string $eventId, $teamCards = null): array
    {
        $teamCards ??= $this->teamCards($eventId);
        $counts = $eventId ? ResponderAssignment::query()->where('disaster_id', $eventId)
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status') : collect();
        $dispatched = collect(['dispatched', 'en_route', 'accepted'])->sum(fn ($key) => (int) ($counts[$key] ?? 0));
        $onScene = (int) ($counts['on_scene'] ?? 0) + (int) ($counts['onscene'] ?? 0);
        $returning = (int) ($counts['returning'] ?? 0);
        $completed = (int) ($counts['completed'] ?? 0);
        $realTeams = collect($teamCards)->filter(fn ($team) => ! empty($team['team_id']));
        $available = $realTeams->filter(fn ($team) => ($team['available_responder_count'] ?? 0) > 0)->count();
        $activeTeams = $eventId ? ResponderAssignment::query()->where('disaster_id', $eventId)
            ->whereIn('status', ['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning'])
            ->whereIn('team_id', $realTeams->pluck('team_id'))->distinct()->count('team_id') : 0;
        return ['total_teams' => $realTeams->count(), 'dispatched' => $dispatched, 'on_scene' => $onScene,
            'available_responders' => (int) $realTeams->sum('available_responder_count'),
            'total_responders' => (int) $realTeams->sum('member_count'),
            'available' => $available, 'completed' => $completed,
            'response_rate' => $realTeams->count() ? (int) round($activeTeams / $realTeams->count() * 100) : 0,
            'active_units' => $dispatched + $onScene + $returning,
            'dispatch_progress' => ['total' => (int) $counts->sum(), 'dispatched' => $dispatched,
                'on_scene' => $onScene, 'returning' => $returning, 'completed' => $completed,
                'cancelled' => (int) ($counts['cancelled'] ?? 0)]];
    }

    public function teamCoverage(?string $eventId): array
    {
        $teams = RescueTeam::query()->orderBy('team_name')->get(['team_id', 'team_code', 'team_name']);
        $totals = [];
        if ($eventId) {
            $assignments = ResponderAssignment::query()->where('disaster_id', $eventId)
                ->where('status', '<>', 'cancelled')->whereNotNull('team_id')
                ->select(['assignment_id', 'team_id', 'household_id', 'route_notes', 'outcome_notes'])->lazyById(200, 'assignment_id');
            foreach ($assignments as $assignment) {
                $route = $this->presenter->decodeJson($assignment->route_notes);
                $outcomes = $this->presenter->decodeJson($assignment->outcome_notes);
                $assigned = max(0, (int) ($route['households_to_cover'] ?? ($assignment->household_id ? 1 : 0)));
                $reported = 0;
                foreach (['safe_count', 'evacuated_count', 'unsafe_count', 'injured_count', 'missing_count'] as $key) {
                    $reported += max(0, (int) ($outcomes[$key] ?? 0));
                }
                $id = $assignment->team_id;
                $totals[$id]['assigned'] = ($totals[$id]['assigned'] ?? 0) + $assigned;
                $totals[$id]['reported'] = ($totals[$id]['reported'] ?? 0) + min($assigned, $reported);
            }
        }
        return $teams->map(function ($team) use ($totals) {
            $assigned = $totals[$team->team_id]['assigned'] ?? 0;
            $reported = $totals[$team->team_id]['reported'] ?? 0;
            return ['team_id' => $team->team_id, 'team_code' => $team->team_code, 'team_name' => $team->team_name,
                'assigned_households' => $assigned, 'reported_households' => $reported,
                'coverage_percent' => $assigned ? (int) round($reported / $assigned * 100) : 0];
        })->all();
    }

    public function teamCards(?string $eventId)
    {
        $teams = RescueTeam::query()->from('rescue_teams as rt')->leftJoin('responders as leader', 'leader.responder_id', '=', 'rt.leader_responder_id')->orderBy('rt.team_name')->get(['rt.team_id', 'rt.team_code', 'rt.team_name', 'rt.team_type', 'rt.duty_status', 'leader.full_name as leader_name']);
        $teamIds = $teams->pluck('team_id')->filter()->values()->all();
        $memberCounts = $teamIds === [] ? collect() : Responder::query()
            ->whereIn('team_id', $teamIds)
            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->selectRaw("team_id, COUNT(*) as members, SUM(CASE WHEN is_deployed = 1 OR duty_status IN ('on_duty','dispatched','accepted','en_route','on_scene') THEN 1 ELSE 0 END) as active_members")
            ->groupBy('team_id')->get()->keyBy('team_id');
        $activeAssignments = collect();
        if ($eventId && $teamIds !== []) {
            $ranked = ResponderAssignment::query()->where('disaster_id', $eventId)
                ->whereIn('team_id', $teamIds)->whereNotIn('status', ['completed', 'cancelled'])
                ->select(['team_id', 'assignment_id', 'responder_id', 'assigned_area', 'assigned_at', 'status', 'route_notes', 'outcome_notes'])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY team_id ORDER BY assigned_at DESC, assignment_id DESC) as row_rank');
            $activeAssignments = DB::query()->fromSub($ranked, 'ranked_assignments')->where('row_rank', 1)->get()->keyBy('team_id');
        }
        $available = $this->availableResponderQuery($eventId)->whereIn('r.team_id', $teamIds)
            ->selectRaw('r.team_id, MIN(r.responder_id) as responder_id, COUNT(*) as available_count')->groupBy('r.team_id')->get()->keyBy('team_id');
        $cards = $teams->map(fn (object $team): array => $this->teamCard(
            $team, $eventId, $activeAssignments->get($team->team_id),
            $memberCounts->get($team->team_id), $available->get($team->team_id),
        ))->values();
        if ($cards->isEmpty()) {
            $count = Responder::query()->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', 0))->count();
            if ($count > 0) { $available = $this->availableResponderQuery($eventId)->where(fn ($q) => $q->whereNull('r.team_id')->orWhere('r.team_id', 0))->count(); $cards->push(['team_id'=>null,'team_code'=>'POOL','team_name'=>'Unassigned responder pool','team_type'=>'Responder pool','status_key'=>'available','status_label'=>'Available','leader_name'=>'No team leader assigned','member_count'=>$count,'available_responder_count'=>$available,'assigned_households'=>0,'assigned_area'=>'No dispatch area yet','active_assignment_id'=>null,'active_responder_id'=>null,'available_responder_id'=>null,'is_available'=>false,'outcomes'=>$this->presenter->outcomes([]),'coverage_percent'=>0,'assigned_time'=>null]); }
        }
        return $cards;
    }

    private function teamCard(object $team, ?string $eventId, ?object $active, ?object $counts, mixed $available): array
    {
        if (! $team->team_id) { $status=$this->presenter->status('available'); return ['team_id'=>null,'team_code'=>$team->team_code,'team_name'=>$team->team_name,'team_type'=>$team->team_type,'status_key'=>$status['key'],'status_label'=>$status['label'],'leader_name'=>'No team leader assigned','member_count'=>0,'available_responder_count'=>0,'assigned_households'=>0,'assigned_area'=>'No active dispatch','active_assignment_id'=>null,'active_responder_id'=>null,'available_responder_id'=>null,'is_available'=>false,'outcomes'=>$this->presenter->outcomes([]),'coverage_percent'=>0,'assigned_time'=>null]; }
        $members = (int) ($counts->members ?? 0);
        $activeMembers = (int) ($counts->active_members ?? 0);
        $out=$this->presenter->decodeJson($active?->outcome_notes); $route=$this->presenter->decodeJson($active?->route_notes); $status=$this->presenter->status($eventId?($active?->status?:'available'):$team->duty_status);
        return ['team_id'=>$team->team_id,'team_code'=>$team->team_code,'team_name'=>$team->team_name?:'Unnamed team','team_type'=>$team->team_type?:'Response team','status_key'=>$status['key'],'status_label'=>$status['label'],'leader_name'=>$team->leader_name?:'No team leader assigned','member_count'=>$members,'active_member_count'=>$activeMembers,'available_responder_count'=>(int)($available?->available_count??0),'assigned_households'=>(int)($route['households_to_cover']??0),'assigned_area'=>$active?->assigned_area?:'No active dispatch','active_assignment_id'=>$active?->assignment_id,'active_responder_id'=>$active?->responder_id,'available_responder_id'=>$available?->responder_id?(int)$available->responder_id:null,'is_available'=>(int)($available?->available_count??0)>0,'outcomes'=>$this->presenter->outcomes($out),'coverage_percent'=>$this->presenter->coveragePercent($out,(int)($route['households_to_cover']??0)),'assigned_time'=>$this->presenter->time($active?->assigned_at)];
    }

    private function availableResponderQuery(?string $eventId = null)
    {
        $query = Responder::query()->withoutGlobalScopes()->from('responders as r');
        if (! $eventId) return $query->where('r.duty_status', 'available')->where('r.is_deployed', false);
        return $query->where('r.is_deployed', false)
            ->where('r.duty_status', 'available')
            ->whereNotExists(function ($q) use ($eventId): void { $q->from('responder_assignments as active_ra')->whereColumn('active_ra.responder_id', 'r.responder_id')->where('active_ra.disaster_id', $eventId)->whereIn('active_ra.status', ['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning']); });
    }
}


