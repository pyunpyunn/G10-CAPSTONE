<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\RequestSchema as Schema;

class NotificationWorkflow
{
    public function markRead(Request $request,array $ids): JsonResponse
    {
        $this->audit($request,'mark_read',['selected_ids'=>$ids]);
        return response()->json(['message'=>'Notifications marked as read in the current HQ view.','data'=>['action'=>'mark_read']]);
    }

    public function deleteSelected(Request $request,array $ids): JsonResponse
    {
        $this->audit($request,'delete_selected',['selected_ids'=>$ids]);
        return response()->json(['message'=>count($ids).' notification(s) hidden from the current HQ view.','data'=>['action'=>'delete_selected','notification_ids'=>$ids]]);
    }

    public function clearAll(Request $request,array $hiddenIds): JsonResponse
    {
        $this->audit($request,'clear_all',['scope'=>'current_hq_view','hidden_ids'=>$hiddenIds]);
        return response()->json(['message'=>'All notifications cleared from the current HQ view.','data'=>['action'=>'clear_all']]);
    }

    private function audit(Request $request,string $action,array $values): void
    {
        if (!Schema::hasTable('audit_logs')) return;
        AuditLog::query()->create(['user_id'=>$request->user()?->user_id,'role_key'=>$request->user()?->role?->role_key,
            'module'=>'notifications','action'=>$action,'reference_table'=>'notifications','reference_id'=>'hq-view',
            'old_values'=>null,'new_values'=>$values,'ip_address'=>$request->ip(),
            'user_agent'=>substr((string)$request->userAgent(),0,255),'created_at'=>now()]);
    }
}







