<?php

namespace App\Services\Web;

use App\Actions\SelectDispatchResponders;
use App\Actions\UpdateHouseholdStatus;
use App\Models\AuditLog;
use App\Models\GeotaggedLocation;
use App\Models\Household;
use App\Models\HouseholdStatus;
use App\Models\HouseholdStatusLog;
use App\Models\Responder;
use App\Models\ResponderAssignment;
use App\Models\ResponderLocationLog;
use App\Models\ResponderRoute;
use App\Models\RescueTeam;
use App\Models\RouteCoordinate;
use App\Presenters\RescueDispatchPresenter;
use App\Queries\RescueDispatchQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescueDispatchWorkflow
{
    public function __construct(private RescueDispatchQuery $query, private RescueDispatchPresenter $presenter, private SelectDispatchResponders $selector) {}

    public function store(Request $request, array $validated, object $event): array
    {
        $selection = [];
        $id=DB::transaction(function()use($request,$validated,$event,&$selection):int{
            $selection=$this->selector->select((int)$validated['team_id'], (string)$event->event_id,
                (int)$validated['responder_count'], $validated['household_id']??null);
            if (($validated['dispatch_type']??'rescue')==='welfare_check' && $selection['assigned_count']!==2) {
                throw ValidationException::withMessages(['responder_count'=>['A welfare check requires two available rescuers from the selected team.']]);
            }
            $selected=$selection['ids'];
            $responderId=$selected[0];
            $validated['selected_responder_ids']=$selected;
            $validated['responder_count']=$selection['assigned_count'];
            $now=now();$id=$this->nextId('responder_assignments','assignment_id');$status=$this->statusKey($validated['status']??'dispatched');
            if(!empty($validated['household_id']))$this->ensureHouseholdCanReceive($validated['household_id'],$event->event_id);
            ResponderAssignment::query()->create(['assignment_id'=>$id,'assignment_code'=>'DSP-'.$now->format('Ymd').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),'responder_id'=>$responderId,'responder_count'=>$selection['assigned_count'],'dispatch_type'=>$validated['dispatch_type']??'rescue','team_id'=>$validated['team_id']??null,'disaster_id'=>$event->event_id,'household_id'=>$validated['household_id']??null,'assigned_area'=>$validated['assigned_area'],'route_notes'=>$this->routeNotesJson($validated),'priority_level'=>$validated['priority_level'],'dispatch_notes'=>$validated['dispatch_notes']??null,'created_by_admin_id'=>$request->user()?->user_id,'status'=>$status,'assigned_at'=>$now,'accepted_at'=>null,'en_route_at'=>$status==='en_route'?$now:null,'arrived_at'=>$status==='on_scene'?$now:null,'completed_at'=>$status==='completed'?$now:null,'outcome_notes'=>$this->outcomesJson($validated),'updated_at'=>$now]);
            $this->updateSelectedResponderDuty($selected,$validated['team_id']??null,$status);$this->audit($request,'create_dispatch','responder_assignments',(string)$id,null,$validated);return $id;
        });
        $dispatch=$this->presenter->dispatch($this->query->dispatchRecord($id));
        return ['dispatch'=>$dispatch,'responder_ids'=>$selection['ids'],
            'requested_count'=>$selection['requested_count'],'available_count'=>$selection['available_count'],
            'assigned_count'=>$selection['assigned_count']];
    }

    public function update(Request $request,int $id,object $dispatch,array $validated):void
    {
        DB::transaction(function () use ($request, $id, $dispatch, $validated): void {
            $current = ResponderAssignment::query()->where('assignment_id', $id)->lockForUpdate()->first();
            if (! $current || in_array($current->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => ['This dispatch is already closed. Refresh before updating it.']]);
            }
            $status = $this->statusKey($validated['status'] ?? $current->status);
            $now = now();
            $updates = [
                'status' => $status,
                'assigned_area' => $validated['assigned_area'] ?? $current->assigned_area,
                'priority_level' => $validated['priority_level'] ?? $current->priority_level,
                'dispatch_notes' => $validated['dispatch_notes'] ?? $current->dispatch_notes,
                'route_notes' => $this->routeNotesJson($validated, $current->route_notes),
                'outcome_notes' => $this->outcomesJson($validated, $current->outcome_notes),
                'updated_at' => $now,
            ];
            if (in_array($status, ['accepted', 'en_route'], true) && ! $current->accepted_at) $updates['accepted_at'] = $now;
            if ($status === 'en_route' && ! $current->en_route_at) $updates['en_route_at'] = $now;
            if ($status === 'on_scene' && ! $current->arrived_at) $updates['arrived_at'] = $now;
            if ($status === 'completed' && ! $current->completed_at) $updates['completed_at'] = $now;
            $current->update($updates);
            $this->updateSelectedResponderDuty($this->responderIdsForDispatch($current), $current->team_id, $status);
            $this->audit($request, 'update_dispatch', 'responder_assignments', (string) $id, $this->presenter->dispatch($dispatch), $validated);
        }, 3);
    }

    public function complete(Request $request,int $id,object $dispatch,array $validated):void
    {
        DB::transaction(function () use ($request, $id, $dispatch, $validated): void {
            $current = ResponderAssignment::query()->where('assignment_id', $id)->lockForUpdate()->first();
            if (! $current || in_array($current->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => ['This dispatch is already closed. Refresh before completing it.']]);
            }
            $now = now();
            $current->update(['status' => 'completed', 'completed_at' => $now,
                'outcome_notes' => $this->outcomesJson($validated, $current->outcome_notes), 'updated_at' => $now]);
            $this->updateSelectedResponderDuty($this->responderIdsForDispatch($current), $current->team_id, 'completed');
            $this->saveHouseholdOutcome($request, $current, $validated, $now);
            $this->audit($request, 'complete_dispatch', 'responder_assignments', (string) $id, $this->presenter->dispatch($dispatch), $validated);
        }, 3);
    }

    public function updateLocation(Request $request,int $id,object $dispatch,array $validated):void
    {
        DB::transaction(function () use ($request, $id, $validated): void {
            $current = ResponderAssignment::query()->whereKey($id)->lockForUpdate()->first();
            if (! $current || in_array($current->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => ['This dispatch is already closed.']]);
            }
            $responderId = $current->responder_id;
            if ($request->user()?->role?->role_key === 'rescuer') {
                $responderId = Responder::query()->where('user_id', $request->user()->user_id)
                    ->orWhere('username', $request->user()->username)->value('responder_id');
                if (! in_array((int) $responderId, $this->responderIdsForDispatch($current), true)) {
                    abort(response()->json(['message' => 'This dispatch is not assigned to your responder account.'], 403));
                }
            }
            $now = now();
            $logId = $this->nextId('responder_location_logs', 'log_id');
            ResponderLocationLog::query()->create(['log_id' => $logId,
                'responder_id' => $responderId, 'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'], 'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null, 'logged_at' => $now]);
            $this->saveRouteCoordinate($id, $validated, $now);
            Responder::query()->where('responder_id', $responderId)
                ->update(['last_active_at' => $now, 'duty_status' => 'deployed', 'updated_at' => $now]);
            $this->audit($request, 'update_responder_location', 'responder_location_logs', (string) $logId,
                null, ['assignment_id' => $id, 'latitude' => $validated['latitude'], 'longitude' => $validated['longitude']]);
        }, 3);
    }

    public function authorize(Request $request,object $dispatch):void
    {
        if($request->user()?->role?->role_key!=='rescuer')return;$r=Responder::query()->where('user_id',$request->user()?->user_id)->orWhere('username',$request->user()?->username)->first();if(!$r||!in_array((int)$r->responder_id,$this->responderIdsForDispatch($dispatch),true))abort(response()->json(['message'=>'This dispatch is not assigned to your responder account.'],403));
    }

    private function activeStatuses():array{return['dispatched','accepted','en_route','on_scene','onscene','returning'];}
    private function ensureHouseholdCanReceive(?string $id,string $eventId):void{if(!$id)return;if(!Household::query()->where('household_id',$id)->exists())throw ValidationException::withMessages(['household_id'=>['Select a valid household from the affected area list.']]);if(!GeotaggedLocation::query()->where('household_id',$id)->whereNotNull('latitude')->whereNotNull('longitude')->exists())throw ValidationException::withMessages(['household_id'=>['Select a household with saved GPS coordinates. Routing cannot be generated for households without a geotag.']]);$active=ResponderAssignment::query()->where('household_id',$id)->where('disaster_id',$eventId)->whereIn('status',$this->activeStatuses())->orderByDesc('assigned_at')->first();if($active)throw ValidationException::withMessages(['household_id'=>['This household is already assigned to '.$active->assignment_code.'. Complete or cancel that dispatch before creating another one for the same household.']]);}
    private function updateSelectedResponderDuty(array $ids,?int $teamId,string $status):void{$active=in_array($status,$this->activeStatuses(),true);foreach($ids as $id){$now=now();Responder::query()->where('responder_id',$id)->update(['is_deployed'=>$active,'duty_status'=>$active?'deployed':'available','last_active_at'=>$now,'updated_at'=>$now]);}if($teamId){$stillAvailable=Responder::query()->where('team_id',$teamId)->where('duty_status','available')->where('is_deployed',false)->exists();RescueTeam::query()->where('team_id',$teamId)->update(['duty_status'=>$stillAvailable?'available':($active?$status:'available'),'updated_at'=>now()]);}}
    private function responderIdsForDispatch(object $dispatch):array{$route=$this->presenter->decodeJson($dispatch->route_notes);return collect($route['selected_responder_ids']??[$dispatch->responder_id])->map(fn($id)=>(int)$id)->filter()->unique()->values()->all();}
    private function statusKey(?string $s):string{return match($s){'onscene','on-scene'=>'on_scene','en-route'=>'en_route','stand-by','standby',null,''=>'available',default=>str_replace('-','_',$s)};}
    private function routeNotesJson(array $v,?string $existing=null):string{$current=$this->presenter->decodeJson($existing);$ids=collect($v['selected_responder_ids']??($current['selected_responder_ids']??[]))->map(fn($id)=>(int)$id)->filter()->unique()->values()->all();$names=empty($ids)?($current['selected_responders']??[]):Responder::query()->whereIn('responder_id',$ids)->orderBy('full_name')->pluck('full_name')->all();return json_encode(array_merge($current,['households_to_cover'=>(int)($v['households_to_cover']??($current['households_to_cover']??0)),'responder_count'=>(int)($v['responder_count']??($current['responder_count']??1)),'selected_responder_ids'=>$ids,'selected_responders'=>$names,'route_notes'=>$v['route_notes']??($current['route_notes']??null)]));}
    private function outcomesJson(array $v,?string $existing=null):string{$c=$this->presenter->decodeJson($existing);return json_encode(array_merge($c,['safe_count'=>(int)($v['safe_count']??($c['safe_count']??0)),'evacuated_count'=>(int)($v['evacuated_count']??($c['evacuated_count']??0)),'unsafe_count'=>(int)($v['unsafe_count']??($c['unsafe_count']??0)),'injured_count'=>(int)($v['injured_count']??($c['injured_count']??0)),'missing_count'=>(int)($v['missing_count']??($c['missing_count']??0)),'pending_count'=>(int)($v['pending_count']??($c['pending_count']??0)),'outcome_notes'=>$v['outcome_notes']??($c['outcome_notes']??null)]));}
    private function saveHouseholdOutcome(Request $request, object $dispatch, array $validated, Carbon $now): void
    {
        if (! $dispatch->household_id) return;

        $statusKey = ((int) ($validated['unsafe_count'] ?? 0) > 0
            || (int) ($validated['injured_count'] ?? 0) > 0
            || (int) ($validated['missing_count'] ?? 0) > 0)
            ? 'unsafe' : (((int) ($validated['evacuated_count'] ?? 0) > 0) ? 'evacuated' : 'safe');
        $candidates = match ($statusKey) {
            'safe' => ['safe', 'active', 'returned'],
            'evacuated' => ['evacuated', 'relocated'],
            default => ['unsafe', 'not_evacuated', 'displaced', 'needs_help', 'need_help'],
        };
        $statusId = HouseholdStatus::query()->whereIn('status_key', $candidates)
            ->orderByRaw('CASE '.collect($candidates)->map(fn ($key, $index) => 'WHEN status_key = ? THEN '.$index)->implode(' ').' ELSE 999 END', $candidates)
            ->value('status_id');
        if (! $statusId) return;

        $notes = 'Dispatch '.$dispatch->assignment_code.' completed by responder team.'
            .(! empty($validated['outcome_notes']) ? "\n".$validated['outcome_notes'] : '');
        app(UpdateHouseholdStatus::class)->apply(
            (string) $dispatch->disaster_id, (string) $dispatch->household_id,
            (int) $statusId, $statusKey, 'rescue_dispatch', $notes,
            $request->user()?->user_id, null,
        );
        HouseholdStatusLog::query()->create([
            'disaster_id' => $dispatch->disaster_id,
            'household_id' => $dispatch->household_id,
            'status_id' => $statusId,
            'source' => 'rescue_dispatch',
            'submitted_by_user_id' => $request->user()?->user_id,
            'responder_id' => $dispatch->responder_id,
            'notes' => $notes,
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function saveRouteCoordinate(int $id,array $v,Carbon $now):void{ResponderAssignment::query()->where('assignment_id',$id)->lockForUpdate()->first();$route=ResponderRoute::query()->where('assignment_id',$id)->orderByDesc('created_at')->first();if(!$route){$routeId=$this->nextId('responder_routes','route_id');ResponderRoute::query()->create(['route_id'=>$routeId,'assignment_id'=>$id,'route_name'=>'Responder mobile route','route_status'=>'active','start_latitude'=>$v['latitude'],'start_longitude'=>$v['longitude'],'end_latitude'=>null,'end_longitude'=>null,'estimated_distance_km'=>null,'estimated_duration_min'=>null,'route_polyline'=>null,'created_at'=>$now,'updated_at'=>$now]);$route=(object)['route_id'=>$routeId];}$order=((int)RouteCoordinate::query()->where('route_id',$route->route_id)->max('sequence_order'))+1;RouteCoordinate::query()->create(['coordinate_id'=>$this->nextId('route_coordinates','coordinate_id'),'route_id'=>$route->route_id,'latitude'=>$v['latitude'],'longitude'=>$v['longitude'],'sequence_order'=>$order,'recorded_at'=>$now,'accuracy_m'=>$v['accuracy_m']??null]);}
    private function nextId(string $table,string $column):int{return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table,$column);}
    private function audit(Request $r,string $action,string $table,string $ref,mixed $old,mixed $new):void{AuditLog::query()->create(['user_id'=>$r->user()?->user_id,'role_key'=>$r->user()?->role?->role_key,'module'=>'rescue_dispatch','action'=>$action,'reference_table'=>$table,'reference_id'=>$ref,'old_values'=>$old?json_encode($old):null,'new_values'=>$new?json_encode($new):null,'ip_address'=>$r->ip(),'user_agent'=>substr((string)$r->userAgent(),0,255),'created_at'=>now()]);}
}







