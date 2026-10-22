<?php

namespace App\Presenters;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProfilePresenter
{
    public function summaryCards(object $user, mixed $lastSeen): array
    {
        $roleKey = $user->roleKey();
        $roleName = $user->roleName();
        return [
            ['label' => 'Account ID', 'value' => $user->username ?: $user->user_id, 'note' => 'Credential role auto-detected'],
            ['label' => 'Role', 'value' => $this->roleShortName($roleKey), 'note' => $roleName ?: 'Unassigned'],
            ['label' => 'Access level', 'value' => $this->accessLevel($roleKey), 'note' => $this->accessSummary($roleKey)],
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
            'role_name' => $user->roleName() ?: 'Unassigned',
            'role_key' => $user->roleKey() ?: 'unassigned',
            'profile_photo' => $user->relationLoaded('member') ? $user->member?->profile_photo : null,
            'profile_photo_url' => $user->relationLoaded('member') && $user->member?->profile_photo
                ? Storage::disk('public')->url($user->member->profile_photo)
                : null,
        ];
    }

    public function permissions(User $user): array
    {
        $modules = app(\App\Services\Web\WebAccessPolicy::class)->modulesFor($user);
        $items = [
            ['key' => 'dashboard', 'title' => 'Dashboard', 'description' => 'Operational overview and current status.'],
            ['key' => 'geospatial_analytics', 'title' => 'Geospatial & analytics', 'description' => 'Maps, weather, and household status data.'],
            ['key' => 'response_operations', 'title' => 'Response operations', 'description' => 'Disaster response, dispatch, and field coordination.'],
            ['key' => 'resources_requests', 'title' => 'Resources & requests', 'description' => 'Resource request tracking and logistics workflow.'],
            ['key' => 'reports_documentation', 'title' => 'Reports documentation', 'description' => 'Situation reports and operational archives.'],
            ['key' => 'personnel_access', 'title' => 'Personnel access', 'description' => 'Manage HQ personnel, responders, and teams.'],
            ['key' => 'profile', 'title' => 'Profile', 'description' => 'View and update your personal profile.'],
            ['key' => 'notifications', 'title' => 'Notifications', 'description' => 'View notices allowed for your role.'],
            ['key' => 'inquiries', 'title' => 'Inquiries', 'description' => 'Review public inquiries.'],
        ];

        $permissions = collect($items)->map(fn (array $item): array => [
            ...$item,
            'status' => in_array($item['key'], $modules, true) ? 'Enabled' : 'Not granted',
            'tone' => in_array($item['key'], $modules, true) ? 'green' : 'gray',
        ])->all();
        $canApprove = app(\App\Services\Web\WebAccessPolicy::class)->allows($user, 'resources_requests', 'approve');
        $permissions[] = [
            'key' => 'resource_request_approval',
            'title' => 'Logistics request approval',
            'description' => 'Validate, return, and forward requests to the next logistics handoff.',
            'status' => $canApprove ? 'Enabled' : 'Not granted',
            'tone' => $canApprove ? 'green' : 'gray',
        ];

        return $permissions;
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
        return match ($roleKey) {
            'super_admin' => 'Super Admin',
            'hq_leader' => 'HQ Leader',
            'hq_personnel' => 'HQ Personnel',
            'rescuer' => 'Rescuer Member',
            default => 'Unassigned',
        };
    }

    private function accessLevel(?string $roleKey): string
    {
        return match ($roleKey) {
            'super_admin' => 'Platform',
            'hq_leader' => 'Command',
            'hq_personnel' => 'Operations',
            'rescuer' => 'Field',
            default => 'None',
        };
    }

    private function accessSummary(?string $roleKey): string
    {
        return match ($roleKey) {
            'super_admin' => 'All modules, including inquiries',
            'hq_leader' => 'All modules except inquiries',
            'hq_personnel' => 'Dashboard, geospatial, response, resources, reports',
            'rescuer' => 'Dashboard, geospatial, profile',
            default => 'No web modules',
        };
    }

    public function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }
}
