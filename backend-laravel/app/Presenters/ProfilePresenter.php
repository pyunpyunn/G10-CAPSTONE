<?php

namespace App\Presenters;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProfilePresenter
{
    public function summaryCards(object $user, mixed $lastSeen): array
    {
        $roleKey = $user->roleKey();
        $roleName = $user->roleName();
        return [
            ['label' => 'Account ID', 'value' => $user->username ?: $user->user_id, 'note' => 'Credential role auto-detected'],
            ['label' => 'Role', 'value' => $this->roleShortName($roleKey), 'note' => $roleName ?: 'HQ/Admin'],
            ['label' => 'Access level', 'value' => $roleKey === 'super_admin' ? 'Full+' : 'Full', 'note' => 'Broadcast, SitRep, archive, dispatch'],
            ['label' => 'Last login', 'value' => $lastSeen ? Carbon::parse($lastSeen)->format('g:i A') : 'Current', 'note' => $lastSeen ? Carbon::parse($lastSeen)->format('M d, Y') : 'Current session'],
        ];
    }

    public function activity(Collection $rows): array
    {
        return $rows->map(fn (object $row): array => [
            'id' => $row->audit_log_id,
            'title' => $this->label($row->module).' '.$this->label($row->action),
            'description' => $this->label($row->reference_table).' #'.$row->reference_id,
            'date_label' => $this->activityDate($row->created_at),
            'time' => Carbon::parse($row->created_at)->format('g:i A'),
        ])->all();
    }    public function identity(mixed $user): array
    {
        return [
            'account_id' => $user->username ?: $user->user_id,
            'user_id' => $user->user_id,
            'name' => trim($user->first_name.' '.$user->last_name) ?: ($user->name ?: 'HQ/Admin Desk'),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email ?: 'No email recorded',
            'contact_number' => $user->contact_number ?: 'No mobile recorded',
            'status' => $user->is_active ? 'Active and verified' : 'Inactive',
            'assigned_station' => $user->assigned_center_id ?: 'Command desk',
            'role_name' => $user->roleName() ?: 'HQ/Admin',
            'role_key' => $user->roleKey() ?: 'admin',
        ];
    }

    public function permissions(?string $roleKey): array
    {
        $items = [
            [
                'key' => 'broadcast',
                'title' => 'Disaster broadcast control',
                'description' => 'Declare, send, and close official disaster broadcasts.',
            ],
            [
                'key' => 'dispatch',
                'title' => 'Rescue dispatch coordination',
                'description' => 'Open dispatch forms, assign teams, and review field route updates.',
            ],
            [
                'key' => 'sitrep',
                'title' => 'Situation reporting and archive',
                'description' => 'Generate SitReps and view historical disaster event records.',
            ],
            [
                'key' => 'accounts',
                'title' => 'Responder account management',
                'description' => 'Create verified rescuer accounts and maintain team rosters.',
            ],
        ];

        return collect($items)
            ->map(fn (array $item): array => [
                ...$item,
                'status' => in_array($roleKey, ['super_admin', 'admin'], true) ? 'Enabled' : 'Limited',
                'tone' => in_array($roleKey, ['super_admin', 'admin'], true) ? 'green' : 'amber',
            ])
            ->all();
    }

    public function activityDate(?string $value): string
    {
        if (! $value) {
            return 'No date';
        }

        $date = Carbon::parse($value);

        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('M d');
    }

    public function roleShortName(?string $roleKey): string
    {
        return $roleKey === 'super_admin' ? 'Super Admin' : 'HQ';
    }

    public function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }
}


