<?php

namespace App\Services\Web;

use App\Services\Mobile\RescuerAccountSupport;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class HeadquartersTeamSupport
{
    public function __construct(private RescuerAccountSupport $support) {}

    public function personnelRoleId(): int
    {
        return $this->ensureRole('operations_admin', 'Operations/Admin');
    }

    public function leaderRoleId(): int
    {
        return $this->ensureRole('operations_admin', 'Operations/Admin');
    }

    public function demoteLeader(string $userId): void
    {
        // Team leadership is no longer an authorization grant for web users.
    }

    private function ensureRole(string $key, string $name): int
    {
        $id = DB::table('roles')->where('role_key', $key)->value('role_id');
        if ($id) return (int) $id;
        $id = (int) DB::table('roles')->max('role_id') + 1;
        DB::table('roles')->insert(['role_id' => $id, 'role_key' => $key, 'role_name' => $name]);

        return $id;
    }

    public function synchronizeLeadership(int $teamId, ?int $leaderId): void
    {
        $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();
        if (! $team || (strtoupper($team->team_code) !== 'HQCC' && ! in_array(strtoupper($team->team_type), ['HQ', 'HQCC', 'HEADQUARTERS'], true))) return;
        $profiles = DB::table('responders')->where('team_id', $teamId)->whereNull('deleted_at')->get(['responder_id', 'user_id']);
        foreach ($profiles as $profile) {
            $user = DB::table('users')->where('user_id', $profile->user_id)->first(['role_id']);
            $accessRole = $user ? \App\Models\Role::query()->find($user->role_id) : null;
            if ($accessRole) DB::table('responders')->where('responder_id', $profile->responder_id)
                ->update(['title' => $accessRole->role_name, 'updated_at' => now()]);
        }
        app(PersonnelAccessNotifier::class)->notify($profiles->pluck('user_id')->all());
    }

    public function ensureTeam(): int
    {
        $team = DB::table('rescue_teams')->whereRaw('UPPER(team_code) = ?', ['HQCC'])->lockForUpdate()->first();
        if ($team) {
            return (int) $team->team_id;
        }

        $id = $this->support->nextId('rescue_teams', 'team_id');
        // ID allocation serializes concurrent creation; check again after acquiring that lock.
        $existingId = DB::table('rescue_teams')->whereRaw('UPPER(team_code) = ?', ['HQCC'])->value('team_id');
        if ($existingId) return (int) $existingId;
        DB::table('rescue_teams')->insert($this->columns('rescue_teams', [
            'team_id' => $id, 'team_code' => 'HQCC',
            'team_name' => 'HQ Command Center', 'team_type' => 'HQCC',
            'duty_status' => 'on_duty', 'assigned_purok_id' => null,
            'leader_responder_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]));

        return $id;
    }

    /** Link an HQ account without changing its login, password, role, or existing assignment. */
    public function syncUser(object $user, ?int $teamId = null): ?int
    {
        $role = \App\Models\Role::query()->find($user->role_id ?? null);
        $base = strtolower((string) ($role?->base_role_key ?: $role?->role_key));
        if (! in_array($base, ['chair', 'operations_admin', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation'], true)) {
            return null;
        }
        $accountId = strtoupper((string) ($user->username ?? ''));

        $existing = DB::table('responders')->where('user_id', $user->user_id)->lockForUpdate()->first();
        if ($existing) {
            // A removed profile must not be resurrected by another HQ account update.
            if ($existing->deleted_at ?? null) return null;
            DB::table('responders')->where('responder_id', $existing->responder_id)->update($this->columns('responders', [
                'full_name' => ($user->name ?? '') ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'title' => $role->role_name,
                'contact_number' => $user->contact_number ?? null,
                'updated_at' => now(),
            ]));

            return (int) $existing->responder_id;
        }

        $teamId ??= $this->ensureTeam();
        $id = $this->support->nextResponderId();
        DB::table('responders')->insert($this->columns('responders', [
            'responder_id' => $id, 'user_id' => $user->user_id, 'team_id' => $teamId,
            'username' => $user->username, 'responder_code' => $accountId,
            'password_hash' => $user->password,
            'full_name' => ($user->name ?? '') ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'title' => $role->role_name,
            'contact_number' => $user->contact_number ?? null,
            'is_validated' => 1, 'is_deployed' => 0,
            'duty_status' => ($user->is_active ?? true) ? 'on_duty' : 'disabled',
            'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]));

        return $id;
    }

    public function backfill(): void
    {
        DB::transaction(function (): void {
            $teamId = $this->ensureTeam();
            $accounts = DB::table('users as u')
                ->join('roles as r', 'r.role_id', '=', 'u.role_id')
                ->whereIn('r.role_key', ['chair', 'operations_admin', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation'])
                ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderBy('u.username')->lockForUpdate()->get('u.*');

            foreach ($accounts as $account) {
                $this->syncUser($account, $teamId);
            }
            $leaderId = DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id');
            $this->synchronizeLeadership($teamId, $leaderId ? (int) $leaderId : null);
        });
    }

    private function columns(string $table, array $values): array
    {
        return array_intersect_key($values, array_flip(Schema::getColumnListing($table)));
    }
}
