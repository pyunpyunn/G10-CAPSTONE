<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use App\Models\GeotaggedLocation;
use App\Models\Household;
use App\Models\HouseholdDisaster;
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
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class RescueDispatchWorkflow
{
    public function __construct(private RescueDispatchQuery $query, private RescueDispatchPresenter $presenter) {}

    public function store(Request $request, array $validated, object $event): array
    {
        $responderId=$this->resolveResponderId($validated,$event->event_id);
        if(!$responderId) throw ValidationException::withMessages(['responder_id'=>['Select an available responder or a team with at least one available responder.']]);
        $selected=$this->selectedResponderIds($validated,$responderId);$this->ensureSelectedRespondersBelongToTeam($selected,$validated['team_id']??null);
        $id=DB::transaction(function()use($request,$validated,$event,$responderId,$selected):int{
            $now=now();$id=$this->nextId('responder_assignments','assignment_id');$status=$this->statusKey($validated['status']??'dispatched');
            foreach($selected as $responderId)$this->ensureResponderIsAvailable($responderId,$event->event_id);
            if(!empty($validated['household_id']))$this->ensureHouseholdCanReceive($validated['household_id'],$event->event_id);
            ResponderAssignment::query()->create(['assignment_id'=>$id,'assignment_code'=>'DSP-'.$now->format('Ymd').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),'responder_id'=>$responderId,'team_id'=>$validated['team_id']??null,'disaster_id'=>$event->event_id,'household_id'=>$validated['household_id']??null,'assigned_area'=>$validated['assigned_area'],'route_notes'=>$this->routeNotesJson($validated),'priority_level'=>$validated['priority_level'],'dispatch_notes'=>$validated['dispatch_notes']??null,'created_by_admin_id'=>$request->user()?->user_id,'status'=>$status,'assigned_at'=>$now,'accepted_at'=>null,'en_route_at'=>$status==='en_route'?$now:null,'arrived_at'=>$status==='on_scene'?$now:null,'completed_at'=>$status==='completed'?$now:null,'outcome_notes'=>$this->outcomesJson($validated),'updated_at'=>$now]);
            $this->updateSelectedResponderDuty($selected,$validated['team_id']??null,$status);$this->audit($request,'create_dispatch','responder_assignments',(string)$id,null,$validated);return $id;
        });
        $dispatch=$this->presenter->dispatch($this->query->dispatchRecord($id));
        $push=$request->attributes->get('dispatch_push_result');
        return ['dispatch'=>$dispatch,'responder_ids'=>$selected,'push_delivery'=>$push];
    }

    public function update(Request $request,int $id,object $dispatch,array $validated):void
    {
        DB::transaction(function()use($request,$id,$dispatch,$validated):void{$status=$this->statusKey($validated['status']??$dispatch->status);$now=now();$updates=['status'=>$status,'assigned_area'=>$validated['assigned_area']??$dispatch->assigned_area,'priority_level'=>$validated['priority_level']??$dispatch->priority_level,'dispatch_notes'=>$validated['dispatch_notes']??$dispatch->dispatch_notes,'route_notes'=>$this->routeNotesJson($validated,$dispatch->route_notes),'outcome_notes'=>$this->outcomesJson($validated,$dispatch->outcome_notes),'updated_at'=>$now];if(in_array($status,['accepted','en_route'],true)&&!$dispatch->accepted_at)$updates['accepted_at']=$now;if($status==='en_route'&&!$dispatch->en_route_at)$updates['en_route_at']=$now;if($status==='on_scene'&&!$dispatch->arrived_at)$updates['arrived_at']=$now;if($status==='completed'&&!$dispatch->completed_at)$updates['completed_at']=$now;ResponderAssignment::query()->where('assignment_id',$id)->update($updates);$this->updateSelectedResponderDuty($this->responderIdsForDispatch($dispatch),$dispatch->team_id,$status);$this->audit($request,'update_dispatch','responder_assignments',(string)$id,$this->presenter->dispatch($dispatch),$validated);});
    }

    public function complete(Request $request,int $id,object $dispatch,array $validated):void
    {
        DB::transaction(function()use($request,$id,$dispatch,$validated):void{$now=now();ResponderAssignment::query()->where('assignment_id',$id)->update(['status'=>'completed','completed_at'=>$now,'outcome_notes'=>$this->outcomesJson($validated,$dispatch->outcome_notes),'updated_at'=>$now]);$this->updateSelectedResponderDuty($this->responderIdsForDispatch($dispatch),$dispatch->team_id,'completed');$this->saveHouseholdOutcome($request,$dispatch,$validated,$now);$this->audit($request,'complete_dispatch','responder_assignments',(string)$id,$this->presenter->dispatch($dispatch),$validated);});
    }

    public function updateLocation(Request $request,int $id,object $dispatch,array $validated):void
    {
        DB::transaction(function()use($request,$id,$dispatch,$validated):void{$now=now();$logId=$this->nextId('responder_location_logs','log_id');ResponderLocationLog::query()->create(['log_id'=>$logId,'responder_id'=>$dispatch->responder_id,'latitude'=>$validated['latitude'],'longitude'=>$validated['longitude'],'battery_level'=>$validated['battery_level']??null,'signal_strength'=>$validated['signal_strength']??null,'logged_at'=>$now]);$this->saveRouteCoordinate($id,$validated,$now);Responder::query()->where('responder_id',$dispatch->responder_id)->update(['last_active_at'=>$now,'duty_status'=>'deployed','updated_at'=>$now]);$this->audit($request,'update_responder_location','responder_location_logs',(string)$logId,null,['assignment_id'=>$id,'latitude'=>$validated['latitude'],'longitude'=>$validated['longitude']]);});
    }

    public function authorize(Request $request,object $dispatch):void
    {
        if($request->user()?->role?->role_key!=='rescuer')return;$r=Responder::query()->where('user_id',$request->user()?->user_id)->orWhere('username',$request->user()?->username)->first();if(!$r||(int)$r->responder_id!==(int)$dispatch->responder_id)abort(response()->json(['message'=>'This dispatch is not assigned to your responder account.'],403));
    }

    private function resolveResponderId(array $v,?string $eventId):?int
    {
        if(!empty($v['selected_responder_ids'][0]))return(int)$v['selected_responder_ids'][0];if(!empty($v['responder_id']))return(int)$v['responder_id'];if(empty($v['team_id']))return null;$leader=RescueTeam::query()->where('team_id',$v['team_id'])->value('leader_responder_id');if($leader&&$this->availableResponders($eventId)->where('r.responder_id',$leader)->exists())return(int)$leader;return $this->availableResponders($eventId)->where('r.team_id',$v['team_id'])->orderBy('r.responder_id')->value('r.responder_id');
    }
    private function selectedResponderIds(array $v,int $fallback):array{return collect($v['selected_responder_ids']??[])->map(fn($id)=>(int)$id)->filter()->unique()->values()->all()?:[$fallback];}
    private function ensureSelectedRespondersBelongToTeam(array $ids,?int $teamId):void{if(!$teamId||!$ids)return;if(Responder::query()->whereIn('responder_id',$ids)->where('team_id',$teamId)->count()!==count($ids))throw ValidationException::withMessages(['selected_responder_ids'=>['Selected responders must belong to the selected team.']]);}
    private function activeStatuses():array{return['dispatched','accepted','en_route','on_scene','onscene','returning'];}
    private function availableResponders(?string $eventId=null){$q=Responder::query()->withoutGlobalScopes()->from('responders as r');if(!$eventId)return$q->where(fn($x)=>$x->whereNull('r.duty_status')->orWhere('r.duty_status','<>','off_duty'));return$q->where(fn($x)=>$x->whereNull('r.is_deployed')->orWhere('r.is_deployed',false))->where(fn($x)=>$x->whereNull('r.duty_status')->orWhereNotIn('r.duty_status',['deployed','dispatched','accepted','en_route','on_scene','off_duty']))->whereNotExists(fn($x)=>$x->from('responder_assignments as active_ra')->whereColumn('active_ra.responder_id','r.responder_id')->where('active_ra.disaster_id',$eventId)->whereIn('active_ra.status',$this->activeStatuses()));}
    private function ensureResponderIsAvailable(int $id,string $eventId):void{if($this->availableResponders($eventId)->where('r.responder_id',$id)->exists())return;$active=ResponderAssignment::query()->where('responder_id',$id)->where('disaster_id',$eventId)->whereIn('status',$this->activeStatuses())->orderByDesc('assigned_at')->first();if($active)throw ValidationException::withMessages(['responder_id'=>['This responder already has an active dispatch ('.$active->assignment_code.'). Complete or cancel that assignment before assigning another one.']]);throw ValidationException::withMessages(['selected_responder_ids'=>['One or more selected responders are not available for a new dispatch.']]);}
    private function ensureHouseholdCanReceive(?string $id,string $eventId):void{if(!$id)return;if(!Household::query()->where('household_id',$id)->exists())throw ValidationException::withMessages(['household_id'=>['Select a valid household from the affected area list.']]);if(!GeotaggedLocation::query()->where('household_id',$id)->whereNotNull('latitude')->whereNotNull('longitude')->exists())throw ValidationException::withMessages(['household_id'=>['Select a household with saved GPS coordinates. Routing cannot be generated for households without a geotag.']]);$active=ResponderAssignment::query()->where('household_id',$id)->where('disaster_id',$eventId)->whereIn('status',$this->activeStatuses())->orderByDesc('assigned_at')->first();if($active)throw ValidationException::withMessages(['household_id'=>['This household is already assigned to '.$active->assignment_code.'. Complete or cancel that dispatch before creating another one for the same household.']]);}
    private function updateSelectedResponderDuty(array $ids,?int $teamId,string $status):void{foreach($ids as $id){$active=in_array($status,['accepted','dispatched','en_route','on_scene'],true);$now=now();Responder::query()->where('responder_id',$id)->update(['is_deployed'=>$active,'duty_status'=>$active?'deployed':'available','last_active_at'=>$now,'updated_at'=>$now]);if($teamId)RescueTeam::query()->where('team_id',$teamId)->update(['duty_status'=>$active?$status:'standby','updated_at'=>$now]);}}
    private function responderIdsForDispatch(object $dispatch):array{$route=$this->presenter->decodeJson($dispatch->route_notes);return collect($route['selected_responder_ids']??[$dispatch->responder_id])->map(fn($id)=>(int)$id)->filter()->unique()->values()->all();}
    private function statusKey(?string $s):string{return match($s){'onscene','on-scene'=>'on_scene','en-route'=>'en_route','off_duty','off-duty','stand-by'=>'standby',null,''=>'standby',default=>str_replace('-','_',$s)};}
    private function routeNotesJson(array $v,?string $existing=null):string{$current=$this->presenter->decodeJson($existing);$ids=collect($v['selected_responder_ids']??($current['selected_responder_ids']??[]))->map(fn($id)=>(int)$id)->filter()->unique()->values()->all();$names=empty($ids)?($current['selected_responders']??[]):Responder::query()->whereIn('responder_id',$ids)->orderBy('full_name')->pluck('full_name')->all();return json_encode(array_merge($current,['households_to_cover'=>(int)($v['households_to_cover']??($current['households_to_cover']??0)),'responder_count'=>(int)($v['responder_count']??($current['responder_count']??1)),'selected_responder_ids'=>$ids,'selected_responders'=>$names,'route_notes'=>$v['route_notes']??($current['route_notes']??null)]));}
    private function outcomesJson(array $v,?string $existing=null):string{$c=$this->presenter->decodeJson($existing);return json_encode(array_merge($c,['safe_count'=>(int)($v['safe_count']??($c['safe_count']??0)),'evacuated_count'=>(int)($v['evacuated_count']??($c['evacuated_count']??0)),'unsafe_count'=>(int)($v['unsafe_count']??($c['unsafe_count']??0)),'injured_count'=>(int)($v['injured_count']??($c['injured_count']??0)),'missing_count'=>(int)($v['missing_count']??($c['missing_count']??0)),'pending_count'=>(int)($v['pending_count']??($c['pending_count']??0)),'outcome_notes'=>$v['outcome_notes']??($c['outcome_notes']??null)]));}
    private function saveHouseholdOutcome(Request $request,object $d,array $v,Carbon $now):void
    {
        if(empty($d->household_id)||!Schema::hasTable('household_disasters')||!Schema::hasTable('household_statuses'))return;$key=((int)($v['unsafe_count']??0)>0||(int)($v['injured_count']??0)>0||(int)($v['missing_count']??0)>0)?'unsafe':(((int)($v['evacuated_count']??0)>0)?'evacuated':'safe');if(!Schema::hasTable('household_statuses'))return;$candidates=match($key){'safe'=>['safe','active','returned'],'evacuated'=>['evacuated','relocated'],'unsafe'=>['unsafe','not_evacuated','displaced','needs_help','need_help'],default=>[$key]};$statusId=HouseholdStatus::query()->whereIn('status_key',$candidates)->orderByRaw('CASE '.collect($candidates)->map(fn($k,$i)=>'WHEN status_key = ? THEN '.$i)->implode(' ').' ELSE 999 END',$candidates)->value('status_id');if(!$statusId)return;$notes='Dispatch '.$d->assignment_code.' completed by responder team.'.(!empty($v['outcome_notes'])?"\n".$v['outcome_notes']:'');$data=['current_status_id'=>$statusId,'last_status_source'=>'rescue_dispatch','last_status_notes'=>$notes,'last_reported_by_user_id'=>$request->user()?->user_id,'priority_level'=>in_array($key,['safe','evacuated'],true)?'monitor':'urgent','needs_dispatch'=>!in_array($key,['safe','evacuated'],true),'last_reported_at'=>$now,'updated_at'=>$now];$existing=HouseholdDisaster::query()->where('disaster_id',$d->disaster_id)->where('household_id',$d->household_id)->first();if($existing)HouseholdDisaster::query()->where('household_disaster_id',$existing->household_disaster_id)->update($this->filterColumns('household_disasters',$data));else HouseholdDisaster::query()->create(array_merge($this->filterColumns('household_disasters',$data),['household_disaster_id'=>$this->nextId('household_disasters','household_disaster_id'),'household_id'=>$d->household_id,'disaster_id'=>$d->disaster_id,'initial_status_id'=>$statusId,'created_at'=>$now]));if(Schema::hasTable('household_status_logs'))HouseholdStatusLog::query()->create(['disaster_id'=>$d->disaster_id,'household_id'=>$d->household_id,'status_id'=>$statusId,'source'=>'rescue_dispatch','submitted_by_user_id'=>$request->user()?->user_id,'responder_id'=>$d->responder_id,'notes'=>$notes,'submitted_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
    }
    private function saveRouteCoordinate(int $id,array $v,Carbon $now):void{ResponderAssignment::query()->where('assignment_id',$id)->lockForUpdate()->first();$route=ResponderRoute::query()->where('assignment_id',$id)->orderByDesc('created_at')->first();if(!$route){$routeId=$this->nextId('responder_routes','route_id');ResponderRoute::query()->create(['route_id'=>$routeId,'assignment_id'=>$id,'route_name'=>'Responder mobile route','route_status'=>'active','start_latitude'=>$v['latitude'],'start_longitude'=>$v['longitude'],'end_latitude'=>null,'end_longitude'=>null,'estimated_distance_km'=>null,'estimated_duration_min'=>null,'route_polyline'=>null,'created_at'=>$now,'updated_at'=>$now]);$route=(object)['route_id'=>$routeId];}$order=((int)RouteCoordinate::query()->where('route_id',$route->route_id)->max('sequence_order'))+1;RouteCoordinate::query()->create(['coordinate_id'=>$this->nextId('route_coordinates','coordinate_id'),'route_id'=>$route->route_id,'latitude'=>$v['latitude'],'longitude'=>$v['longitude'],'sequence_order'=>$order,'recorded_at'=>$now,'accuracy_m'=>$v['accuracy_m']??null]);}
    private function nextId(string $table,string $column):int{return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table,$column);}
    private function filterColumns(string $table,array $data):array{if(!Schema::hasTable($table))return$data;$columns=Schema::getColumnListing($table);return collect($data)->filter(fn($v,string $k)=>in_array($k,$columns,true))->all();}
    private function audit(Request $r,string $action,string $table,string $ref,mixed $old,mixed $new):void{AuditLog::query()->create(['user_id'=>$r->user()?->user_id,'role_key'=>$r->user()?->role?->role_key,'module'=>'rescue_dispatch','action'=>$action,'reference_table'=>$table,'reference_id'=>$ref,'old_values'=>$old?json_encode($old):null,'new_values'=>$new?json_encode($new):null,'ip_address'=>$r->ip(),'user_agent'=>substr((string)$r->userAgent(),0,255),'created_at'=>now()]);}
}







