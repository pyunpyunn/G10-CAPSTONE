<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class HqPersonnelSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->command?->error('HQ personnel accounts were not seeded because users or roles is missing.');

            return;
        }

        $personnel = [
            ['id' => 'BDRRM-HQCC-001', 'first_name' => 'Barangay', 'last_name' => 'Captain'],
            ['id' => 'BDRRM-HQCC-002', 'first_name' => 'Operational', 'last_name' => 'Head'],
            ['id' => 'BDRRM-HQCC-003', 'first_name' => 'Command Center', 'last_name' => 'Personnel 1'],
            ['id' => 'BDRRM-HQCC-004', 'first_name' => 'Command Center', 'last_name' => 'Personnel 2'],
            ['id' => 'BDRRM-HQCC-005', 'first_name' => 'Command Center', 'last_name' => 'Personnel 3'],
            ['id' => 'BDRRM-HQCC-006', 'first_name' => 'Command Center', 'last_name' => 'Personnel 4'],
            ['id' => 'BDRRM-HQCC-007', 'first_name' => 'Command Center', 'last_name' => 'Personnel 5'],
        ];
        $columns = array_flip(Schema::getColumnListing('users'));
        $initialPassword = trim((string) env('HQ_DEFAULT_PASSWORD', ''));

        if (! isset($columns['user_id'], $columns['username'], $columns['password'], $columns['role_id'])) {
            throw new RuntimeException('The users table is missing a required account column.');
        }

        if ($initialPassword === '') {
            throw new RuntimeException('Set HQ_DEFAULT_PASSWORD before seeding HQ accounts.');
        }

        $created = [];
        $skipped = [];
        $createdAdminRole = false;
        $passwordHash = Hash::make($initialPassword);

        DB::transaction(function () use ($personnel, $columns, $passwordHash, &$created, &$skipped, &$createdAdminRole): void {
            $adminRoleId = DB::table('roles')->where('role_key', 'admin')->value('role_id');

            if (! $adminRoleId) {
                $role = ['role_key' => 'admin', 'role_name' => 'Admin'];

                if (! DB::table('roles')->where('role_id', 4)->exists()) {
                    DB::table('roles')->insert(['role_id' => 4, ...$role]);
                    $adminRoleId = 4;
                } else {
                    $adminRoleId = DB::table('roles')->insertGetId($role, 'role_id');
                }

                $createdAdminRole = true;
            }

            foreach ($personnel as $member) {
                $accountId = $member['id'];
                $userId = 'USR-HQCC-'.$accountId;
                $existing = DB::table('users')
                    ->where('user_id', $userId)
                    ->orWhere('username', $accountId)
                    ->first();

                if ($existing) {
                    if (($existing->user_id ?? null) !== $userId || ($existing->username ?? null) !== $accountId) {
                        throw new RuntimeException("The HQ account identifier {$accountId} is already in use.");
                    }

                    DB::table('users')->where('user_id', $userId)->update(array_intersect_key([
                        'password' => $passwordHash,
                        'role_id' => $adminRoleId,
                        'is_active' => 1,
                        'must_change_password' => 1,
                        'updated_at' => now(),
                    ], $columns));
                    $skipped[] = $accountId;

                    continue;
                }

                $name = $member['first_name'].' '.$member['last_name'];
                $values = [
                    'user_id' => $userId,
                    'first_name' => $member['first_name'],
                    'last_name' => $member['last_name'],
                    'name' => $name,
                    'username' => $accountId,
                    'login_id' => $accountId,
                    'email' => strtolower(str_replace('-', '', $accountId)).'@resqperation.local',
                    'password' => $passwordHash,
                    'role_id' => $adminRoleId,
                    'is_active' => 1,
                    'must_change_password' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('users')->insert(array_intersect_key($values, $columns));
                $created[] = ['name' => $name, 'account_id' => $accountId];
            }
        });

        if ($createdAdminRole) {
            $this->command?->info('Created the missing admin role.');
        }

        foreach ($created as $account) {
            $this->command?->info("Created {$account['name']} | login: {$account['account_id']}");
        }

        foreach ($skipped as $accountId) {
            $this->command?->line("Updated HQ account password: {$accountId}");
        }
    }
}