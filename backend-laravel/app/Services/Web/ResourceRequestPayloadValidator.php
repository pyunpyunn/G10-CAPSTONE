<?php

namespace App\Services\Web;

use App\Presenters\ResourceRequestPresenter;
use App\Queries\ResourceRequestQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResourceRequestPayloadValidator
{
    private const SOURCES=['evatrack','field_team','evacuation_site','hq_desk','household_mobile','rescuer_mobile','shared_db'];
    private const CATEGORIES=['resource','personnel','vehicle'];
    private const STATUSES=['needs_validation','verified','forwarded','returned','fulfilled','cancelled'];
    public function __construct(private ResourceRequestQuery $query,private ResourceRequestPresenter $presenter){}
    public function request(Request $request):array{$ids=DB::table('urgency_levels')->pluck('urgency_id')->map(fn($id)=>(int)$id)->all();return$request->validate(['request_source'=>['required',Rule::in(self::SOURCES)],'source_reference'=>['nullable','string','max:120'],'request_category'=>['required',Rule::in(self::CATEGORIES)],'evacuation_center_id'=>['nullable','string','max:255'],'requested_by'=>['required','string','max:255'],'resource_type'=>['required','string','max:100'],'item_name'=>['nullable','string','max:150'],'quantity'=>['required','integer','min:1','max:100000'],'unit'=>['nullable','string','max:50'],'description'=>['nullable','string','max:2000'],'urgency_id'=>['nullable','integer',Rule::in($ids)]],['request_source.required'=>'Request source is required.','request_source.in'=>'Select a valid request source.','request_category.required'=>'Request type is required.','requested_by.required'=>'Requester or contact person is required.','resource_type.required'=>'Need/category is required.','quantity.required'=>'Quantity is required.','quantity.integer'=>'Quantity must be a whole number.','quantity.min'=>'Quantity must be at least 1.','urgency_id.in'=>'Select a valid urgency level.']);}
    public function decision(Request $request):array{return$request->validate(['validation_status'=>['required',Rule::in(self::STATUSES)],'validation_notes'=>['nullable','string','max:2000'],'missing_information'=>['nullable','string','max:2000'],'duplicate_request_id'=>['nullable','string','max:255']],['validation_status.required'=>'Validation decision is required.','validation_status.in'=>'Select a valid validation decision.','validation_notes.max'=>'Validation notes must be shorter.','missing_information.max'=>'Missing information note must be shorter.']);}
}







