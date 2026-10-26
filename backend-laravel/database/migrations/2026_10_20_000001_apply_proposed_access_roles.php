<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLES = [
        'chair' => ['BDRRM Committee Chairperson', 'chair', ['dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests', 'reports_documentation', 'profile', 'notifications'], false, true],
        'operations_admin' => ['Operations/Admin', 'operations_admin', ['personnel_access', 'profile', 'notifications', 'inquiries'], true, false],
        'mitigation_prevention' => ['Mitigation and Prevention Sub-committee', 'mitigation_prevention', ['dashboard', 'geospatial_analytics', 'reports_documentation', 'profile', 'notifications'], false, false],
        'preparedness' => ['Preparedness Sub-committee', 'preparedness', ['dashboard', 'geospatial_analytics', 'reports_documentation', 'profile', 'notifications'], false, false],
        'response' => ['Response Sub-committee', 'response', ['dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests', 'reports_documentation', 'profile', 'notifications'], false, false],
        'recovery_rehabilitation' => ['Recovery and Rehabilitation Sub-committee', 'recovery_rehabilitation', ['dashboard', 'geospatial_analytics', 'resources_requests', 'reports_documentation', 'profile', 'notifications'], false, false],
        'tl' => ['Team Leader', 'rescuer', [], false, false],
        'tm' => ['Team Member', 'rescuer', [], false, false],
        'household_resident' => ['Household Resident', 'household_resident', [], false, false],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            return;
        }

        DB::transaction(function (): void {
            foreach ([
                'super_admin' => 'operations_admin',
                'admin' => 'operations_admin',
                'hq_admin' => 'chair',
                'pb' => 'chair',
                'hq_leader' => 'response',
                'owh' => 'response',
                'dsp' => 'response',
                'leo' => 'response',
                'ech' => 'response',
                'pao' => 'mitigation_prevention',
                'hq_personnel' => 'operations_admin',
                'rescuer' => 'tm',
            ] as $legacy => $current) {
                $this->renameLegacyRole($legacy, $current);
            }

            foreach (self::ROLES as $key => [$name, $base, $modules, $manage, $approve]) {
                $values = [
                    'role_key' => $key,
                    'role_name' => $name,
                    'name' => $name,
                    'base_role_key' => $base,
                    'web_modules' => json_encode($modules),
                    'can_manage_personnel' => $manage,
                    'can_approve_requests' => $approve,
                ];
                $values = array_intersect_key($values, array_flip(Schema::getColumnListing('roles')));
                $role = DB::table('roles')->where('role_key', $key)->first();

                if ($role) {
                    DB::table('roles')->where('role_id', $role->role_id)->update($values);
                } else {
                    $values['role_id'] = (int) DB::table('roles')->max('role_id') + 1;
                    DB::table('roles')->insert($values);
                }
            }

            $this->moveCustomFieldRolesToTeamMember();
            $this->assignFieldLeaders();
            if (Schema::hasTable('rescue_teams') && Schema::hasTable('responders')) {
                $this->linkCommitteeAccounts();
            }
        });
    }

    public function down(): void
    {
        // Role assignments are data changes and are intentionally not reversed.
    }

    private function renameLegacyRole(string $from, string $to): void
    {
        $legacy = DB::table('roles')->where('role_key', $from)->first();
        if (! $legacy) {
            return;
        }

        $target = DB::table('roles')->where('role_key', $to)->first();
        if ($target && (int) $target->role_id !== (int) $legacy->role_id) {
            DB::table('users')->where('role_id', $legacy->role_id)->update(['role_id' => $target->role_id]);
            DB::table('roles')->where('role_id', $legacy->role_id)->delete();
            return;
        }

        DB::table('roles')->where('role_id', $legacy->role_id)->update(['role_key' => $to]);
    }

    private function moveCustomFieldRolesToTeamMember(): void
    {
        if (! Schema::hasColumn('roles', 'base_role_key') || ! Schema::hasTable('responders')) {
            return;
        }

        $memberRoleId = DB::table('roles')->where('role_key', 'tm')->value('role_id');
        if (! $memberRoleId) {
            return;
        }

        $customRoleIds = DB::table('roles')
            ->where('base_role_key', 'rescuer')
            ->whereNotIn('role_key', ['tl', 'tm'])
            ->pluck('role_id');

        if ($customRoleIds->isNotEmpty()) {
            DB::table('users')->whereIn('role_id', $customRoleIds)->update(['role_id' => $memberRoleId]);
        }
    }

    private function assignFieldLeaders(): void
    {
        if (! Schema::hasTable('rescue_teams') || ! Schema::hasTable('responders')) {
            return;
        }

        $leaderRoleId = DB::table('roles')->where('role_key', 'tl')->value('role_id');
        if (! $leaderRoleId) {
            return;
        }

        $leaderIds = DB::table('rescue_teams')
            ->whereNotNull('leader_responder_id')
            ->whereRaw('UPPER(team_code) <> ?', ['HQCC'])
            ->pluck('leader_responder_id');
        $userIds = DB::table('responders')->whereIn('responder_id', $leaderIds)->pluck('user_id');
        if ($userIds->isNotEmpty()) {
            DB::table('users')->whereIn('user_id', $userIds)->update(['role_id' => $leaderRoleId]);
        }
    }

    private function linkCommitteeAccounts(): void
    {
        $teamSupport = app(\App\Services\Web\HeadquartersTeamSupport::class);
        $teamId = $teamSupport->ensureTeam();
        $accounts = DB::table('users as user')
            ->join('roles as role', 'role.role_id', '=', 'user.role_id')
            ->whereIn('role.role_key', ['chair', 'operations_admin', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation'])
            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('user.deleted_at'))
            ->orderBy('user.username')
            ->get('user.*');

        foreach ($accounts as $account) {
            $teamSupport->syncUser($account, $teamId);
        }
    }
};
