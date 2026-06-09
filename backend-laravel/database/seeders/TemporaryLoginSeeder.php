<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TemporaryLoginSeeder extends Seeder
{
    public function run(): void
    {
        $adminRoleId = DB::table('roles')->where('role_key', 'admin')->value('role_id');
        $householdRoleId = DB::table('roles')->where('role_key', 'household_resident')->value('role_id');
        $rescuerRoleId = DB::table('roles')->where('role_key', 'rescuer')->value('role_id');
        $sarTeamId = DB::table('rescue_teams')->where('team_code', 'SAR')->value('team_id');

        if (! $adminRoleId || ! $householdRoleId || ! $rescuerRoleId) {
            $this->command?->warn('Temporary users were not seeded because required roles are missing.');

            return;
        }

        $password = Hash::make('password');
        $now = now();

        if (! DB::table('users')->where('username', '2024035500')->exists()) {
            DB::table('users')->insert([
                'user_id' => 'USR-HQ-2024035500',
                'first_name' => 'HQ',
                'last_name' => 'Admin',
                'username' => '2024035500',
                'email' => 'hq.temp@resqperation.local',
                'password' => $password,
                'role_id' => $adminRoleId,
                'contact_number' => '09170000000',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('households')->where('household_id', 'HH-2024035501')->exists()) {
            DB::table('households')->insert([
                'household_id' => 'HH-2024035501',
                'household_code' => 'HH-2024035501',
                'household_name' => 'Temporary Household Account',
                'contact_number' => '09170000001',
                'emergency_contact' => '09170000002',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('users')->where('username', '2024035501')->exists()) {
            DB::table('users')->insert([
                'user_id' => 'USR-HH-2024035501',
                'first_name' => 'Temporary Household',
                'last_name' => 'User',
                'username' => '2024035501',
                'email' => 'household.temp@resqperation.local',
                'password' => $password,
                'role_id' => $householdRoleId,
                'contact_number' => '09170000001',
                'household_id' => 'HH-2024035501',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('users')->where('user_id', 'USR-RESCUER-BDRRM-SAR-001')->exists()) {
            DB::table('users')->insert([
                'user_id' => 'USR-RESCUER-BDRRM-SAR-001',
                'first_name' => 'Temporary Rescuer',
                'last_name' => 'User',
                'name' => 'Temporary Rescuer User',
                'username' => 'temporary.rescuer.user',
                'email' => 'rescuer.temp@resqperation.local',
                'password' => $password,
                'role_id' => $rescuerRoleId,
                'contact_number' => '09170000003',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('responders')->where('username', 'BDRRM-SAR-001')->exists()) {
            DB::table('responders')->insert([
                'responder_id' => 2024035502,
                'user_id' => 'USR-RESCUER-BDRRM-SAR-001',
                'responder_code' => 'BDRRM-SAR-001',
                'created_by_admin_id' => 'USR-HQ-2024035500',
                'team_id' => $sarTeamId,
                'username' => 'BDRRM-SAR-001',
                'password_hash' => $password,
                'full_name' => 'Temporary Rescuer User',
                'title' => 'Responder',
                'contact_number' => '09170000003',
                'address' => 'Temporary testing account',
                'is_validated' => 1,
                'is_deployed' => 0,
                'duty_status' => 'off_duty',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
