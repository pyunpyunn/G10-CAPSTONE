<?php

namespace App\Services\Web;

use App\Presenters\ResourceRequestPresenter;
use App\Queries\ResourceRequestQuery;
use App\Models\ResourceRequestType;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResourceRequestPayloadValidator
{
    private const SOURCES=['evatrack','field_team','evacuation_site','hq_desk','household_mobile','rescuer_mobile','shared_db'];
    private const STATUSES=['needs_validation','verified','forwarded','returned','fulfilled','cancelled'];
    public function __construct(private ResourceRequestQuery $query,private ResourceRequestPresenter $presenter){}
    public function request(Request $request):array
    {
        $rules = [
            'request_source'=>['required',Rule::in(self::SOURCES)],'source_reference'=>['nullable','string','max:120'],
            'category'=>['required_without_all:request_category,resource_type_id','nullable','string','max:30'],
            'request_category'=>['required_without_all:category,resource_type_id','nullable','string','max:30'],
            'evacuation_center_id'=>['nullable','string','max:255'],
            'requested_by'=>['required','string','max:255'],
            'resource_type_id'=>['nullable','integer'],
            'resource_type'=>['required_without:resource_type_id','nullable','string','max:100'],
            'item_name'=>['nullable','string','max:150'],'sku'=>['nullable','string','max:100'],'item_id'=>['nullable'],
            'quantity'=>['required','integer','min:1','max:100000'],'unit'=>['nullable','string','max:50'],
            'urgency_id'=>['nullable','integer','min:1'],'description'=>['nullable','string','max:2000'],
            'destination'=>['nullable','string','max:255'],
            'validation_notes'=>['nullable','string','max:2000'],'validation_status'=>['nullable','string',Rule::in(self::STATUSES)],
        ];
        if (Schema::hasTable('resource_request_types')) {
            $rules['resource_type_id'][] = Rule::exists('resource_request_types', 'type_id');
        }

        $validated = $request->validate($rules,[
            'request_source.required'=>'Request source is required.',
            'request_source.in'=>'Select a valid request source.',
            'category.required_without_all'=>'Request type is required.',
            'request_category.required_without_all'=>'Request type is required.',
            'requested_by.required'=>'Requester or contact person is required.',
            'resource_type.required_without'=>'Need/category is required.',
            'quantity.required'=>'Quantity is required.',
            'quantity.integer'=>'Quantity must be a whole number.',
            'quantity.min'=>'Quantity must be at least 1.',
        ]);
        $validated['category'] = $validated['category'] ?? $validated['request_category'] ?? null;
        unset($validated['request_category']);

        if (! empty($validated['resource_type_id']) && Schema::hasTable('resource_request_types')) {
            $type = ResourceRequestType::query()->find($validated['resource_type_id']);
            if (! $type) {
                throw ValidationException::withMessages([
                    'resource_type_id' => 'Select a resource type that is still available.',
                ]);
            }

            $validated['category'] = $type->category ?: $validated['category'];
            $validated['resource_type'] = $type->resource_type ?: $type->type_label ?: $type->item_name ?: ($validated['resource_type'] ?? null);
            $validated['item_name'] = $type->item_name ?: ($validated['item_name'] ?? null);
            $validated['unit'] = $type->unit ?: ($validated['unit'] ?? 'units');

            if (trim((string) $validated['category']) === '') {
                throw ValidationException::withMessages([
                    'category' => 'The selected resource type has no category.',
                ]);
            }
            if (trim((string) $validated['resource_type']) === '') {
                throw ValidationException::withMessages([
                    'resource_type' => 'The selected resource type has no resource description.',
                ]);
            }
        }

        return $validated;
    }
    public function decision(Request $request):array{return$request->validate(['validation_status'=>['required',Rule::in(self::STATUSES)],'validation_notes'=>['nullable','string','max:2000'],'missing_information'=>['nullable','string','max:2000'],'duplicate_request_id'=>['nullable','string','max:255']],['validation_status.required'=>'Validation decision is required.','validation_status.in'=>'Select a valid validation decision.','validation_notes.max'=>'Validation notes must be shorter.','missing_information.max'=>'Missing information note must be shorter.']);}
}







