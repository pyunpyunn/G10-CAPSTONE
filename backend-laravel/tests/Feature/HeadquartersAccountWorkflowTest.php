<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HeadquartersAccountWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary();
            $table->string('role_key');
            $table->string('role_name');
            $table->string('base_role_key')->nullable();
            $table->json('web_modules')->nullable();
            $table->boolean('can_manage_personnel')->nullable();
            $table->boolean('can_approve_requests')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('username')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->integer('role_id')->nullable();
            $table->string('contact_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(true);
            $table->timestamps();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->increments('responder_id');
            $table->string('user_id');
            $table->string('username')->nullable();
            $table->integer('team_id')->nullable();
            $table->string('responder_code')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('full_name')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_validated')->default(true);
            $table->boolean('is_deployed')->default(false);
            $table->string('duty_status')->default('available');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->increments('team_id');
            $table->string('team_code')->unique();
            $table->string('team_name');
            $table->string('team_type');
            $table->string('duty_status');
            $table->integer('assigned_purok_id')->nullable();
            $table->integer('leader_responder_id')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'role_id' => 1,
            'role_key' => 'operations_admin',
            'role_name' => 'Operations/Admin',
            'base_role_key' => 'operations_admin',
            'web_modules' => json_encode(['personnel_access', 'profile', 'notifications', 'inquiries']),
            'can_manage_personnel' => true,
            'can_approve_requests' => false,
        ]);
    }

    public function test_retired_account_type_is_rejected(): void
    {
        $this->withoutMiddleware()->postJson('/api/v1/headquarters-accounts', [
            'account_type' => 'retired_role',
            'first_name' => 'Alicia', 'last_name' => 'Reyes', 'password' => 'temporary-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('account_type');

        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('responders')->count());
    }

    public function test_hq_personnel_account_uses_next_id_and_is_linked_to_database_team(): void
    {
        $this->withoutMiddleware()->postJson('/api/v1/headquarters-accounts', [
            'account_type' => 'command_center',
            'first_name' => 'Mara',
            'last_name' => 'Santos',
            'email' => 'mara@example.test',
            'contact_number' => '09171111111',
            'password' => 'temporary-password',
            'role_id' => 1,
        ])->assertCreated()->assertJsonPath('data.account.account_id', 'OPS-0001');

        $this->assertSame('operations_admin', DB::table('users as u')->join('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.username', 'OPS-0001')->value('r.role_key'));
        $this->assertSame(1, DB::table('responders')->where('username', 'OPS-0001')->count());
        $this->assertSame(1, DB::table('rescue_teams')->where('team_code', 'HQCC')->count());
    }
}
