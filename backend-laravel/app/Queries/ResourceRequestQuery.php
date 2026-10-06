<?php

namespace App\Queries;

use App\Models\DisasterEvent;
use App\Models\EvacuationCenter;
use App\Models\RequestValidation;
use App\Models\ResourceRequest;
use App\Models\ResourceRequestStatus;
use App\Models\UrgencyLevel;
use App\Presenters\ResourceRequestPresenter;
use App\Services\Shared\TrackingAidForwardingService;
use App\Services\Shared\OperationalSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;
use Throwable;

class ResourceRequestQuery
{
    /** @var array<string, int>|null */
    private ?array $statusKeyIds = null;
    public function nextRequestId(): string
    {
        do {
            $id = 'RR-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (ResourceRequest::query()->where('request_id', $id)->exists());

        return $id;
    }

    public function nextTrackingReference(): string
    {
        return app(OperationalSequence::class)->nextTrackingReference();
    }
    private const SOURCES=['evatrack'=>'EvaTrack','field_team'=>'Field team','evacuation_site'=>'Evacuation site','hq_desk'=>'HQ desk','household_mobile'=>'Household mobile','rescuer_mobile'=>'Rescuer mobile','shared_db'=>'EvaTrack request'];
    private const CATEGORIES=['resource'=>'Resource','personnel'=>'Personnel','vehicle'=>'Vehicle / transport'];
    private const STATUSES=['needs_validation'=>'Pending','verified'=>'Validated','forwarded'=>'In Progress','returned'=>'Returned','fulfilled'=>'Completed','cancelled'=>'Cancelled'];
    public function __construct(private ResourceRequestPresenter $presenter,private TrackingAidForwardingService $trackingAid){}
    public function find(string $id):?object{return $this->requestQuery()->where('rr.request_id',$id)->first();}
    public function activeEvent():?object{return DisasterEvent::query()->from('disaster_events as de')->leftJoin('disaster_types as dt','dt.type_id','=','de.type_id')->leftJoin('severity_levels as sl','sl.severity_id','=','de.severity_level_id')->when(Schema::hasColumn('disaster_events','deleted_at'),fn($q)=>$q->whereNull('de.deleted_at'))->whereNull('de.ended_at')->orderByDesc('de.started_at')->select(['de.event_id','de.name','de.started_at','dt.type_name','sl.severity_key','sl.severity_label'])->first();}
    public function list(Request $request):array
    {
        $search=trim((string)$request->query('search',''));$status=$this->presenter->statusKey((string)$request->query('status','all'));$statusId=trim((string)$request->query('status_id',''));$source=$this->presenter->sourceKey((string)$request->query('source','all'));$category=$this->presenter->categoryKey((string)$request->query('category','all'));$purok=trim((string)$request->query('purok','all'));$eventId=trim((string)$request->query('event_id',''));$core=$request->boolean('core');$period=$this->periodKey((string)$request->query('period','week'));$q=$this->requestQuery();
        if($search!=='')$q->where(function($x)use($search):void{$x->where('rr.request_id','like',"%$search%")->orWhere('rr.request_source','like',"%$search%")->orWhere('rr.source_reference','like',"%$search%")->orWhere('rr.requested_by','like',"%$search%")->orWhere('rr.resource_type','like',"%$search%")->orWhere('rr.item_name','like',"%$search%")->orWhere('rr.description','like',"%$search%")->orWhere('ec.name','like',"%$search%")->orWhere('ec.osm_address','like',"%$search%");});
        if($status!=='all')$q->where('rr.validation_status',$status);if($statusId!=='')$q->where('rr.status_id',$statusId);if($source!=='all')$q->where('rr.request_source',$source);if($category!=='all')$q->where('rr.request_category',$category);if($purok!==''&&$purok!=='all')$q->where(fn($x)=>$x->where('ec.name','like',"%$purok%")->orWhere('ec.osm_address','like',"%$purok%")->orWhere('rr.description','like',"%$purok%")->orWhere('rr.evacuation_center_id','like',"%$purok%"));if($eventId!==''&&$eventId!=='all')$q->where(fn($x)=>$x->where('rr.source_reference',$eventId)->orWhere('ec.current_event_id',$eventId));
        $p=$q->orderByRaw("CASE WHEN rr.validation_status = 'needs_validation' THEN 0 WHEN rr.validation_status = 'verified' THEN 1 ELSE 2 END")->orderByDesc('rr.created_at')->paginate(\App\Http\Requests\ListRequest::clampPerPage($request->query('per_page')));return[$p,$core,$period];
    }
    private function requestQuery(){return ResourceRequest::query()->from('resource_requests as rr')->leftJoin('resource_request_status as rrs','rrs.status_id','=','rr.status_id')->leftJoin('urgency_levels as ul','ul.urgency_id','=','rr.urgency_id')->leftJoin('evacuation_centers as ec','ec.evacuation_center_id','=','rr.evacuation_center_id')->leftJoin('disaster_events as ec_event','ec_event.event_id','=','ec.current_event_id')->select(['rr.*','rrs.status_key as request_status_key','rrs.status_label as request_status_label','ul.urgency_key','ul.urgency_label','ec.name as evacuation_center_name','ec.osm_address as evacuation_center_address','ec.current_event_id as evacuation_event_id','ec_event.name as evacuation_event_name','ec_event.ended_at as evacuation_event_ended_at','ec_event.deleted_at as evacuation_event_deleted_at']);}
    public function summary(string $period='week'):array{$counts=ResourceRequest::query()->select('validation_status',DB::raw('COUNT(*) as total'))->groupBy('validation_status')->pluck('total','validation_status');$start=match($period){'month'=>Carbon::now()->startOfMonth(),'year'=>Carbon::now()->startOfYear(),default=>Carbon::now()->startOfWeek()};$today=ResourceRequest::query()->whereBetween('released_for_tracking_at',[Carbon::today(),Carbon::today()->endOfDay()])->count();return['needs_validation'=>(int)($counts['needs_validation']??0),'verified'=>(int)(($counts['verified']??0)+($counts['validated']??0)),'validated_and_forwarded'=>(int)($counts['verified']??0)+(int)($counts['forwarded']??0),'acknowledged'=>$this->trackingAid->acknowledgedRequestCount(),'forwarded_today'=>$today,'total_requests'=>(int)ResourceRequest::query()->where('created_at','>=',$start)->count(),'returned'=>(int)($counts['returned']??0),'status_ids'=>$this->statusIds(),'rows'=>[['key'=>'needs_validation','label'=>'Needs validation','status_id'=>$this->statusId('needs_validation'),'count'=>(int)($counts['needs_validation']??0)],['key'=>'verified','label'=>'Verified','status_id'=>$this->statusId('verified'),'count'=>(int)(($counts['verified']??0)+($counts['validated']??0))],['key'=>'forwarded','label'=>'Forwarded today','status_id'=>$this->statusId('forwarded'),'count'=>$today],['key'=>'returned','label'=>'Returned','status_id'=>$this->statusId('returned'),'count'=>(int)($counts['returned']??0)]]];}
    public function coreSummary(array $items,int $total):array{$c=collect($items)->countBy(fn($i)=>$i['validation']['key']??'needs_validation');return['needs_validation'=>(int)$c->get('needs_validation',0),'verified'=>(int)$c->get('verified',0),'forwarded_today'=>(int)$c->get('forwarded',0),'returned'=>(int)$c->get('returned',0),'total'=>$total,'rows'=>[]];}
    public function trackingMirror():array{$connection=(string)config('services.trackingaid.connection','trackingaid');try{if(Schema::connection($connection)->hasTable('inventory')){$items=DB::connection($connection)->table('inventory')->whereNull('deleted_at')->orderByDesc('quantity')->orderBy('name')->limit(12)->get(['id','name','category','sku','type','quantity','expiration','storage_location'])->map(fn($i)=>['label'=>$i->name?:'Unnamed resource','source'=>'TrackingAid inventory','status'=>max(0,(int)($i->quantity??0)).' available','detail'=>trim(($i->category?:'Uncategorized').' - '.($i->storage_location?:'No storage location')),'sku'=>$i->sku,'quantity'=>max(0,(int)($i->quantity??0)),'type'=>$i->type,'expiration'=>$i->expiration])->values()->all();return $items?:[['label'=>'No TrackingAid inventory yet','source'=>'TrackingAid inventory','status'=>'0 available','detail'=>'TrackingAid inventory table is reachable but has no resources recorded.']];}}catch(Throwable){}return app(ResourceRequestMirror::class)->summarize();}
    public function validationHistory(string $id):array{if(!Schema::hasTable('request_validations'))return[];return RequestValidation::query()->where('request_id',$id)->orderByDesc('created_at')->limit(8)->get()->map(fn($r)=>['validation_id'=>$r->validation_id,'validation_status'=>$this->presenter->validation($r->validation_status),'validator_user_id'=>$r->validator_user_id,'validation_notes'=>$r->validation_notes,'missing_information'=>$r->missing_information,'duplicate_request_id'=>$r->duplicate_request_id,'validated_at'=>$this->presenter->dateTime($r->validated_at)])->values()->all();}
    public function insertValidation(string $id,string $status,?string $user,?string $notes,?string $missing,?string $duplicate,Carbon $now):void{if(Schema::hasTable('request_validations'))RequestValidation::create(['request_id'=>$id,'validation_status'=>$status,'validator_user_id'=>$user,'validation_notes'=>$notes,'missing_information'=>$missing,'duplicate_request_id'=>$duplicate,'validated_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);}
    public function options():array{return['sources'=>collect(self::SOURCES)->map(fn($label,$key)=>['key'=>$key,'label'=>$label])->values()->all(),'categories'=>collect(self::CATEGORIES)->map(fn($label,$key)=>['key'=>$key,'label'=>$label])->values()->all(),'statuses'=>collect(self::STATUSES)->map(fn($label,$key)=>['key'=>$key,'label'=>$label,'status_id'=>$this->statusId($key)])->values()->all(),'urgencies'=>UrgencyLevel::query()->orderBy('urgency_id')->get(['urgency_id','urgency_key','urgency_label'])->map(fn($r)=>['urgency_id'=>$r->urgency_id,'key'=>$r->urgency_key,'label'=>$r->urgency_label])->values()->all(),'evacuation_centers'=>$this->centers(),'puroks'=>$this->puroks()];}
    private function centers():array{return EvacuationCenter::query()->from('evacuation_centers as ec')->leftJoin('disaster_events as de','de.event_id','=','ec.current_event_id')->when(Schema::hasColumn('evacuation_centers','deleted_at'),fn($q)=>$q->whereNull('ec.deleted_at'))->orderBy('ec.name')->get(['ec.evacuation_center_id','ec.name','ec.osm_address','ec.current_event_id','de.name as current_event_name','de.ended_at as current_event_ended_at','de.deleted_at as current_event_deleted_at'])->map(fn($r)=>['evacuation_center_id'=>$r->evacuation_center_id,'name'=>$r->name?:$r->evacuation_center_id,'address'=>$r->osm_address,'current_event_id'=>$this->presenter->evacuationEventStatus($r->current_event_id,$r->current_event_ended_at,$r->current_event_deleted_at)==='active'?$r->current_event_id:null,'current_event_name'=>$this->presenter->evacuationEventStatus($r->current_event_id,$r->current_event_ended_at,$r->current_event_deleted_at)==='active'?$r->current_event_name:null,'current_event_status'=>$this->presenter->evacuationEventStatus($r->current_event_id,$r->current_event_ended_at,$r->current_event_deleted_at)])->values()->all();}
    private function puroks():array{return DB::table('addresses')->whereNotNull('purok_sitio')->where('purok_sitio','<>','')->select('purok_sitio')->distinct()->orderBy('purok_sitio')->pluck('purok_sitio')->values()->all();}
    public function statusId(string $status):?int{$key=match($this->presenter->statusKey($status)){'verified'=>'approved','forwarded'=>'acknowledged','returned','cancelled'=>'rejected','fulfilled'=>'delivered',default=>'pending'};$this->statusKeyIds ??= ResourceRequestStatus::query()->pluck('status_id','status_key')->map(fn($id)=>(int)$id)->all();return $this->statusKeyIds[$key]??null;}
    public function urgencyId(string $key):?int{return UrgencyLevel::query()->where('urgency_key',$key)->value('urgency_id');}
    private function statusIds():array{return['needs_validation'=>$this->statusId('needs_validation'),'verified'=>$this->statusId('verified'),'forwarded'=>$this->statusId('forwarded'),'returned'=>$this->statusId('returned'),'fulfilled'=>$this->statusId('fulfilled'),'cancelled'=>$this->statusId('cancelled')];}
    private function periodKey(string $p):string{return in_array(strtolower(trim($p)),['week','month','year'],true)?strtolower(trim($p)):'week';}
}



