<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    private const PAGE_SIZE = 5;

    public function index(Request $request): JsonResponse
    {
        $status = $this->statusFilter((string) $request->query('status', 'all'));
        $page = max(1, (int) $request->query('page', 1));
        $allItems = $this->notificationFeed();
        $filteredItems = $this->filterByStatus($allItems, $status);
        $pageCount = max(1, (int) ceil($filteredItems->count() / self::PAGE_SIZE));
        $page = min($page, $pageCount);
        $visibleItems = $filteredItems
            ->slice(($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE)
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => $allItems->count(),
                    'unread' => $allItems->where('read', false)->count(),
                    'critical' => $allItems->filter(fn (array $item): bool => in_array($item['priority'], ['Critical', 'High'], true))->count(),
                    'selected' => 0,
                ],
                'notifications' => [
                    'data' => $visibleItems,
                    'current_page' => $page,
                    'per_page' => self::PAGE_SIZE,
                    'total' => $filteredItems->count(),
                    'page_count' => $pageCount,
                ],
                'preview' => $allItems->take(5)->values()->all(),
                'status_filter' => $status,
                'scope_note' => 'HQ/Admin notification actions hide or mark notices in the current web view only. Delivery logs stay unchanged for audit.',
            ],
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $this->writeAuditLog($request, 'mark_read', [
            'selected_ids' => $request->input('notification_ids', []),
        ]);

        return response()->json([
            'message' => 'Notifications marked as read in the current HQ view.',
            'data' => [
                'action' => 'mark_read',
            ],
        ]);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_ids' => ['required', 'array', 'min:1'],
            'notification_ids.*' => ['string', 'max:120'],
        ], [
            'notification_ids.required' => 'Select at least one notification to delete.',
            'notification_ids.min' => 'Select at least one notification to delete.',
        ]);

        $this->writeAuditLog($request, 'delete_selected', [
            'selected_ids' => $validated['notification_ids'],
        ]);

        return response()->json([
            'message' => count($validated['notification_ids']).' notification(s) hidden from the current HQ view.',
            'data' => [
                'action' => 'delete_selected',
                'notification_ids' => $validated['notification_ids'],
            ],
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $this->writeAuditLog($request, 'clear_all', [
            'scope' => 'current_hq_view',
        ]);

        return response()->json([
            'message' => 'All notifications cleared from the current HQ view.',
            'data' => [
                'action' => 'clear_all',
            ],
        ]);
    }

    private function notificationFeed(): Collection
    {
        return collect()
            ->merge($this->outgoingNotificationRows())
            ->merge($this->householdAlertRows())
            ->merge($this->dispatchRows())
            ->merge($this->resourceRequestRows())
            ->merge($this->weatherRows())
            ->merge($this->broadcastRows())
            ->merge($this->auditRows())
            ->sortByDesc('sort_time')
            ->values()
            ->take(80);
    }

    private function outgoingNotificationRows(): array
    {
        return DB::table('notifications')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (object $row): array {
                $priority = $this->priorityFromUrgency($row->urgency_level_id);

                return $this->notice(
                    'notif-'.$row->notif_id,
                    'Broadcast',
                    $priority,
                    $this->notificationTitle($row),
                    $row->message ?: 'Notification message saved in shared DB.',
                    $row->created_at,
                    in_array($row->status, ['sent', 'cancelled', 'completed'], true)
                );
            })
            ->all();
    }

    private function householdAlertRows(): array
    {
        return DB::table('household_status_logs as hsl')
            ->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->whereIn('hs.status_key', ['unsafe', 'injured', 'missing', 'not_evacuated', 'displaced'])
            ->orderByDesc('hsl.submitted_at')
            ->limit(20)
            ->get([
                'hsl.status_log_id',
                'hsl.submitted_at',
                'hsl.reviewed_at',
                'hsl.location_label',
                'hsl.notes',
                'h.household_name',
                'a.purok_sitio',
                'hs.status_label',
            ])
            ->map(function (object $row): array {
                $purok = $row->purok_sitio ?: 'unassigned area';
                $household = $row->household_name ?: 'Household';
                $status = $row->status_label ?: 'Unsafe';

                return $this->notice(
                    'household-'.$row->status_log_id,
                    'Household status',
                    'Critical',
                    $status.' household report from '.$purok,
                    $household.' reported '.$status.'. '.($row->notes ?: $row->location_label ?: 'HQ review is required.'),
                    $row->submitted_at,
                    (bool) $row->reviewed_at
                );
            })
            ->all();
    }

    private function dispatchRows(): array
    {
        return DB::table('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->orderByDesc('ra.assigned_at')
            ->limit(20)
            ->get([
                'ra.assignment_id',
                'ra.assignment_code',
                'ra.assigned_area',
                'ra.priority_level',
                'ra.status',
                'ra.assigned_at',
                'ra.completed_at',
                'rt.team_name',
                'r.full_name as responder_name',
            ])
            ->map(function (object $row): array {
                $team = $row->team_name ?: $row->responder_name ?: 'Responder team';
                $priority = in_array($row->priority_level, ['high', 'critical', 'urgent'], true) ? 'High' : 'Medium';
                $read = in_array($row->status, ['completed', 'cancelled'], true) || (bool) $row->completed_at;

                return $this->notice(
                    'dispatch-'.$row->assignment_id,
                    'Dispatch route',
                    $priority,
                    $team.' route updated',
                    $this->label($row->status ?: 'assigned').' to '.($row->assigned_area ?: 'assigned area').'.',
                    $row->assigned_at,
                    $read
                );
            })
            ->all();
    }

    private function resourceRequestRows(): array
    {
        return DB::table('resource_requests')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (object $row): array {
                $priority = $row->validation_status === 'needs_validation' ? 'Medium' : 'Normal';
                $item = $row->item_name ?: $row->resource_type ?: 'Request';
                $read = ! in_array($row->validation_status, ['needs_validation', 'returned'], true);

                return $this->notice(
                    'request-'.$row->request_id,
                    'Resources',
                    $priority,
                    $item.' request '.$this->label($row->validation_status ?: 'needs_validation'),
                    ($row->requested_by ?: 'Requester').' requested '.trim(($row->quantity ?? '').' '.($row->unit ?? '')).'.',
                    $row->created_at,
                    $read
                );
            })
            ->all();
    }

    private function weatherRows(): array
    {
        return DB::table('weather_logs')
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (object $row): array {
                $text = strtolower(trim(($row->condition_name ?? '').' '.($row->advisory_title ?? '').' '.($row->advisory_text ?? '')));
                $isCritical = str_contains($text, 'storm') || str_contains($text, 'heavy') || str_contains($text, 'flood');

                return $this->notice(
                    'weather-'.$row->weather_log_id,
                    'Weather',
                    $isCritical ? 'High' : 'Normal',
                    $row->advisory_title ?: ($row->condition_name ?: 'Weather snapshot saved'),
                    $row->advisory_text ?: 'Weather data was saved from '.$row->source_name.'. Confirm official warnings through PAGASA before broadcasting.',
                    $row->observed_at ?: $row->created_at,
                    ! $isCritical
                );
            })
            ->all();
    }

    private function broadcastRows(): array
    {
        return DB::table('disaster_broadcasts as db')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'db.severity_id')
            ->orderByDesc('db.sent_at')
            ->limit(20)
            ->get([
                'db.broadcast_id',
                'db.broadcast_title',
                'db.message',
                'db.scope_type',
                'db.status',
                'db.sent_at',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->map(function (object $row): array {
                $priority = in_array($row->severity_key, ['high', 'critical', 'severe'], true) ? 'Critical' : 'High';

                return $this->notice(
                    'broadcast-'.$row->broadcast_id,
                    'Broadcast',
                    $priority,
                    $row->broadcast_title ?: 'Disaster broadcast saved',
                    ($row->message ?: 'Broadcast message saved.').' Scope: '.$this->label($row->scope_type ?: 'barangay'),
                    $row->sent_at,
                    true
                );
            })
            ->all();
    }

    private function auditRows(): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return DB::table('audit_logs')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(fn (object $row): array => $this->notice(
                'audit-'.$row->audit_log_id,
                'System activity',
                'Normal',
                $this->label($row->module).' '.$this->label($row->action),
                'Action recorded for '.$row->reference_table.' #'.$row->reference_id.'.',
                $row->created_at,
                true
            ))
            ->all();
    }

    private function filterByStatus(Collection $items, string $status): Collection
    {
        if ($status === 'unread') {
            return $items->where('read', false)->values();
        }

        if ($status === 'read') {
            return $items->where('read', true)->values();
        }

        return $items;
    }

    private function notice(string $id, string $type, string $priority, string $title, string $body, mixed $time, bool $read): array
    {
        $date = $time ? Carbon::parse($time) : now();

        return [
            'id' => $id,
            'type' => $type,
            'priority' => $priority,
            'title' => $title,
            'body' => $body,
            'date' => $this->dateLabel($date),
            'time' => $date->format('g:i A'),
            'created_at' => $date->toDateTimeString(),
            'sort_time' => $date->timestamp,
            'read' => $read,
            'tone' => $this->priorityTone($priority),
        ];
    }

    private function statusFilter(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['all', 'read', 'unread'], true) ? $status : 'all';
    }

    private function priorityFromUrgency(mixed $urgencyId): string
    {
        $id = (int) $urgencyId;

        if ($id >= 4) {
            return 'Critical';
        }

        if ($id >= 3) {
            return 'High';
        }

        if ($id >= 2) {
            return 'Medium';
        }

        return 'Normal';
    }

    private function priorityTone(string $priority): string
    {
        return match ($priority) {
            'Critical' => 'red',
            'High' => 'amber',
            'Medium' => 'blue',
            default => 'gray',
        };
    }

    private function notificationTitle(object $row): string
    {
        $status = $row->status ? $this->label($row->status) : 'Saved';

        return $status.' notification'.($row->channel ? ' via '.$this->label($row->channel) : '');
    }

    private function dateLabel(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('M d, Y');
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    private function writeAuditLog(Request $request, string $action, array $values): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'notifications',
            'action' => $action,
            'reference_table' => 'notifications',
            'reference_id' => 'hq-view',
            'old_values' => null,
            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
