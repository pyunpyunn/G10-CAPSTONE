<?php

use App\Services\Web\HeadquartersTeamSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')
            || ! Schema::hasTable('responders') || ! Schema::hasTable('rescue_teams')) {
            return;
        }

        DB::transaction(function (): void {
            $teams = app(HeadquartersTeamSupport::class);
            $personnelRoleId = $teams->personnelRoleId();
            $teams->leaderRoleId();
            $teams->backfill();

            $legacyRoleIds = DB::table('roles')->whereIn('role_key', ['admin', 'hq_admin'])->pluck('role_id');
            if ($legacyRoleIds->isNotEmpty()) {
                DB::table('users')->whereIn('role_id', $legacyRoleIds)
                    ->where('username', 'like', 'BDRRM-HQCC-%')
                    ->where('user_id', 'like', 'USR-HQCC-%')
                    ->update(['role_id' => $personnelRoleId, 'updated_at' => now()]);
            }

            foreach (DB::table('rescue_teams')->get(['team_id', 'leader_responder_id']) as $team) {
                $teams->synchronizeLeadership((int) $team->team_id, $team->leader_responder_id ? (int) $team->leader_responder_id : null);
            }

        });
    }

    public function down(): void
    {
        // Do not restore broad legacy Admin access or retired account roles.
    }
};