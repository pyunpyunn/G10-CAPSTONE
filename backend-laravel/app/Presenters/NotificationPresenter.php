<?php

namespace App\Presenters;

use App\Models\HouseholdStatusLog;
use App\Models\Notification;
use App\Models\ResourceRequest;
use App\Models\ResponderAssignment;
use App\Models\WeatherLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationPresenter
{
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
            'preview' => $allItems->take(5)->values()->all(),
            'status_filter' => $status,
            'scope_note' => 'HQ/Admin notification actions hide or mark notices in the current web view only. Delivery logs stay unchanged for audit.',
        ];
    }

    public function feed(array $sources): Collection
    {
        return collect()
            ->merge($sources['outgoing']->map(fn (Notification $row): array => $this->outgoing($row)))
            ->merge($sources['household']->map(fn (HouseholdStatusLog $row): array => $this->household($row)))
            ->merge($sources['dispatch']->map(fn (ResponderAssignment $row): array => $this->dispatch($row)))
            ->merge($sources['requests']->map(fn (ResourceRequest $row): array => $this->request($row)))
            ->merge($sources['weather']->map(fn (WeatherLog $row): array => $this->weather($row)))
            ->merge($sources['broadcasts']->map(fn ($row): array => $this->broadcast($row)))
            ->merge($sources['audit']->map(fn ($row): array => $this->audit($row)))
            ->sortByDesc('sort_time')->values()->take(80);
    }

    public function statusFilter(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['all','read','unread'], true) ? $status : 'all';
    }

    private function outgoing(Notification $row): array
    {
        return $this->notice('notif-'.$row->notif_id, 'Broadcast', $this->priorityFromUrgency($row->urgency_level_id),
            $this->notificationTitle($row), $row->message ?: 'Notification message saved in shared DB.', $row->created_at,
            in_array($row->status, ['sent','cancelled','completed'], true));
    }

    private function household(HouseholdStatusLog $row): array
    {
        $purok = $row->household?->address?->purok_sitio ?: 'unassigned area';
        $name = $row->household?->household_name ?: 'Household';
        $status = $row->status?->status_label ?: 'Unsafe';
        return $this->notice('household-'.$row->status_log_id, 'Household status', 'Critical', $status.' household report from '.$purok,
            $name.' reported '.$status.'. '.($row->notes ?: $row->location_label ?: 'HQ review is required.'), $row->submitted_at, (bool) $row->reviewed_at);
    }

    private function dispatch(ResponderAssignment $row): array
    {
        $team = $row->team?->team_name ?: $row->responder?->full_name ?: 'Responder team';
        $priority = in_array($row->priority_level, ['high','critical','urgent'], true) ? 'High' : 'Medium';
        $read = in_array($row->status, ['completed','cancelled'], true) || (bool) $row->completed_at;
        return $this->notice('dispatch-'.$row->assignment_id, 'Dispatch route', $priority, $team.' route updated',
            $this->label($row->status ?: 'assigned').' to '.($row->assigned_area ?: 'assigned area').'.', $row->assigned_at, $read);
    }

    private function request(ResourceRequest $row): array
    {
        $priority = $row->validation_status === 'needs_validation' ? 'Medium' : 'Normal';
        $item = $row->item_name ?: $row->resource_type ?: 'Request';
        $read = ! in_array($row->validation_status, ['needs_validation','returned'], true);
        return $this->notice('request-'.$row->request_id, 'Resources', $priority, $item.' request '.$this->label($row->validation_status ?: 'needs_validation'),
            ($row->requested_by ?: 'Requester').' requested '.trim(($row->quantity ?? '').' '.($row->unit ?? '')).'.', $row->created_at, $read);
    }

    private function weather(WeatherLog $row): array
    {
        $text = strtolower(trim(($row->condition_name ?? '').' '.($row->advisory_title ?? '').' '.($row->advisory_text ?? '')));
        $critical = str_contains($text, 'storm') || str_contains($text, 'heavy') || str_contains($text, 'flood');
        return $this->notice('weather-'.$row->weather_log_id, 'Weather', $critical ? 'High' : 'Normal',
            $row->advisory_title ?: ($row->condition_name ?: 'Weather snapshot saved'),
            $row->advisory_text ?: 'Weather data was saved from '.$row->source_name.'. Confirm official warnings through PAGASA before broadcasting.',
            $row->observed_at ?: $row->created_at, ! $critical);
    }

    private function broadcast($row): array
    {
        $severity = $row->severity?->severity_key;
        $priority = in_array($severity, ['high','critical','severe'], true) ? 'Critical' : 'High';
        return $this->notice('broadcast-'.$row->broadcast_id, 'Broadcast', $priority, $row->broadcast_title ?: 'Disaster broadcast saved',
            ($row->message ?: 'Broadcast message saved.').' Scope: '.$this->label($row->scope_type ?: 'barangay'), $row->sent_at, true);
    }

    private function audit($row): array
    {
        return $this->notice('audit-'.$row->audit_log_id, 'System activity', 'Normal', $this->label($row->module).' '.$this->label($row->action),
            'Action recorded for '.$row->reference_table.' #'.$row->reference_id.'.', $row->created_at, true);
    }

    private function notice(string $id,string $type,string $priority,string $title,string $body,mixed $time,bool $read): array
    {
        $date = $time ? Carbon::parse($time) : now();
        return ['id'=>$id,'type'=>$type,'priority'=>$priority,'title'=>$title,'body'=>$body,'date'=>$this->dateLabel($date),
            'time'=>$date->format('g:i A'),'created_at'=>$date->toDateTimeString(),'sort_time'=>$date->timestamp,'read'=>$read,
            'tone'=>$this->priorityTone($priority),'action_url'=>$this->actionUrlForType($type)];
    }

    private function actionUrlForType(string $type): string
    {
        return match ($type) {'Broadcast'=>'/broadcast','Household status'=>'/households','Dispatch route'=>'/dispatch','Resources'=>'/resources','Weather'=>'/weather',default=>'/notifications'};
    }
    private function priorityFromUrgency(mixed $id): string { return (int)$id >= 4 ? 'Critical' : ((int)$id >= 3 ? 'High' : ((int)$id >= 2 ? 'Medium' : 'Normal')); }
    private function priorityTone(string $priority): string { return match($priority) {'Critical'=>'red','High'=>'amber','Medium'=>'blue',default=>'gray'}; }
    private function notificationTitle(Notification $row): string { return ($row->status ? $this->label($row->status) : 'Saved').' notification'.($row->channel ? ' via '.$this->label($row->channel) : ''); }
    private function dateLabel(Carbon $date): string { return $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('M d, Y')); }
    private function label(?string $value): string { return ucwords(str_replace(['_','-'],' ',$value ?? 'Unknown')); }
}


