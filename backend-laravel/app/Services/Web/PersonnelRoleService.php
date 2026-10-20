<?php

namespace App\Services\Web;

use App\Models\Role;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonnelRoleService
{
    public function workspace(): array
    {
        $counts = DB::table('users')->selectRaw('role_id, COUNT(*) as total')->groupBy('role_id')->pluck('total', 'role_id');
        $roleKeys = array_merge(array_keys(WebAccessPolicy::MODULES), ['tl', 'tm']);
        return ['roles' => Role::query()->whereIn('role_key', $roleKeys)->orderBy('role_name')->get()->map(function ($role) use ($counts) {
            $base = $role->base_role_key ?: $role->role_key;
            $modules = $role->web_modules ?? (WebAccessPolicy::MODULES[$base] ?? []);
            return ['role_id' => $role->role_id, 'role_key' => $role->role_key, 'role_name' => $role->role_name, 'base_role_key' => $base,
                'web_modules' => $modules, 'can_manage_personnel' => $role->can_manage_personnel ?? $base === 'operations_admin',
                'can_approve_requests' => $role->can_approve_requests ?? $base === 'chair',
                'member_count' => (int) ($counts[$role->role_id] ?? 0),
                'can_edit' => false,
                'can_delete' => false];
        })->all(), 'modules' => array_values(array_unique(array_merge(...array_values(WebAccessPolicy::MODULES)))),
            'base_roles' => $roleKeys];
    }

    public function save(Request $request, ?int $id = null): array
    {
        throw ValidationException::withMessages(['role' => 'The RESQPERATION access role catalog is fixed. Assign an account to one of the listed roles instead.']);
    }

    public function delete(Request $request, int $id): array
    {
        throw ValidationException::withMessages(['role' => 'System access roles cannot be deleted. Reassign accounts to another role instead.']);
    }

    public function selected(array $data, bool $hq, ?int $existing = null): ?Role
    {
        if (empty($data['role_id'])) return null;
        $role = Role::query()->findOrFail($data['role_id']);
        $existingRole = $existing ? Role::query()->find($existing) : null;
        if (($existingRole?->base_role_key ?: $existingRole?->role_key) === 'operations_admin' && (int) $role->role_id !== $existing) throw ValidationException::withMessages(['role_id' => 'Operations/Admin access cannot be changed from account management.']);
        $base = $role->base_role_key ?: $role->role_key;
        $roleKey = strtolower((string) $role->role_key);
        if ($base === 'operations_admin' && $existing === (int) $role->role_id) return $role;
        $allowed = $hq ? ['chair', 'operations_admin', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation'] : ['tl', 'tm'];
        if (! in_array($hq ? $base : $roleKey, $allowed, true)) throw ValidationException::withMessages(['role_id' => 'Select a role matching this account type.']);
        return $role;
    }

    public function apply(int $responderId, ?Role $role): void
    {
        if (!$role) return;
        $profile = DB::table('responders')->where('responder_id', $responderId)->first();
        DB::table('users')->where('user_id', $profile->user_id)->update(['role_id' => $role->role_id, 'updated_at' => now()]);
        if ($role->base_role_key || $role->web_modules !== null) DB::table('responders')->where('responder_id', $responderId)->update(['title' => $role->role_name, 'updated_at' => now()]);
        app(PersonnelAccessNotifier::class)->notify([$profile->user_id]);
    }

    public function isRescueLeader(?Role $role): bool
    {
        return $role && strtolower((string) $role->role_key) === 'tl';
    }

    public function synchronizeRescueLeadership(int $teamId, ?int $leaderId): void
    {
        $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();
        if (!$team || strtoupper($team->team_code) === 'HQCC' || in_array(strtoupper($team->team_type), ['HQ', 'HQCC', 'HEADQUARTERS'], true)) return;
        $leaderRole = Role::query()->where('role_key', 'tl')->first();
        $rescuerRole = Role::query()->where('role_key', 'tm')->first();
        $profiles = DB::table('responders')->where('team_id', $teamId)->whereNull('deleted_at')->get(['responder_id', 'user_id', 'title']);
        foreach ($profiles as $profile) {
            if ((int) $profile->responder_id === $leaderId) {
                DB::table('responders')->where('responder_id', $profile->responder_id)->update(['title' => $leaderRole?->role_name ?? 'Team Leader', 'updated_at' => now()]);
                if ($leaderRole) $this->apply((int) $profile->responder_id, $leaderRole);
            } elseif (strtolower(trim((string) $profile->title)) === 'team leader' || ($leaderRole && (int) DB::table('users')->where('user_id', $profile->user_id)->value('role_id') === (int) $leaderRole->role_id)) {
                DB::table('responders')->where('responder_id', $profile->responder_id)->update(['title' => 'Team Member', 'updated_at' => now()]);
                if ($rescuerRole) $this->apply((int) $profile->responder_id, $rescuerRole);
            }
        }
    }

    private function audit(Request $request, string $action, int $id, ?array $before, array $after): void
    {
        if (!Schema::hasTable('audit_logs')) return;
        DB::table('audit_logs')->insert(['user_id' => $request->user()?->user_id, 'role_key' => $request->user()?->roleKey(), 'module' => 'rescuer_accounts',
            'action' => $action, 'reference_table' => 'roles', 'reference_id' => (string) $id, 'old_values' => $before ? json_encode($before) : null,
            'new_values' => json_encode($after), 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255), 'created_at' => now()]);
    }
}
