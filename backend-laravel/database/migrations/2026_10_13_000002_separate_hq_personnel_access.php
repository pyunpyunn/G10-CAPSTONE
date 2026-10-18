<?php

use App\Services\Web\HeadquartersTeamSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $support = app(HeadquartersTeamSupport::class);
            $roleId = $support->personnelRoleId();
            $adminIds = DB::table('roles')->whereIn('role_key', ['admin', 'hq_admin'])->pluck('role_id');
            // These are the HQ accounts provisioned as Admin by the old HQ workflow.
            DB::table('users')->where('user_id', 'like', 'USR-HQCC-%')
                ->where('username', 'like', 'BDRRM-HQCC-%')
                ->whereIn('role_id', $adminIds)->update(['role_id' => $roleId, 'updated_at' => now()]);
            foreach (DB::table('rescue_teams')->get() as $team) {
                $support->synchronizeLeadership((int) $team->team_id, $team->leader_responder_id ? (int) $team->leader_responder_id : null);
            }
        });
    }
    public function down(): void
    {
        // Do not restore elevated Admin access to ordinary HQ personnel.
    }
};
