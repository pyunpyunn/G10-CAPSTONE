<?php

use App\Services\Web\HeadquartersTeamSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

            if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name') && Schema::hasColumn('users', 'name')) {
                DB::table('users')->where('username', 'BDRRM-HQCC-001')
                    ->update([
                        'first_name' => 'Command Center',
                        'last_name' => 'Personnel 1',
                        'name' => 'Command Center Personnel 1',
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Do not restore broad legacy Admin access or retired account roles.
    }
};