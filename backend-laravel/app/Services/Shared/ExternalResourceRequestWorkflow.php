<?php

namespace App\Services\Shared;

use App\Models\ResourceRequest;
use App\Presenters\ResourceRequestPresenter;
use App\Queries\ResourceRequestQuery;
use App\Services\Web\ResourceRequestWriteWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExternalResourceRequestWorkflow
{
    public function __construct(private ResourceRequestQuery $query, private ResourceRequestPresenter $presenter, private ResourceRequestWriteWorkflow $writes) {}

    public function store(\Illuminate\Http\Request $request,array $v,array $n,ResourceRequestWriteWorkflow $writes):array
    {
        return \Illuminate\Support\Facades\DB::transaction(function()use($request,$v,$n,$writes):array{$source=$n['request_source'];$reference=$n['source_reference'];$existing=\App\Models\ResourceRequest::query()->where('request_source',$source)->where('source_reference',$reference)->first();$current=$this->presenter->statusKey($existing?->validation_status??'needs_validation');$status=in_array($current,['forwarded','fulfilled','cancelled'],true)?$current:'needs_validation';$id=$existing?->request_id?:$this->query->nextRequestId();$row=['request_source'=>$source,'source_reference'=>$reference,'request_category'=>$n['request_category'],'evacuation_center_id'=>$v['evacuation_center_id']??null,'requested_by'=>$v['requested_by'],'resource_type'=>$n['resource_type'],'item_name'=>$n['item_name'],'quantity'=>(int)$v['quantity'],'unit'=>$v['unit']??'units','description'=>$n['description'],'urgency_id'=>$n['urgency_id'],'status_id'=>$this->query->statusId($status),'validation_status'=>$status,'updated_at'=>now()];if($existing){\App\Models\ResourceRequest::query()->where('request_id',$id)->update($row);if($existing->tracking_reference)app(\App\Services\Shared\TrackingAidForwardingService::class)->syncRequestStatus($id,$status,now());}else{\App\Models\ResourceRequest::create(array_merge($row,['request_id'=>$id,'handled_by'=>null,'validation_notes'=>null,'validated_by_user_id'=>null,'validated_at'=>null,'released_for_tracking_at'=>null,'tracking_reference'=>null,'created_at'=>$n['created_at']?:now()]));}$result=$this->presenter->format($this->query->find($id),true,$this->query->validationHistory($id));$writes->auditExternal($request,$existing?'external_update':'external_intake',$id,$result);return['request'=>$result,'created'=>!$existing];}, 3);
    }
    public function normalize(array $payload): array
    {
        $type=$this->resourceType($payload);if($type==='')throw ValidationException::withMessages(['requested_item'=>'Request item or request type is required.']);$priority=strtolower(str_replace([' ','-'],'_',trim((string)($payload['priority']??$payload['urgency']??''))));$urgency=match($priority){'critical','urgent','emergency'=>'critical','high'=>'high','low'=>'low',default=>'medium'};
        return ['request_source'=>$this->sourceKey($payload),'source_reference'=>$this->sourceReference($payload),'request_category'=>$this->categoryKey($payload),'resource_type'=>$type,'item_name'=>$payload['requested_item']??$payload['item_name']??$type,'description'=>$this->description($payload),'urgency_id'=>$this->query->urgencyId($urgency)?:$this->query->urgencyId('medium'),'created_at'=>empty($payload['requested_at'])?null:Carbon::parse($payload['requested_at'])];
    }
    public function validatePayload(\Illuminate\Http\Request $request):array{return $request->validate(['external_request_id'=>['nullable','string','max:120'],'request_id'=>['nullable','string','max:120'],'source_reference'=>['nullable','string','max:120'],'source_system'=>['nullable','string','max:80'],'request_source'=>['nullable','string','max:80'],'request_type'=>['nullable','string','max:100'],'request_category'=>['nullable','string','max:80'],'requested_item'=>['nullable','string','max:150'],'resource_type'=>['nullable','string','max:100'],'item_name'=>['nullable','string','max:150'],'quantity'=>['required','integer','min:1','max:100000'],'unit'=>['nullable','string','max:50'],'priority'=>['nullable','string','max:50'],'urgency'=>['nullable','string','max:50'],'location_name'=>['nullable','string','max:255'],'evacuation_center_id'=>['nullable','string','max:255'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'requested_by'=>['required','string','max:255'],'contact_number'=>['nullable','string','max:80'],'notes'=>['nullable','string','max:2000'],'description'=>['nullable','string','max:2000'],'requested_at'=>['nullable','date']],['quantity.required'=>'Quantity is required.','requested_by.required'=>'Requester or contact person is required.']);}
    private function sourceKey(array $p):string{$source=str_replace([' ','-'],'_',strtolower(trim((string)($p['request_source']??$p['source_system']??'evatrack'))));return match(true){str_contains($source,'eva')=>'evatrack',str_contains($source,'evac')=>'evacuation_site',str_contains($source,'rescue'),str_contains($source,'field')=>'field_team',str_contains($source,'household')=>'household_mobile',str_contains($source,'hq'),str_contains($source,'resq')=>'hq_desk',default=>$this->presenter->sourceKey($source)};}
    private function sourceReference(array $p):string{$ref=trim((string)($p['external_request_id']??$p['request_id']??$p['source_reference']??''));return$ref!==''?$ref:'EXT-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));}
    private function categoryKey(array $p):string{$c=strtolower(trim((string)($p['request_category']??$p['request_type']??'resource')));return match(true){str_contains($c,'person'),str_contains($c,'responder'),str_contains($c,'medical')=>'personnel',str_contains($c,'vehicle'),str_contains($c,'transport'),str_contains($c,'ambulance')=>'vehicle',default=>'resource'};}
    private function resourceType(array $p):string{return trim((string)($p['resource_type']??$p['requested_item']??$p['item_name']??$p['request_type']??''));}
    private function description(array $p):?string{$parts=[];foreach(['description','notes','location_name','contact_number']as$key){$v=trim((string)($p[$key]??''));if($v!=='')$parts[]=ucfirst(str_replace('_',' ',$key)).': '.$v;}if(isset($p['latitude'],$p['longitude']))$parts[]='Coordinates: '.$p['latitude'].', '.$p['longitude'];if(!empty($p['requested_at']))$parts[]='Requested at source: '.Carbon::parse($p['requested_at'])->toDateTimeString();return$parts?implode("\n",$parts):null;}
}







