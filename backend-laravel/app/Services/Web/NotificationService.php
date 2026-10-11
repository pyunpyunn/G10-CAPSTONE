<?php

namespace App\Services\Web;

use App\Presenters\NotificationPresenter;
use App\Queries\NotificationQuery;
use App\Services\Shared\RealtimeReadCache;
use Illuminate\Http\Request;

class NotificationService
{
    public function __construct(
        private NotificationQuery $query,
        private NotificationPresenter $presenter,
        private NotificationSavedView $savedView,
        private NotificationWorkflow $workflow,
    ) {}

    public function index(Request $request): array
    {
        $status = $this->presenter->statusFilter((string) $request->query('status','all'));
        $allItems = $this->items($request);
        $filtered = $this->savedView->filter($allItems,$status);
        return ['items' => $allItems, 'filtered' => $filtered, 'status' => $status, 'page' => (int) $request->query('page', 1)];
    }

    public function markRead(Request $request): void
    {
        $validated=$request->validate(['notification_ids'=>['nullable','array'],'notification_ids.*'=>['string','max:120']]);
        $ids=$validated['notification_ids']??[];
        if (count($ids)===0) $ids=$this->items($request)->pluck('id')->all();
        $this->workflow->markRead($request,$ids);
    }

    public function deleteSelected(Request $request): array
    {
        $validated=$request->validate(['notification_ids'=>['required','array','min:1'],'notification_ids.*'=>['string','max:120']],
            ['notification_ids.required'=>'Select at least one notification to delete.','notification_ids.min'=>'Select at least one notification to delete.']);
        $this->workflow->deleteSelected($request,$validated['notification_ids']);
        return $validated['notification_ids'];
    }

    public function clearAll(Request $request): void
    {
        $this->workflow->clearAll($request,$this->items($request)->pluck('id')->all());
    }

    private function items(Request $request)
    {
        // Cache only presented arrays; apply each user's read/hidden state after the shared read.
        $rows = app(RealtimeReadCache::class)->remember('notification-feed:v2',
            fn () => $this->presenter->feed($this->query->feedSources())->all());
        $items = collect($rows);
        return $this->savedView->apply($items,$this->savedView->state($this->query->savedViewRows($request), $items->pluck('id')->all()));
    }
}







