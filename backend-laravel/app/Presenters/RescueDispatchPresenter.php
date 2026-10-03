<?php

namespace App\Presenters;

use Carbon\Carbon;

class RescueDispatchPresenter
{
    public function statusKey(?string $status): string
    {
        return match ($status) { 'onscene', 'on-scene' => 'on_scene', 'en-route' => 'en_route', 'off_duty', 'off-duty', 'stand-by' => 'standby', null, '' => 'standby', default => str_replace('-', '_', $status) };
    }

    public function status(?string $value): array
    {
        $key = $this->statusKey($value ?: 'standby');
        return ['key' => $key, 'label' => match ($key) { 'accepted' => 'Accepted', 'returning' => 'Returning to base', 'on_scene' => 'On-scene', 'en_route' => 'En route', 'dispatched' => 'Dispatched', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'available' => 'Available', 'deployed' => 'Deployed', default => 'Stand-by' }, 'tone' => match ($key) { 'on_scene', 'completed', 'available' => 'green', 'dispatched', 'en_route', 'accepted', 'deployed' => 'purple', 'returning' => 'amber', 'cancelled' => 'red', default => 'gray' }];
    }

    public function activeEvent(object $event): array
    {
        return ['event_id' => $event->event_id, 'name' => $event->name, 'type' => $event->type_name ?? $event->type?->type_name ?? 'Disaster event', 'severity' => $event->severity_label ?? $event->severity?->severity_label ?? 'Unspecified', 'severity_key' => $event->severity_key ?? $event->severity?->severity_key ?? 'medium', 'started_at' => $this->dateTime($event->started_at), 'started_time' => $this->time($event->started_at)];
    }

    public function dateTime(?string $dateTime): ?string { return $dateTime ? Carbon::parse($dateTime)->format('M d, Y h:i A') : null; }
    public function time(?string $dateTime): ?string { return $dateTime ? Carbon::parse($dateTime)->format('h:i A') : null; }
    public function label(?string $value): string { return str($value ?? '')->replace(['_', '-'], ' ')->title()->toString(); }
    public function decodeJson(?string $value): array { $decoded = $value ? json_decode($value, true) : null; return is_array($decoded) ? $decoded : []; }

    public function outcomes(array $outcomes): array
    {
        return ['safe' => (int) ($outcomes['safe_count'] ?? 0), 'evacuated' => (int) ($outcomes['evacuated_count'] ?? 0), 'unsafe' => (int) ($outcomes['unsafe_count'] ?? 0), 'injured' => (int) ($outcomes['injured_count'] ?? 0), 'missing' => (int) ($outcomes['missing_count'] ?? 0), 'pending' => (int) ($outcomes['pending_count'] ?? 0), 'notes' => $outcomes['outcome_notes'] ?? null];
    }

    public function coveragePercent(array $outcomes, int $assignedHouseholds): int
    {
        if ($assignedHouseholds <= 0) return 0;
        $reported = (int) ($outcomes['safe_count'] ?? 0) + (int) ($outcomes['evacuated_count'] ?? 0) + (int) ($outcomes['unsafe_count'] ?? 0) + (int) ($outcomes['injured_count'] ?? 0) + (int) ($outcomes['missing_count'] ?? 0);
        return min(100, (int) round(($reported / $assignedHouseholds) * 100));
    }

    public function dispatch(?object $dispatch, int $teamResponderCount = 0): array
    {
        if (! $dispatch) return [];
        $route = $this->decodeJson($dispatch->route_notes);
        $outcomes = $this->decodeJson($dispatch->outcome_notes);
        return ['assignment_id' => $dispatch->assignment_id, 'assignment_code' => $dispatch->assignment_code, 'team_id' => $dispatch->team_id, 'team_name' => $dispatch->team_name ?: 'Responder assignment', 'team_code' => $dispatch->team_code, 'team_type' => $dispatch->team_type ?: 'Response team', 'responder_id' => $dispatch->responder_id, 'responder_name' => $dispatch->responder_name ?: 'Assigned responder', 'responder_contact' => $dispatch->responder_contact, 'household_id' => $dispatch->household_id, 'assigned_area' => $dispatch->assigned_area, 'priority_level' => $dispatch->priority_level ?: 'monitor', 'priority_label' => $this->label($dispatch->priority_level ?: 'monitor'), 'status' => $this->status($dispatch->status), 'dispatch_notes' => $dispatch->dispatch_notes, 'route_notes' => $route['route_notes'] ?? $dispatch->route_notes, 'selected_responder_ids' => $route['selected_responder_ids'] ?? ($dispatch->responder_id ? [(int) $dispatch->responder_id] : []), 'selected_responders' => $route['selected_responders'] ?? [], 'households_to_cover' => (int) ($route['households_to_cover'] ?? ($dispatch->household_id ? 1 : 0)), 'responder_count' => (int) ($route['responder_count'] ?? ($teamResponderCount ?: ($dispatch->responder_id ? 1 : 0))), 'outcomes' => $this->outcomes($outcomes), 'assigned_at' => $this->dateTime($dispatch->assigned_at), 'assigned_time' => $this->time($dispatch->assigned_at), 'accepted_at' => $this->dateTime($dispatch->accepted_at), 'en_route_at' => $this->dateTime($dispatch->en_route_at), 'arrived_at' => $this->dateTime($dispatch->arrived_at), 'completed_at' => $this->dateTime($dispatch->completed_at), 'coverage_percent' => $this->coveragePercent($outcomes, (int) ($route['households_to_cover'] ?? 0))];
    }

    public function riskHousehold(object $item, $busyHouseholdIds): array
    {
        $statusKey = $item->status_key ?: 'unchecked';
        $busyAssignmentId = $busyHouseholdIds[$item->household_id] ?? null;
        return ['household_id' => $item->household_id, 'household_code' => $item->household_code, 'household_name' => $item->household_name ?: $item->household_code ?: $item->household_id, 'member_count' => (int) ($item->member_count ?? 0), 'address' => $item->geotag_label ?: ($item->full_address ?: trim(($item->house_number ? $item->house_number.' ' : '').($item->area_name ?: ''))), 'status_key' => $statusKey, 'status_label' => $item->status_label ?: 'Unchecked', 'reported_unsafe_count' => in_array($statusKey, ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured'], true) ? 1 : 0, 'priority_level' => $item->priority_level ?: 'watch', 'needs_dispatch' => (bool) $item->needs_dispatch, 'has_geotag' => (bool) $item->has_geotag, 'last_battery_level' => $item->last_battery_level, 'last_reported_at' => $this->dateTime($item->last_reported_at), 'active_assignment_id' => $busyAssignmentId ? (int) $busyAssignmentId : null, 'is_available_for_dispatch' => ! $busyAssignmentId];
    }
}


