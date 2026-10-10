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
        });

        DB::table('roles')->insert([
            'role_id' => 1,
            'role_key' => 'admin',
            'role_name' => 'Admin',
        ]);
    }

    public function test_captain_account_uses_one_reserved_identifier_and_updates_seeded_slot(): void
    {
        DB::table('users')->insert([
            'user_id' => 'USR-HQCC-BDRRM-HQCC-001',
            'username' => 'BDRRM-HQCC-001',
            'first_name' => 'Barangay',
            'last_name' => 'Captain',
            'name' => 'Barangay Captain',
            'email' => 'captain@example.test',
            'password' => 'existing-hash',
            'role_id' => 1,
            'is_active' => 1,
            'must_change_password' => 0,
        ]);

        $this->withoutMiddleware()->postJson('/api/v1/headquarters-accounts', [
            'account_type' => 'barangay_captain',
            'first_name' => 'Alicia',
            'last_name' => 'Reyes',
            'email' => 'captain@example.test',
            'contact_number' => '09170000000',
        ])->assertOk()->assertJsonPath('data.account.account_id', 'BDRRM-HQCC-001');

        $this->assertSame(1, DB::table('users')->where('username', 'BDRRM-HQCC-001')->count());
        $this->assertSame('Alicia', DB::table('users')->where('username', 'BDRRM-HQCC-001')->value('first_name'));
        $this->assertSame(0, (int) DB::table('users')->where('username', 'BDRRM-HQCC-001')->value('must_change_password'));
        $this->assertSame(0, DB::table('responders')->count());
    }

    public function test_command_center_account_uses_next_hqcc_id_without_becoming_a_responder(): void
    {
        foreach ([1, 2] as $sequence) {
            $accountId = sprintf('BDRRM-HQCC-%03d', $sequence);
            DB::table('users')->insert([
                'user_id' => 'USR-HQCC-'.$accountId,
                'username' => $accountId,
                'password' => 'existing-hash',
                'role_id' => 1,
                'is_active' => 1,
                'must_change_password' => 1,
            ]);
        }

        $this->withoutMiddleware()->postJson('/api/v1/headquarters-accounts', [
            'account_type' => 'command_center',
            'first_name' => 'Mara',
            'last_name' => 'Santos',
            'email' => 'mara@example.test',
            'contact_number' => '09171111111',
            'password' => 'temporary-password',
        ])->assertCreated()->assertJsonPath('data.account.account_id', 'BDRRM-HQCC-003');

        $this->assertSame('admin', DB::table('users as u')->join('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.username', 'BDRRM-HQCC-003')->value('r.role_key'));
        $this->assertSame(0, DB::table('responders')->count());
    }
}
