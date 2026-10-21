<?php

namespace App\Presenters;

class UserRolePresenter
{
    public function key(?object $role, array $attributes): ?string
    {
        $key = $role?->role_key ?: ($role?->base_role_key ?? ($attributes['role'] ?? null));
        if (! $key && isset($attributes['role_id'])) {
            $key = match ((int) $attributes['role_id']) {
                1 => 'super_admin', 2, 4 => 'admin', 3, 5 => 'rescuer',
                6 => 'household_resident', default => null,
            };
        }
        return match (strtolower((string) $key)) {
            'superadmin', 'super_admin', 'super admin', 'admin', 'adm', 'operations_admin' => 'operations_admin',
            'hq_admin', 'hq admin', 'pb', 'chair' => 'chair',
            'hq_leader', 'hq leader', 'hqcc_leader', 'owh', 'dsp', 'leo', 'ech' => 'response',
            'hq_personnel', 'hq personnel' => 'operations_admin',
            'pao' => 'mitigation_prevention',
            'rescuer', 'responder' => 'rescuer',
            'household', 'household_resident', 'resident' => 'household_resident',
            default => $key ? strtolower((string) $key) : null,
        };
    }

    public function name(?object $role, array $attributes, ?string $key): ?string
    {
        if ($role && ($role->base_role_key || $role->web_modules !== null)) return $role->role_name;
        $name = $role?->role_name ?? ($attributes['role'] ?? null);
        return match ($key) {
            'operations_admin' => 'Operations/Admin',
            'chair' => 'BDRRM Committee Chairperson',
            'mitigation_prevention' => 'Mitigation and Prevention Sub-committee',
            'preparedness' => 'Preparedness Sub-committee',
            'response' => 'Response Sub-committee',
            'recovery_rehabilitation' => 'Recovery and Rehabilitation Sub-committee',
            'tl' => 'Team Leader',
            'tm', 'rescuer' => 'Team Member',
            'household_resident' => 'Household Resident',
            default => $name,
        };
    }
}
