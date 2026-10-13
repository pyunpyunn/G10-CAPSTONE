<?php

namespace App\Presenters;

class UserRolePresenter
{
    public function key(?object $role, array $attributes): ?string
    {
        $key = $role?->role_key ?? ($attributes['role'] ?? null);
        if (! $key && isset($attributes['role_id'])) {
            $key = match ((int) $attributes['role_id']) {
                1 => 'super_admin', 2, 4 => 'admin', 3, 5 => 'rescuer',
                6 => 'household_resident', default => null,
            };
        }
        return match (strtolower((string) $key)) {
            'superadmin', 'super_admin', 'super admin' => 'super_admin',
            'admin', 'hq_admin', 'hq admin' => 'admin',
            'hq_leader', 'hq leader', 'hqcc_leader' => 'hq_leader',
            'hq_personnel', 'hq personnel' => 'hq_personnel',
            'rescuer', 'responder' => 'rescuer',
            'household', 'household_resident', 'resident' => 'household_resident',
            default => $key,
        };
    }

    public function name(?object $role, array $attributes, ?string $key): ?string
    {
        $name = $role?->role_name ?? ($attributes['role'] ?? null);
        return match ($key) {
            'super_admin' => 'Super Admin', 'admin' => 'Admin',
            'hq_leader' => 'HQ Leader', 'hq_personnel' => 'HQ Personnel',
            'rescuer' => 'Rescuer Member', 'household_resident' => 'Household Resident',
            default => $name,
        };
    }
}
