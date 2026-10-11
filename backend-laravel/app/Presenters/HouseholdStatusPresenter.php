<?php

namespace App\Presenters;

use Carbon\Carbon;

class HouseholdStatusPresenter
{
    public function formatHouseholdRow(object $row, ?object $latestLog, ?object $latestDevice, ?object $accountUser, bool $hasActiveEvent): array
    {
        $statusNotes = $this->decodeJson($row->last_status_notes ?? $latestLog?->notes);
        $status = $hasActiveEvent
            ? $this->formatStatus($row->status_key, $row->status_label, $statusNotes)
            : ['key' => 'standby', 'label' => 'No active event', 'tone' => 'gray'];

        if ($hasActiveEvent && ! $row->current_status_id) {
            $status = ['key' => 'unchecked', 'label' => 'Unchecked', 'tone' => 'gray'];
        }

        $battery = $latestDevice?->battery_level ?? $row->lowest_battery ?? $row->last_battery_level ?? $latestLog?->battery_level;
        $lastSeen = $latestDevice?->last_seen_at ?? $row->latest_device_seen_at ?? $latestLog?->submitted_at ?? $row->last_reported_at;
        $locationLabel = $latestDevice?->last_location_label
            ?: $latestLog?->location_label
            ?: $row->full_address
            ?: 'No location yet';
        $deviceRisk = $this->deviceRisk((int) $row->device_total, (int) $row->active_device_total, $battery, $lastSeen, $locationLabel);
        $priority = $this->priority($status['key'], (bool) $row->needs_dispatch, $deviceRisk['key'], $hasActiveEvent);

        return [
            'id' => $row->household_id,
            'household_id' => $row->household_id,
            'household_name' => $row->household_name ?: 'Unnamed household',
            'account_holder' => $this->personName($accountUser?->name, $accountUser?->first_name, $accountUser?->last_name, $accountUser?->username ?? 'No linked account'),
            'account_id' => $accountUser?->username ?? $accountUser?->user_id,
            'household_code' => $row->household_code ?? $row->household_number,
            'contact_number' => $row->contact_number,
            'purok' => $row->purok ?: 'Unassigned',
            'address' => $row->full_address,
            'people' => (int) $row->member_total,
            'status' => $status,
            'source' => [
                'label' => $this->sourceLabel($row->last_status_source ?: $latestLog?->source),
                'submitted_by' => $this->personName($row->reporter_name, $row->reporter_first_name, $row->reporter_last_name, $row->last_reported_by_user_id),
                'time' => $this->formatTime($row->last_reported_at ?? $latestLog?->submitted_at),
                'datetime' => $this->formatDateTime($row->last_reported_at ?? $latestLog?->submitted_at),
                'notes' => $statusNotes['user_notes'] ?? $statusNotes['member_notes'] ?? ($row->last_status_notes ?? $latestLog?->notes),
            ],
            'device' => [
                'total' => (int) $row->device_total,
                'active' => (int) $row->active_device_total,
                'lowest_battery' => $battery,
                'battery_tone' => $this->batteryTone($battery),
                'last_seen_at' => $this->formatDateTime($lastSeen),
                'last_seen_time' => $this->formatTime($lastSeen),
                'risk' => $deviceRisk,
            ],
            'location' => [
                'label' => $locationLabel,
                'note' => $this->locationNote($latestDevice, $latestLog, $row),
                'latitude' => $latestDevice?->last_latitude ?? $latestLog?->latitude ?? $row->last_latitude,
                'longitude' => $latestDevice?->last_longitude ?? $latestLog?->longitude ?? $row->last_longitude,
            ],
            'priority' => $priority,
            'needs_dispatch' => (bool) $row->needs_dispatch,
        ];
    }

    public function formatHouseholdDetail(object $row, ?object $latestLog, ?object $latestDevice, ?object $accountUser, $devices, bool $hasActiveEvent, string $riskSummary): array
    {
        $basic = $this->formatHouseholdRow($row, $latestLog, $latestDevice, $accountUser, $hasActiveEvent);
        $lowestBattery = $devices->pluck('battery_level')->filter(fn ($value) => $value !== null)->min();
        $basic['detail_tiles'] = [
            [
                'label' => 'Lowest battery',
                'value' => $lowestBattery !== null ? $lowestBattery.'%' : ($basic['device']['lowest_battery'] !== null ? $basic['device']['lowest_battery'].'%' : 'No battery data'),
            ],
            [
                'label' => 'Last location',
                'value' => $basic['location']['label'],
            ],
            [
                'label' => 'Devices',
                'value' => $devices->count().' synced',
            ],
            [
                'label' => 'Risk flags',
                'value' => $riskSummary,
            ],
        ];

        return $basic;
    }

    public function formatActiveEvent(object $event): array
    {
        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Unspecified',
            'severity_key' => $event->severity_key ?? 'medium',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
        ];
    }

    public function formatStatus(?string $statusKey, ?string $statusLabel, array $notes = []): array
    {
        if (in_array($notes['mobile_status_key'] ?? null, ['safe', 'evacuated', 'unsafe', 'needs_help'], true)) {
            $statusKey = $notes['mobile_status_key'];
            $statusLabel = $notes['mobile_status_label'] ?? null;
        }

        if (! $statusKey) {
            return ['key' => 'unchecked', 'label' => 'Unchecked', 'tone' => 'gray'];
        }

        $key = str_replace('_', '-', $statusKey);
        $label = $statusLabel ?: $this->label($statusKey);

        if (in_array($statusKey, ['active', 'returned', 'safe'], true)) {
            $key = 'safe';
            $label = 'Safe';
        }

        if (in_array($statusKey, ['evacuated', 'relocated'], true)) {
            $key = 'evacuated';
            $label = 'Evacuated';
        }

        if (in_array($statusKey, ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased'], true)) {
            $key = 'unsafe';
            $label = str_contains(strtolower($label), 'help') || str_contains(strtolower($label), 'assist') ? 'Needs help' : 'Unsafe';
        }

        if (in_array($statusKey, ['needs_help', 'need_help', 'needs_assistance', 'injured', 'missing'], true)) {
            $key = 'needs-help';
            $label = 'Needs help';
        }

        return [
            'key' => $key,
            'label' => $label,
            'tone' => $this->statusTone($key),
        ];
    }

    public function statusTone(string $statusKey): string
    {
        if (in_array($statusKey, ['safe', 'active', 'returned'], true)) {
            return 'green';
        }

        if (in_array($statusKey, ['evacuated', 'relocated'], true)) {
            return 'blue';
        }

        if (in_array($statusKey, ['unsafe', 'missing', 'not-evacuated', 'displaced', 'needs-help'], true)) {
            return 'red';
        }

        if ($statusKey === 'injured') {
            return 'amber';
        }

        return 'gray';
    }

    public function sourceLabel(?string $source): string
    {
        return match ($source) {
            'household_mobile', 'mobile', 'self_report' => 'Household mobile',
            'responder_field_report', 'responder', 'rescuer' => 'Responder field report',
            default => $source ? $this->label($source) : 'No event report',
        };
    }

    public function deviceRisk(int $deviceTotal, int $activeDeviceTotal, mixed $battery, ?string $lastSeen, ?string $locationLabel): array
    {
        if ($deviceTotal <= 0) {
            return ['key' => 'none', 'label' => 'No device data'];
        }

        if ($activeDeviceTotal <= 0 || ($battery !== null && (int) $battery <= 15)) {
            return ['key' => 'critical', 'label' => 'Critical'];
        }

        if (($battery !== null && (int) $battery <= 25) || $this->isStale($lastSeen) || ! $locationLabel) {
            return ['key' => 'watch', 'label' => 'Watch'];
        }

        return ['key' => 'stable', 'label' => 'Stable'];
    }

    public function priority(string $statusKey, bool $needsDispatch, string $deviceRisk, bool $hasActiveEvent): array
    {
        if (! $hasActiveEvent) {
            return ['key' => 'standby', 'label' => 'Standby'];
        }

        if ($needsDispatch || in_array($statusKey, ['unsafe', 'needs-help', 'missing', 'injured'], true) || $deviceRisk === 'critical') {
            return ['key' => 'urgent', 'label' => 'Dispatch focus'];
        }

        if ($statusKey === 'unchecked' || in_array($deviceRisk, ['watch', 'none'], true)) {
            return ['key' => 'watch', 'label' => 'Follow-up'];
        }

        return ['key' => 'stable', 'label' => 'Stable'];
    }

    public function batteryTone(mixed $battery): string
    {
        if ($battery === null) {
            return 'unknown';
        }

        if ((int) $battery <= 15) {
            return 'critical';
        }

        if ((int) $battery <= 25) {
            return 'low';
        }

        return 'ok';
    }

    public function isStale(?string $dateTime): bool
    {
        if (! $dateTime) {
            return false;
        }

        return Carbon::parse($dateTime)->lt(now()->subHours(6));
    }

    public function locationNote(?object $latestDevice, ?object $latestLog, object $row): string
    {
        if ($latestDevice?->last_location_label) {
            $permission = $this->label($latestDevice->location_permission_status ?? 'unknown');
            $accuracy = $latestDevice->last_location_accuracy_m ? round((float) $latestDevice->last_location_accuracy_m).'m' : 'accuracy unknown';

            return "{$permission} location permission - {$accuracy}";
        }

        if ($latestLog?->location_label) {
            $accuracy = $latestLog->location_accuracy_m ? round((float) $latestLog->location_accuracy_m).'m' : 'accuracy unknown';

            return "Status report location - {$accuracy}";
        }

        if ($row->full_address) {
            return 'Registered address';
        }

        return 'No location saved yet';
    }

    public function memberRiskFlags(object $member): string
    {
        $flags = [];

        if ((bool) $member->is_pwd) {
            $flags[] = 'PWD';
        }

        if ((bool) $member->is_senior) {
            $flags[] = 'Senior';
        }

        if ((bool) $member->is_pregnant) {
            $flags[] = 'Pregnant';
        }

        if (! empty($member->special_needs)) {
            $flags[] = $member->special_needs;
        }

        return count($flags) > 0 ? implode(' / ', array_unique($flags)) : 'None';
    }

    public function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function label(?string $value): string
    {
        return str($value ?? '')
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    public function personName(?string $name, ?string $firstName, ?string $lastName, ?string $fallback): string
    {
        $fullName = trim((string) ($name ?: trim(($firstName ?? '').' '.($lastName ?? ''))));

        return $fullName !== '' ? $fullName : ($fallback ?: 'Not recorded');
    }

    public function ageFromBirthDate(?string $birthDate): ?int
    {
        if (! $birthDate) {
            return null;
        }

        return Carbon::parse($birthDate)->age;
    }

    public function formatDateTime(?string $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->format('M d, Y h:i A');
    }

    public function formatTime(?string $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->format('h:i A');
    }
    public function formatStatusLog(object $log): array
    {
        $notes = $this->decodeJson($log->notes);
        return [
            'status_log_id' => $log->status_log_id,
            'status' => $this->formatStatus($log->status_key, $log->status_label, $notes),
            'source' => $this->sourceLabel($log->source),
            'submitted_by' => $this->personName($log->submitter_name ?? null, $log->submitter_first_name ?? null, $log->submitter_last_name ?? null, $log->submitted_by_user_id ?? null),
            'location_label' => $log->location_label,
            'location_accuracy_m' => $log->location_accuracy_m,
            'battery_level' => $log->battery_level,
            'signal_strength' => $log->signal_strength,
            'notes' => $notes['user_notes'] ?? $notes['member_notes'] ?? $log->notes,
            'submitted_at' => $this->formatDateTime($log->submitted_at ?? $log->created_at),
            'submitted_time' => $this->formatTime($log->submitted_at ?? $log->created_at),
        ];
    }
}


