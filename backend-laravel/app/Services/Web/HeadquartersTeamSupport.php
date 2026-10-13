<?php

namespace App\Services\Web;

use App\Services\Mobile\RescuerAccountSupport;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class HeadquartersTeamSupport
{
    public const CAPTAIN_ACCOUNT_ID = 'BDRRM-HQCC-001';

    public function __construct(private RescuerAccountSupport $support) {}

    public function ensureTeam(): int
    {
        $team = DB::table('rescue_teams')->whereRaw('UPPER(team_code) = ?', ['HQCC'])->lockForUpdate()->first();
        if ($team) {
            return (int) $team->team_id;
        }

        $id = $this->support->nextId('rescue_teams', 'team_id');
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
        $accountId = strtoupper((string) ($user->username ?? ''));
        if ($accountId === self::CAPTAIN_ACCOUNT_ID || ! preg_match('/^BDRRM-HQCC-\d{3}$/', $accountId)) {
            return null;
        }

        $existing = DB::table('responders')->where('user_id', $user->user_id)->lockForUpdate()->first();
        if ($existing) {
            // A removed profile must not be resurrected by another HQ account update.
            if ($existing->deleted_at ?? null) return null;
            DB::table('responders')->where('responder_id', $existing->responder_id)->update($this->columns('responders', [
                'full_name' => $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
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
            'full_name' => $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'title' => $accountId === 'BDRRM-HQCC-002' ? 'HQCC Operational Head' : 'HQ Personnel',
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
            $accounts = DB::table('users')->where('username', 'like', 'BDRRM-HQCC-%')
                ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderBy('username')->lockForUpdate()->get();

            foreach ($accounts as $account) {
                $id = $this->syncUser($account, $teamId);
                // The existing Operational Head is the initial leader; later user edits are preserved.
                if ($id && strtoupper($account->username) === 'BDRRM-HQCC-002') {
                    DB::table('rescue_teams')->where('team_id', $teamId)->whereNull('leader_responder_id')
                        ->whereExists(fn ($query) => $query->selectRaw('1')->from('responders')
                            ->where('responder_id', $id)->where('team_id', $teamId))
                        ->update(['leader_responder_id' => $id, 'updated_at' => now()]);
                }
            }
        });
    }

    private function columns(string $table, array $values): array
    {
        return array_intersect_key($values, array_flip(Schema::getColumnListing($table)));
    }
}
