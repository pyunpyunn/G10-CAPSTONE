<?php

namespace App\Queries;

use App\Models\AuditLog;
use App\Models\DisasterBroadcast;
use App\Models\HouseholdStatusLog;
use App\Models\Notification;
use App\Models\ResourceRequest;
use App\Models\ResponderAssignment;
use App\Models\WeatherLog;
use App\Services\Shared\RealtimeReadCache;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;

class NotificationQuery
{
    public function feedSources(): array
    {
        return app(RealtimeReadCache::class)->remember('notification-sources', fn () => $this->uncachedFeedSources());
    }

    private function uncachedFeedSources(): array
    {
        return [
            'outgoing' => Schema::hasTable('notifications') ? Notification::query()->select(['notif_id', 'message', 'sent_by', 'evacuation_event_id', 'evacuation_center_id', 'urgency_level_id', 'scheduled_at', 'is_recurring', 'recurrence_type_id', 'recurrence_end_at', 'last_sent_at', 'created_at', 'channel', 'status', 'target_filter'])->orderByDesc('created_at')->limit(20)->get() : collect(),
            'household' => Schema::hasTable('household_status_logs') ? HouseholdStatusLog::query()->with(['household.address', 'status'])->whereHas('status', fn ($q) => $q->whereIn('status_key', ['unsafe', 'injured', 'missing', 'not_evacuated', 'displaced']))->orderByDesc('submitted_at')->limit(20)->get() : collect(),
            'dispatch' => Schema::hasTable('responder_assignments') ? ResponderAssignment::query()->with(['team', 'responder'])->orderByDesc('assigned_at')->limit(20)->get() : collect(),
            'requests' => Schema::hasTable('resource_requests') ? ResourceRequest::query()->select(['request_id', 'request_source', 'source_reference', 'request_category', 'evacuation_center_id', 'requested_by', 'handled_by', 'resource_type', 'item_name', 'quantity', 'unit', 'description', 'urgency_id', 'status_id', 'validation_status', 'validation_notes', 'validated_by_user_id', 'validated_at', 'released_for_tracking_at', 'tracking_reference', 'created_at', 'updated_at'])->orderByDesc('created_at')->limit(20)->get() : collect(),
            'weather' => Schema::hasTable('weather_logs') ? WeatherLog::query()->select(['weather_log_id', 'disaster_id', 'source_name', 'source_url', 'condition_name', 'temperature', 'rainfall_mm', 'wind_speed', 'wind_direction', 'humidity', 'advisory_title', 'advisory_text', 'raw_payload', 'observed_at', 'created_at', 'updated_at'])->orderByDesc('created_at')->orderByDesc('observed_at')->limit(10)->get() : collect(),
            'broadcasts' => Schema::hasTable('disaster_broadcasts') ? DisasterBroadcast::query()->with('severity')->orderByDesc('sent_at')->limit(20)->get() : collect(),
            'audit' => Schema::hasTable('audit_logs') ? AuditLog::query()->where('module', '<>', 'notifications')->orderByDesc('created_at')->limit(12)->get() : collect(),
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
