<?php

namespace App\Services\Web;

use App\Models\Role;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonnelRoleService
{
    public function workspace(): array
    {
        $counts = DB::table('users')->selectRaw('role_id, COUNT(*) as total')->groupBy('role_id')->pluck('total', 'role_id');
        return ['roles' => Role::query()->orderBy('role_name')->get()->map(function ($role) use ($counts) {
            $base = $role->base_role_key ?: $role->role_key;
            $modules = $role->web_modules ?? (WebAccessPolicy::MODULES[$base] ?? []);
            return ['role_id' => $role->role_id, 'role_key' => $role->role_key, 'role_name' => $role->role_name, 'base_role_key' => $base,
                'web_modules' => $modules, 'can_manage_personnel' => $role->can_manage_personnel ?? in_array($base, ['super_admin', 'hq_leader'], true),
                'can_approve_requests' => $role->can_approve_requests ?? in_array($base, ['super_admin', 'hq_leader'], true),
                'member_count' => (int) ($counts[$role->role_id] ?? 0),
                'can_edit' => $role->base_role_key || in_array($base, ['rescuer', 'hq_personnel', 'hq_leader'], true),
                'can_delete' => (bool) $role->base_role_key];
        })->all(), 'modules' => array_values(array_diff(WebAccessPolicy::MODULES['super_admin'], ['inquiries'])),
            'base_roles' => ['rescuer', 'hq_personnel', 'hq_leader']];
    }

    public function save(Request $request, ?int $id = null): array
    {
        abort_unless(Schema::hasColumn('roles', 'base_role_key'), 503, 'Install the personnel role permissions migration first.');
        $request->merge(['role_name' => trim((string) $request->input('role_name'))]);
        $data = $request->validate([
            'role_name' => 'required|string|min:2|max:100', 'base_role_key' => ['required', Rule::in(['rescuer', 'hq_personnel', 'hq_leader'])],
            'web_modules' => 'present|array', 'web_modules.*' => ['distinct', Rule::in(array_diff(WebAccessPolicy::MODULES['super_admin'], ['inquiries']))],
            'can_manage_personnel' => 'required|boolean', 'can_approve_requests' => 'required|boolean',
        ]);
        if ($data['can_manage_personnel'] && !in_array('personnel_access', $data['web_modules'], true)) throw ValidationException::withMessages(['web_modules' => 'Enable Personnel access when granting personnel management.']);
        if ($data['can_approve_requests'] && !in_array('resources_requests', $data['web_modules'], true)) throw ValidationException::withMessages(['web_modules' => 'Enable Resources requests when granting request approval.']);
        DB::transaction(function () use ($request, $id, $data) {
            $roles = Role::query()->lockForUpdate()->get();
            $role = $id ? $roles->firstWhere('role_id', $id) : new Role;
            abort_unless($role, 404);
            $base = $role->base_role_key ?: $role->role_key;
            if ($id && !$role->base_role_key && !in_array($base, ['rescuer', 'hq_personnel', 'hq_leader'], true)) abort(422, 'This system role is protected.');
            if ($roles->contains(fn ($r) => (int) $r->role_id !== $id && Str::lower($r->role_name) === Str::lower($data['role_name']))) throw ValidationException::withMessages(['role_name' => 'A role with this name already exists.']);
            if ($id && $base !== $data['base_role_key'] && (!$role->base_role_key || DB::table('users')->where('role_id', $id)->exists())) throw ValidationException::withMessages(['base_role_key' => 'Reassign this role’s accounts before changing its account type.']);
            $before = $role->exists ? $role->toArray() : null;
            if (!$id) {
                $role->role_id = (int) $roles->max('role_id') + 1;
                $role->role_key = 'personnel_'.Str::slug($data['role_name'], '_').'_'.$role->role_id;
                $role->base_role_key = $data['base_role_key'];
            } elseif ($role->base_role_key) $role->base_role_key = $data['base_role_key'];
            $role->role_name = $data['role_name']; $role->web_modules = array_values($data['web_modules']);
            $role->can_manage_personnel = $data['can_manage_personnel']; $role->can_approve_requests = $data['can_approve_requests']; $role->save();
            $userIds = DB::table('users')->where('role_id', $role->role_id)->pluck('user_id')->all();
            if (Schema::hasTable('responders')) DB::table('responders')->whereIn('user_id', $userIds)->whereNull('deleted_at')->update(['title' => $role->role_name, 'updated_at' => now()]);
            $this->audit($request, $id ? 'update_role' : 'create_role', $role->role_id, $before, $role->toArray());
            app(PersonnelAccessNotifier::class)->notify($userIds);
        });
        return $this->workspace();
    }

    public function delete(Request $request, int $id): array
    {
        $data = $request->validate(['replacement_role_id' => 'nullable|integer|exists:roles,role_id']);
        DB::transaction(function () use ($request, $id, $data) {
            $role = Role::query()->lockForUpdate()->findOrFail($id);
            abort_unless($role->base_role_key, 422, 'System roles cannot be deleted.');
            $users = DB::table('users')->where('role_id', $id)->lockForUpdate()->pluck('user_id')->all();
            if ($users) {
                $replacement = Role::query()->find($data['replacement_role_id'] ?? null);
                if (!$replacement || $replacement->role_id === $id || ($replacement->base_role_key ?: $replacement->role_key) !== $role->base_role_key) throw ValidationException::withMessages(['replacement_role_id' => 'Select a different replacement role with the same account type.']);
                if ($this->isRescueLeader($replacement) && !$this->isRescueLeader($role)) throw ValidationException::withMessages(['replacement_role_id' => 'Assign team leadership from individual accounts or team management. Choose a non-leader replacement here.']);
                if ($this->isRescueLeader($role) && !$this->isRescueLeader($replacement)) {
                    $ids = DB::table('responders')->whereIn('user_id', $users)->pluck('responder_id');
                    DB::table('rescue_teams')->whereIn('leader_responder_id', $ids)->update(['leader_responder_id' => null, 'updated_at' => now()]);
                }
                DB::table('users')->whereIn('user_id', $users)->update(['role_id' => $replacement->role_id, 'updated_at' => now()]);
                DB::table('responders')->whereIn('user_id', $users)->whereNull('deleted_at')->update(['title' => $replacement->role_name, 'updated_at' => now()]);
            }
            $this->audit($request, 'delete_role', $id, $role->toArray(), $data); $role->delete();
            app(PersonnelAccessNotifier::class)->notify($users);
        });
        return $this->workspace();
    }

    public function selected(array $data, bool $hq, ?int $existing = null): ?Role
    {
        if (empty($data['role_id'])) return null;
        $role = Role::query()->findOrFail($data['role_id']);
        $existingRole = $existing ? Role::query()->find($existing) : null;
        if (($existingRole?->base_role_key ?: $existingRole?->role_key) === 'super_admin' && (int) $role->role_id !== $existing) throw ValidationException::withMessages(['role_id' => 'The Super Admin role cannot be changed from account management.']);
        $base = $role->base_role_key ?: $role->role_key;
        if ($base === 'super_admin' && $existing === (int) $role->role_id) return $role;
        if (!in_array($base, $hq ? ['hq_personnel', 'hq_leader'] : ['rescuer'], true)) throw ValidationException::withMessages(['role_id' => 'Select a role matching this account type.']);
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
        return $role && (str_starts_with($role->role_key, 'personnel_team_leader') || strtolower(trim($role->role_name)) === 'team leader');
    }

    public function synchronizeRescueLeadership(int $teamId, ?int $leaderId): void
    {
        $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();
        if (!$team || strtoupper($team->team_code) === 'HQCC' || in_array(strtoupper($team->team_type), ['HQ', 'HQCC', 'HEADQUARTERS'], true)) return;
        $leaderRole = Role::query()->where('role_key', 'personnel_team_leader')->first() ?? Role::query()->whereRaw('LOWER(role_name) = ?', ['team leader'])->first();
        $rescuerRole = Role::query()->where('role_key', 'rescuer')->first();
        $profiles = DB::table('responders')->where('team_id', $teamId)->whereNull('deleted_at')->get(['responder_id', 'user_id', 'title']);
        foreach ($profiles as $profile) {
            if ((int) $profile->responder_id === $leaderId) {
                DB::table('responders')->where('responder_id', $profile->responder_id)->update(['title' => $leaderRole?->role_name ?? 'Team Leader', 'updated_at' => now()]);
                if ($leaderRole) $this->apply((int) $profile->responder_id, $leaderRole);
            } elseif (strtolower(trim((string) $profile->title)) === 'team leader' || ($leaderRole && (int) DB::table('users')->where('user_id', $profile->user_id)->value('role_id') === (int) $leaderRole->role_id)) {
                DB::table('responders')->where('responder_id', $profile->responder_id)->update(['title' => 'Responder', 'updated_at' => now()]);
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
