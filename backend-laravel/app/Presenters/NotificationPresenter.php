<?php

namespace App\Presenters;

use App\Models\ResourceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationPresenter
{
    public const WEB_TYPES = ['Broadcast', 'Dispatch status', 'Dispatch log', 'Approval request', 'PAGASA advisory sync', 'PHIVOLCS advisory sync'];
    public function workspace(array $feed): array
    {
        $allItems = $feed['items'];
        $filtered = $feed['filtered'];
        $status = $feed['status'];
        $pageSize = 5;
        $pageCount = max(1, (int) ceil($filtered->count() / $pageSize));
        $page = min(max(1, $feed['page']), $pageCount);

        return [
            'summary' => [
                'total' => $allItems->count(),
                'unread' => $allItems->where('read', false)->count(),
                'critical' => $allItems->filter(fn (array $item): bool => in_array($item['priority'], ['Critical', 'High'], true))->count(),
                'selected' => 0,
            ],
            'notifications' => [
                'data' => $filtered->slice(($page - 1) * $pageSize, $pageSize)->values()->all(),
                'current_page' => $page,
                'per_page' => $pageSize,
                'total' => $filtered->count(),
                'page_count' => $pageCount,
            ],
            'preview' => $allItems->values()->all(),
            'status_filter' => $status,
            'scope_note' => 'Notification actions affect only your role-scoped view. Delivery logs stay unchanged for audit.',
        ];
    }

    public function feed(array $sources): Collection
    {
        return collect()
            ->merge(($sources['advisories'] ?? collect())->map(fn ($row): array => $this->advisory($row)))

            ->merge($sources['requests']->filter(fn ($row) => in_array($row->validation_status, [null, '', 'needs_validation', 'pending'], true))->map(fn (ResourceRequest $row): array => $this->request($row)))

            ->merge($sources['broadcasts']->map(fn ($row): array => $this->broadcast($row)))
            ->merge($sources['audit']->filter(fn ($row) => $row->reference_table === 'responder_assignments' && in_array($row->module, ['rescue_dispatch', 'rescuer_mobile'], true) && in_array($row->action, ['create_dispatch', 'update_dispatch', 'complete_dispatch', 'mobile_update_assignment'], true))->map(fn ($row): array => $this->audit($row)))
            ->filter(fn (array $item) => $item['sort_time'] > 0)
            ->sortByDesc('sort_time')->values();
    }

    public function forUser(Collection $items, User $user): Collection
    {
        $modules = app(\App\Services\Web\WebAccessPolicy::class)->modulesFor($user);
        $roleKey = $user->roleKey();

        return $items->filter(function (array $item) use ($modules, $roleKey, $user): bool {
            if (! in_array($item['type'], self::WEB_TYPES, true)) return false;
            if ($roleKey === 'rescuer' && $item['type'] === 'Dispatch status') {
                return in_array('dashboard', $modules, true)
                    && (string) ($item['recipient_user_id'] ?? '') === (string) $user->user_id;
            }

            return in_array($item['access_module'], $modules, true);
        })->map(function (array $item): array {
            unset($item['access_module'], $item['recipient_user_id']);
            return $item;
        })->values();
    }

    public function statusFilter(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['all','read','unread'], true) ? $status : 'all';
    }

    private function advisory($row): array
    {
        $item = $this->notice('advisory-'.$row->id, $row->source.' advisory sync', 'High', $row->title, $row->body, $row->created_at, false, 'geospatial_analytics');
        $item['source_url'] = $row->source_url;
        return $item;
    }

    private function request(ResourceRequest $row): array
    {
        $priority = $row->validation_status === 'needs_validation' ? 'Medium' : 'Normal';
        $item = $row->item_name ?: $row->resource_type ?: 'Request';
        return $this->notice('request-'.$row->request_id, 'Approval request', $priority, $item.' request '.$this->label($row->validation_status ?: 'needs_validation'),
            ($row->requested_by ?: 'Requester').' requested '.trim(($row->quantity ?? '').' '.($row->unit ?? '')).'.', $row->created_at, false, 'resources_requests');
    }

    private function broadcast($row): array
    {
        $severity = $row->severity?->severity_key;
        $priority = in_array($severity, ['high','critical','severe'], true) ? 'Critical' : 'High';
        return $this->notice('broadcast-'.$row->broadcast_id, 'Broadcast', $priority, $row->broadcast_title ?: 'Disaster broadcast saved',
            ($row->message ?: 'Broadcast message saved.').' Scope: '.$this->label($row->scope_type ?: 'barangay'), $row->sent_at, false, 'dashboard');
    }

    private function audit($row): array
    {
        $new = $this->auditValues($row->new_values ?? null);
        $old = $this->auditValues($row->old_values ?? null);
        $status = $row->action === 'complete_dispatch' ? 'completed' : ($new['status'] ?? $new['status_key'] ?? null);
        $previous = $old['status'] ?? $old['status_key'] ?? $new['previous_status'] ?? null;
        $changed = $row->action !== 'create_dispatch' && $status && $status !== $previous;
        $code = $new['assignment_code'] ?? $old['assignment_code'] ?? ('#'.$row->reference_id);
        return $this->notice('audit-'.$row->audit_log_id, $changed ? 'Dispatch status' : 'Dispatch log', 'Normal',
            'Dispatch '.$code.($changed ? ': '.$this->label($status) : ' '.$this->label($row->action)),
            $changed ? 'Rescue dispatch status changed'.($previous ? ' from '.$this->label($previous) : '').' to '.$this->label($status).'.'
                : 'Dispatch activity recorded for assignment '.$code.'.',
            $row->created_at, false, 'response_operations');
    }

    private function auditValues(mixed $values): array
    {
        // Some older workflows double-encoded these JSON columns.
        for ($i = 0; $i < 2 && is_string($values); $i++) $values = json_decode($values, true);
        return is_array($values) ? $values : [];
    }

    private function auditModule(?string $module): string
    {
        return match (strtolower(str_replace('-', '_', (string) $module))) {
            'household_status', 'mapping', 'weather' => 'geospatial_analytics',
            'dispatch', 'rescue_dispatch', 'field_reports', 'communications', 'disaster_broadcast', 'broadcast' => 'response_operations',
            'resource_request', 'resources_requests', 'requests' => 'resources_requests',
            'situation_report', 'reports', 'archive' => 'reports_documentation',
            'inquiry', 'inquiries' => 'inquiries',
            'profile' => 'profile',
            'notifications' => 'notifications',
            default => 'personnel_access',
        };
    }

    private function notice(string $id,string $type,string $priority,string $title,string $body,mixed $time,bool $read,string $accessModule,?string $recipientUserId=null): array
    {
        // Undated legacy rows are not events. Never turn them into new alerts on every read.
        $date = $time ? Carbon::parse($time) : Carbon::createFromTimestamp(0);
        return ['id'=>$id,'type'=>$type,'priority'=>$priority,'title'=>$title,'body'=>$body,'date'=>$this->dateLabel($date),
            'time'=>$date->format('g:i A'),'created_at'=>$date->toDateTimeString(),'sort_time'=>$date->timestamp,'read'=>$read,
            'tone'=>$this->priorityTone($priority),'action_url'=>$this->actionUrlForType($type),
            'access_module'=>$accessModule,'recipient_user_id'=>$recipientUserId];
    }

    private function actionUrlForType(string $type): string
    {
        return match ($type) {'Broadcast'=>'/broadcast','Household status'=>'/households','Dispatch status'=>'/rescue-management','Dispatch log'=>'/rescue-management','Approval request'=>'/resources-requests','PAGASA advisory sync'=>'/weather','PHIVOLCS advisory sync'=>'/mapping','Weather'=>'/weather',default=>'/notifications'};
    }
    private function priorityTone(string $priority): string { return match($priority) {'Critical'=>'red','High'=>'amber','Medium'=>'blue',default=>'gray'}; }
    private function dateLabel(Carbon $date): string { return $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('M d, Y')); }
    private function label(?string $value): string { return ucwords(str_replace(['_','-'],' ',$value ?? 'Unknown')); }
}


