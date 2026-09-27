<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('role_id');
                $table->string('role_key');
                $table->string('role_name');
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->string('user_id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('name')->nullable();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->integer('role_id')->nullable();
                $table->string('contact_number')->nullable();
                $table->boolean('is_active')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('responders')) {
            Schema::create('responders', function (Blueprint $table) {
                $table->unsignedBigInteger('responder_id')->primary();
                $table->string('user_id');
                $table->string('responder_code');
                $table->string('username');
                $table->string('password_hash');
                $table->boolean('is_validated')->default(true);
                $table->string('full_name')->nullable();
                $table->string('title')->nullable();
                $table->string('contact_number')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        DB::table('roles')->updateOrInsert([
            'role_key' => 'rescuer',
        ], [
            'role_id' => 5,
            'role_name' => 'Rescuer',
        ]);

        DB::table('users')->updateOrInsert([
            'user_id' => 'USR-RESCUER-BDRRM-SAR-001',
        ], [
            'first_name' => 'Miguel',
            'last_name' => 'Reyes',
            'name' => 'Miguel Reyes',
            'username' => 'miguel.reyes',
            'email' => 'bdrrm.sar.001@rescuer.resqperation.local',
            'password' => Hash::make('password'),
            'role_id' => 5,
            'contact_number' => '09170001001',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('responders')->updateOrInsert([
            'responder_code' => 'BDRRM-SAR-001',
        ], [
            'responder_id' => 2024035502,
            'user_id' => 'USR-RESCUER-BDRRM-SAR-001',
            'username' => 'BDRRM-SAR-001',
            'password_hash' => Hash::make('password'),
            'is_validated' => 1,
            'full_name' => 'Miguel Reyes',
            'title' => 'Responder',
            'contact_number' => '09170001001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_protected_api_routes_return_json_when_not_logged_in(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_rescuer_mobile_login_accepts_current_bdrm_account_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'BDRRM-SAR-001',
            'password' => 'password',
            'device_name' => 'resqperation-mobile',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role.role_key', 'rescuer')
            ->assertJsonPath('user.user_id', 'USR-RESCUER-BDRRM-SAR-001')
            ->assertJsonPath('message', 'Login successful.');
    }
}
