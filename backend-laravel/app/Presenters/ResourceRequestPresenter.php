<?php

namespace App\Presenters;

use App\Services\Shared\TrackingAidForwardingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ResourceRequestPresenter
{
    private const SOURCES = ['evatrack'=>'EvaTrack','field_team'=>'Field team','evacuation_site'=>'Evacuation site','hq_desk'=>'HQ desk','household_mobile'=>'Household mobile','rescuer_mobile'=>'Rescuer mobile','shared_db'=>'EvaTrack request'];
    private const CATEGORIES = ['resource'=>'Resource','personnel'=>'Personnel','vehicle'=>'Vehicle / transport'];
    private const STATUSES = ['needs_validation'=>'Pending','verified'=>'Validated','forwarded'=>'In Progress','returned'=>'Returned','fulfilled'=>'Completed','cancelled'=>'Cancelled'];

    public function __construct(private TrackingAidForwardingService $trackingAid) {}

    public function format(?object $row, bool $details = false, array $history = [], ?array $handoffStatuses = null): ?array
    {
        if (!$row) return null;
        $validation=$this->validation($row->validation_status??'needs_validation');$quantity=trim((string)($row->quantity??'').' '.(string)($row->unit??''));$area=$row->evacuation_center_name?:$row->evacuation_center_id?:'Area not recorded';$handoff=$this->handoff($row,$handoffStatuses);$key=$this->statusKey($row->validation_status??'');$status=$row->request_status_label?:'Pending';if(in_array($key,['verified','forwarded'],true))$status='Validated';if($handoff['label']==='Acknowledged')$status='Acknowledged';
        $data=['request_id'=>$row->request_id,'request_source'=>['key'=>$this->sourceKey($row->request_source??''),'label'=>$this->sourceLabel($row->request_source??'')],'source_system'=>$this->sourceSystem($row->request_source??''),'source_reference'=>$row->source_reference,'request_category'=>['key'=>$this->categoryKey($row->request_category??''),'label'=>$this->categoryLabel($row->request_category??'')],'need'=>['type'=>$row->resource_type?:$row->item_name?:'Request','item_name'=>$row->item_name,'quantity'=>(int)($row->quantity??0),'unit'=>$row->unit,'quantity_text'=>$quantity!==''?$quantity:'No quantity'],'area'=>['label'=>$area,'meta'=>$row->evacuation_center_address?:$row->description?:'No beneficiary note recorded','evacuation_center_id'=>$row->evacuation_center_id,'event'=>['event_id'=>$row->evacuation_event_id,'name'=>$row->evacuation_event_name,'status'=>$this->evacuationEventStatus($row->evacuation_event_id,$row->evacuation_event_ended_at,$row->evacuation_event_deleted_at)]],'requested_by'=>$row->requested_by?:'Requester not recorded','handled_by'=>$row->handled_by,'description'=>$row->description,'urgency'=>['key'=>$row->urgency_key?:'medium','label'=>$row->urgency_label?:'Medium'],'validation'=>$validation,'handoff'=>$handoff,'status'=>['key'=>$row->request_status_key?:'pending','label'=>$status],'validation_notes'=>$row->validation_notes,'validated_by_user_id'=>$row->validated_by_user_id,'validated_at'=>$this->dateTime($row->validated_at),'created_at'=>$this->dateTime($row->created_at),'created_time'=>$this->time($row->created_at),'released_for_tracking_at'=>$this->dateTime($row->released_for_tracking_at),'tracking_reference'=>$row->tracking_reference];if($details)$data['validation_history']=$history;return$data;
    }
    public function statusKey(?string $status):string{$key=str_replace([' ','-'],'_',strtolower(trim((string)$status)));return match($key){'validated','approved','verify'=>'verified','return_for_info','rejected','duplicate'=>'returned','pending',''=>'needs_validation',default=>$key};}
    public function sourceKey(?string $source):string{$key=str_replace([' ','-'],'_',strtolower(trim((string)$source)));return array_key_exists($key,self::SOURCES)?$key:($key?:'shared_db');}
    public function categoryKey(?string $category):string{$key=str_replace([' ','-'],'_',strtolower(trim((string)$category)));return array_key_exists($key,self::CATEGORIES)?$key:($key?:'resource');}
    public function sourceLabel(?string $source):string{$key=$this->sourceKey($source);return self::SOURCES[$key]??$this->label($source);}
    public function sourceSystem(?string $source):array{$evatrack=in_array($this->sourceKey($source),['shared_db','evatrack','evacuation_site'],true);return['key'=>$evatrack?'evatrack':'resqperation','label'=>$evatrack?'EvaTrack':'ResQperation'];}
    public function categoryLabel(?string $category):string{$key=$this->categoryKey($category);return self::CATEGORIES[$key]??$this->label($category);}
    public function validation(?string $status):array{$key=$this->statusKey($status?:'needs_validation');return['key'=>$key,'label'=>self::STATUSES[$key]??$this->label($key),'tone'=>match($key){'verified','fulfilled'=>'green','forwarded'=>'blue','returned','cancelled'=>'red',default=>'amber'}];}
    public function handoff(object $row, ?array $handoffStatuses = null):array{$tracking=$handoffStatuses!==null?($handoffStatuses[$row->request_id]??null):(DB::transactionLevel()>0?null:$this->trackingAid->requestHandoffStatus($row->request_id,$row->tracking_reference));$trackingStatus=$tracking['status']??null;if(in_array($trackingStatus,['acknowledged','received','received_by_trackingaid'],true)&&$tracking)return['label'=>'Acknowledged','tone'=>'green','tracking_reference'=>$tracking['tracking_reference'],'meta'=>($tracking['updated_at']??null)?'acknowledged '.$this->time($tracking['updated_at']):null];$status=$this->statusKey($row->validation_status??'needs_validation');if(in_array($status,['verified','forwarded'],true)||$row->tracking_reference)return['label'=>'Ready','tone'=>'green','tracking_reference'=>null,'meta'=>'ready for TrackingAid'];return['label'=>'Not forwarded','tone'=>'gray','tracking_reference'=>null,'meta'=>null];}
    public function evacuationEventStatus(?string $id,?string $ended,?string $deleted):string{return !$id?'none':($ended||$deleted?'closed':'active');}
    public function activeEvent(?object $event):?array{return !$event?null:['event_id'=>$event->event_id,'name'=>$event->name,'type'=>$event->type_name??'Disaster event','severity'=>$event->severity_label??'Unspecified','severity_key'=>$event->severity_key??'medium','started_at'=>$this->dateTime($event->started_at)];}
    public function dateTime(?string $value):?string{return$value?Carbon::parse($value)->format('M d, Y g:i A'):null;}
    public function time(?string $value):?string{return$value?Carbon::parse($value)->format('g:i A'):null;}
    private function label(?string $value):string{return ucwords(str_replace(['_','-'],' ',$value??'Unknown'));}
}


