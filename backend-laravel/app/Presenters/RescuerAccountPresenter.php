<?php

namespace App\Presenters;

use Illuminate\Support\Carbon;

class RescuerAccountPresenter
{
    public function formatResponder(object $row, bool $includeDetails = false): array
    {
        $status = $this->formatStatus($row->duty_status, (int) $row->is_active === 1);
        $nameParts = $this->namePartsFromRow($row);
        $accountStatus = (int) $row->is_active === 1
            ? ((int) $row->is_validated === 1 ? 'active' : 'reserve')
            : 'disabled';

        $data = [
            'responder_id' => $row->responder_id,
            'user_id' => $row->user_id,
            'account_id' => $row->username,
            'username' => $row->user_username,
            'display_username' => $row->user_username,
            'responder_code' => $row->responder_code,
            'account_status' => $accountStatus,
            'full_name' => $row->full_name ?: 'Unnamed rescuer',
            'first_name' => $nameParts['first_name'],
            'middle_initial' => $nameParts['middle_initial'],
            'last_name' => $nameParts['last_name'],
            'title' => $row->title ?: 'Responder',
            'team_id' => $row->team_id,
            'team_name' => $row->team_name ?: 'Unassigned',
            'team_code' => $row->team_code ?: null,
            'team_type' => $row->team_type ?: null,
            'contact_number' => $row->contact_number,
            'emergency_contact_name' => $row->emergency_contact_name,
            'emergency_contact_number' => $row->emergency_contact_number,
            'address' => $row->address,
            'blood_type' => $row->blood_type ?: 'Unknown',
            'skills' => $row->skills,
            'training_notes' => $row->training_notes,
            'certification_reference' => $row->certification_reference,
            'equipment_notes' => $row->equipment_notes,
            'duty_status' => $status,
            'is_deployed' => (bool) $row->is_deployed,
            'is_validated' => (bool) $row->is_validated,
            'is_active' => (bool) $row->is_active,
            'training_due' => $this->hasTrainingDue($row),
            'last_active_at' => $this->formatDateTime($row->last_active_at),
            'created_at' => $this->formatDateTime($row->created_at),
        ];

        if ($includeDetails) {
            $data['email'] = $row->email;
            $data['date_of_birth'] = $row->date_of_birth;
            $data['gender'] = $row->gender;
            $data['must_change_password'] = (bool) $row->must_change_password;
            $data['audit_note'] = 'Create, update, and deactivate actions are retained in audit logs when available.';
        }

        return $data;
    }

    public function formatStatus(?string $status, bool $isActive = true): array
    {
        if (! $isActive || $status === 'disabled') {
            return ['key' => 'disabled', 'label' => 'Disabled', 'tone' => 'gray'];
        }

        return match ($status) {
            'on_duty' => ['key' => 'on_duty', 'label' => 'On duty', 'tone' => 'green'],
            'available', 'standby' => ['key' => 'available', 'label' => 'Available', 'tone' => 'green'],
            'reserve' => ['key' => 'reserve', 'label' => 'Reserve', 'tone' => 'blue'],
            'dispatched' => ['key' => 'dispatched', 'label' => 'Dispatched', 'tone' => 'purple'],
            'on_scene' => ['key' => 'on_scene', 'label' => 'On-scene', 'tone' => 'green'],
            'unavailable' => ['key' => 'unavailable', 'label' => 'Unavailable', 'tone' => 'amber'],
            default => ['key' => 'off_duty', 'label' => 'Off duty', 'tone' => 'gray'],
        };
    }

    public function dutyStatuses(): array
    {
        return [
            ['key' => 'all', 'label' => 'All'],
            ['key' => 'active', 'label' => 'Active accounts'],
            ['key' => 'on_duty', 'label' => 'On duty'],
            ['key' => 'available', 'label' => 'Available'],
            ['key' => 'reserve', 'label' => 'Reserve'],
            ['key' => 'dispatched', 'label' => 'Dispatched'],
            ['key' => 'on_scene', 'label' => 'On-scene'],
            ['key' => 'training_due', 'label' => 'Training due'],
            ['key' => 'disabled', 'label' => 'Disabled'],
        ];
    }

    public function responderRoles(): array
    {
        return [
            'Responder',
            'HQ Command Center',
            'Team leader',
            'Driver',
            'Medic / first aider',
            'Radio operator',
            'Logistics officer',
            'Site coordinator',
        ];
    }

    public function bloodTypes(): array
    {
        return ['Unknown', 'O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-', 'AB-'];
    }



    public function hasTrainingDue(object $row): bool
    {
        $text = strtolower(trim(($row->training_notes ?? '').' '.($row->certification_reference ?? '')));

        return str_contains($text, 'due')
            || str_contains($text, 'expired')
            || str_contains($text, 'refresh');
    }

    public function namePartsFromRow(object $row): array
    {
        $firstName = trim((string) ($row->user_first_name ?? ''));
        $lastName = trim((string) ($row->user_last_name ?? ''));
        $fullName = trim((string) ($row->full_name ?? $row->user_full_name ?? ''));

        if ($firstName !== '' && $lastName !== '') {
            $middle = trim($fullName);
            $middle = preg_replace('/^'.preg_quote($firstName, '/').'\s+/i', '', $middle);
            $middle = preg_replace('/(^|\s+)'.preg_quote($lastName, '/').'$/i', '', $middle);
            $middle = trim((string) $middle);

            return [
                'first_name' => $firstName,
                'middle_initial' => $this->formatMiddleInitial($middle),
                'last_name' => $lastName,
            ];
        }

        $parts = collect(explode(' ', $fullName))->filter()->values();

        if ($parts->count() === 1) {
            return ['first_name' => $parts[0], 'middle_initial' => '', 'last_name' => ''];
        }

        $firstName = $parts->slice(0, -1)->join(' ');
        $lastName = $parts->last();

        return ['first_name' => $firstName, 'middle_initial' => '', 'last_name' => $lastName];
    }

    public function formatMiddleInitial(?string $value): string
    {
        $middle = strtoupper(trim((string) $value));
        $middle = str_replace('.', '', $middle);
        return $middle === '' ? '' : substr($middle, 0, 1).'.';
    }
    public function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }
}


