<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('role_id');
                $table->string('role_key')->unique();
                $table->string('role_name');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('households')) {
            Schema::create('households', function (Blueprint $table) {
                $table->string('household_id')->primary();
                $table->string('household_code')->nullable()->unique();
                $table->string('household_name');
                $table->string('contact_number')->nullable();
                $table->string('emergency_contact')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('responders')) {
            Schema::create('responders', function (Blueprint $table) {
                $table->bigIncrements('responder_id');
                $table->string('user_id')->nullable();
                $table->string('responder_code')->nullable()->unique();
                $table->string('created_by_admin_id')->nullable();
                $table->string('team_id')->nullable();
                $table->string('username')->nullable()->unique();
                $table->string('password_hash')->nullable();
                $table->string('full_name')->nullable();
                $table->string('title')->nullable();
                $table->string('contact_number')->nullable();
                $table->string('address')->nullable();
                $table->boolean('is_validated')->default(false);
                $table->boolean('is_deployed')->default(false);
                $table->string('duty_status')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }

        $now = now();

        $roleSeed = [
            ['role_key' => 'super_admin', 'role_name' => 'Super Admin'],
            ['role_key' => 'admin', 'role_name' => 'HQ/Admin'],
            ['role_key' => 'household_resident', 'role_name' => 'Household Resident'],
            ['role_key' => 'rescuer', 'role_name' => 'Rescuer'],
        ];

        foreach ($roleSeed as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_key' => $role['role_key']],
                [
                    'role_name' => $role['role_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $adminRoleId = DB::table('roles')->where('role_key', 'admin')->value('role_id');
        $householdRoleId = DB::table('roles')->where('role_key', 'household_resident')->value('role_id');
        $rescuerRoleId = DB::table('roles')->where('role_key', 'rescuer')->value('role_id');

        $password = Hash::make('password');

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
                'user_id' => 'USR-RESCUER-BDRRM-SAR-001',
                'responder_code' => 'BDRRM-SAR-001',
                'created_by_admin_id' => 'USR-HQ-2024035500',
                'team_id' => 'SAR',
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

    public function down(): void
    {
        Schema::dropIfExists('responders');
        Schema::dropIfExists('households');
        Schema::dropIfExists('roles');
    }
};
