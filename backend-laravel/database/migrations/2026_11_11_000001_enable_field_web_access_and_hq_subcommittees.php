<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('roles')->whereIn('role_key', ['tl', 'tm'])->update([
                'web_modules' => json_encode(['field_assignments', 'profile', 'notifications']),
            ]);

            foreach ([
                'MPSC' => 'Mitigation and Prevention Sub-committee',
                'PSC' => 'Preparedness Sub-committee',
                'RSC' => 'Response Sub-committee',
                'RRSC' => 'Recovery and Rehabilitation Sub-committee',
            ] as $code => $name) {
                if (DB::table('rescue_teams')->where('team_name', $name)->exists()) continue;
                if (DB::table('rescue_teams')->where('team_code', $code)->exists()) {
                    throw new RuntimeException("Team code {$code} is already used; resolve the conflict before creating the subcommittee.");
                }
                DB::table('rescue_teams')->insert([
                    'team_id' => app(\App\Services\Mobile\RescuerAccountSupport::class)->nextId('rescue_teams', 'team_id'),
                    'team_code' => $code, 'team_name' => $name, 'team_type' => 'HQ',
                    'duty_status' => 'on_duty', 'leader_responder_id' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve organizational records and any subsequently assigned personnel.
    }
};
