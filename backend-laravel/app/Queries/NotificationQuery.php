<?php

namespace App\Queries;

use App\Models\AuditLog;
use App\Models\DisasterBroadcast;
use App\Models\ResourceRequest;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;

class NotificationQuery
{
    public function feedSources(): array
    {
        // Eloquent collections and their models must stay outside the shared serialized cache.
        $sources = $this->sourceRecords();
        app(\App\Services\Web\NotificationArchive::class)->sync($sources);
        return $sources;
    }

    public function sourceRecords(bool $history = false): array
    {
        return [
            'advisories' => Schema::hasColumn('notifications', 'advisory_fingerprint') ? \Illuminate\Support\Facades\DB::table('notifications')->whereNotNull('advisory_fingerprint')->whereNotNull('created_at')->select(['notif_id as id', 'advisory_source as source', 'advisory_fingerprint as fingerprint', 'advisory_title as title', 'message as body', 'advisory_source_url as source_url', 'created_at'])->orderByDesc('created_at')->when(! $history, fn ($q) => $q->limit(100))->get() : collect(),
            'requests' => Schema::hasTable('resource_requests') ? ResourceRequest::query()->whereNotNull('created_at')->where(fn ($q) => $q->whereNull('validation_status')->orWhereIn('validation_status', ['needs_validation', 'pending']))->select(['request_id', 'request_source', 'source_reference', 'request_category', 'evacuation_center_id', 'requested_by', 'handled_by', 'resource_type', 'item_name', 'quantity', 'unit', 'description', 'status_id', 'validation_status', 'validation_notes', 'validated_by_user_id', 'validated_at', 'released_for_tracking_at', 'tracking_reference', 'created_at', 'updated_at'])->orderByDesc('created_at')->get() : collect(),
            'broadcasts' => Schema::hasTable('disaster_broadcasts') ? DisasterBroadcast::query()->with('severity')->whereNotNull('sent_at')->orderByDesc('sent_at')->when(! $history, fn ($q) => $q->limit(200))->get() : collect(),
            'audit' => Schema::hasTable('audit_logs') ? AuditLog::query()->whereNotNull('created_at')->where('reference_table', 'responder_assignments')->whereIn('module', ['rescue_dispatch', 'rescuer_mobile'])->whereIn('action', ['create_dispatch', 'update_dispatch', 'complete_dispatch', 'mobile_update_assignment'])->orderByDesc('created_at')->when(! $history, fn ($q) => $q->limit(200))->get() : collect(),
        ];
    }

    public function savedViewRows(Request $request): ?LazyCollection
    {
        if (! Schema::hasTable('audit_logs') || ! $request->user()) {
            return null;
        }

        return AuditLog::query()->where('user_id', $request->user()->user_id)->where('module', 'notifications')
            ->whereIn('action', ['mark_read', 'delete_selected', 'clear_all'])
            ->select(['audit_log_id', 'action', 'new_values', 'created_at'])->lazyById(500, 'audit_log_id');
    }
}
