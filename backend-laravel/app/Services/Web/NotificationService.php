<?php

namespace App\Services\Web;

use App\Presenters\NotificationPresenter;
use App\Queries\NotificationQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationService
{
    private const PAGE_SIZE = 5;

    public function __construct(
        private NotificationQuery $query,
        private NotificationPresenter $presenter,
        private NotificationSavedView $savedView,
        private NotificationWorkflow $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $this->presenter->statusFilter((string) $request->query('status','all'));
        $allItems = $this->items($request);
        $filtered = $this->savedView->filter($allItems,$status);
        $pageCount=max(1,(int)ceil($filtered->count()/self::PAGE_SIZE));
        $page=min(max(1,(int)$request->query('page',1)),$pageCount);
        return response()->json(['data'=>[
            'summary'=>['total'=>$allItems->count(),'unread'=>$allItems->where('read',false)->count(),
                'critical'=>$allItems->filter(fn(array $item):bool=>in_array($item['priority'],['Critical','High'],true))->count(),'selected'=>0],
            'notifications'=>['data'=>$filtered->slice(($page-1)*self::PAGE_SIZE,self::PAGE_SIZE)->values()->all(),
                'current_page'=>$page,'per_page'=>self::PAGE_SIZE,'total'=>$filtered->count(),'page_count'=>$pageCount],
            'preview'=>$allItems->take(5)->values()->all(),'status_filter'=>$status,
            'scope_note'=>'HQ/Admin notification actions hide or mark notices in the current web view only. Delivery logs stay unchanged for audit.',
        ]]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated=$request->validate(['notification_ids'=>['nullable','array'],'notification_ids.*'=>['string','max:120']]);
        $ids=$validated['notification_ids']??[];
        if (count($ids)===0) $ids=$this->items($request)->pluck('id')->all();
        return $this->workflow->markRead($request,$ids);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        $validated=$request->validate(['notification_ids'=>['required','array','min:1'],'notification_ids.*'=>['string','max:120']],
            ['notification_ids.required'=>'Select at least one notification to delete.','notification_ids.min'=>'Select at least one notification to delete.']);
        return $this->workflow->deleteSelected($request,$validated['notification_ids']);
    }

    public function clearAll(Request $request): JsonResponse
    {
        return $this->workflow->clearAll($request,$this->items($request)->pluck('id')->all());
    }

    private function items(Request $request)
    {
        $raw=$this->query->feedSources();
        $items=$this->presenter->feed($raw);
        return $this->savedView->apply($items,$this->savedView->state($this->query->savedViewRows($request), $items->pluck('id')->all()));
    }
}







