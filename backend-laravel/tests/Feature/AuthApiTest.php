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

    private function passwordChangePayload(): array
    {
        return ['login' => 'BDRRM-SAR-001', 'current_password' => 'password',
            'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'];
    }

    public function test_old_password_change_requires_no_security_questions_and_revokes_sessions(): void
    {
        $user = \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001');
        $user->createToken('old-session');
        $this->postJson('/api/v1/auth/password-recovery/reset', $this->passwordChangePayload())->assertOk();
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['login' => 'BDRRM-SAR-001', 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['login' => 'BDRRM-SAR-001', 'password' => 'new-secure-password'])
            ->assertOk()->assertJsonPath('user.user_id', $user->user_id);

    }

    public function test_wrong_old_password_or_unknown_account_cannot_change_password(): void
    {
        $payload = $this->passwordChangePayload();
        $payload['current_password'] = 'incorrect';
        $this->postJson('/api/v1/auth/password-recovery/reset', $payload)->assertUnprocessable();
        $payload['login'] = 'unknown-account';
        $this->postJson('/api/v1/auth/password-recovery/reset', $payload)->assertUnprocessable();
        $this->assertTrue(Hash::check('password', \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001')->password));
    }

    public function test_password_change_rejects_mismatch_and_unchanged_password(): void
    {
        $payload = $this->passwordChangePayload();
        $payload['password_confirmation'] = 'mismatch';
        $this->postJson('/api/v1/auth/password-recovery/reset', $payload)->assertUnprocessable();
        $payload['password'] = $payload['password_confirmation'] = 'password';
        $this->postJson('/api/v1/auth/password-recovery/reset', $payload)->assertUnprocessable();
    }

    public function test_inactive_account_cannot_change_password(): void
    {
        DB::table('users')->where('user_id', 'USR-RESCUER-BDRRM-SAR-001')->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/password-recovery/reset', $this->passwordChangePayload())->assertForbidden();
    }

    public function test_mobile_role_can_change_password_through_authenticated_profile_endpoint(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(true);
        });
        $user = \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001');
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->patchJson('/api/v1/profile/password', $this->passwordChangePayload())->assertOk();
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertFalse((bool) $user->fresh()->must_change_password);
    }

    public function test_password_change_verification_checks_database_account_then_old_password(): void
    {
        $this->postJson('/api/v1/auth/password-recovery/verify', ['login' => 'unknown-account'])
            ->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->postJson('/api/v1/auth/password-recovery/verify', ['login' => 'BDRRM-SAR-001'])->assertOk();
        $this->postJson('/api/v1/auth/password-recovery/verify', ['login' => 'BDRRM-SAR-001', 'current_password' => 'incorrect'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $response = $this->postJson('/api/v1/auth/password-recovery/verify', ['login' => 'BDRRM-SAR-001', 'current_password' => 'password']);
        $response->assertOk()->assertExactJson(['message' => 'Old password verified.']);
        $this->assertTrue(Hash::check('password', \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001')->password));
    }

    public function test_direct_password_change_still_checks_account_and_password_without_preverification(): void
    {
        $payload = $this->passwordChangePayload();
        $payload['login'] = 'unknown-account';
        $this->postJson('/api/v1/auth/password-recovery/reset', $payload)->assertUnprocessable()->assertJsonValidationErrors('login');
        $payload['login'] = 'BDRRM-SAR-001';
        $payload['current_password'] = 'wrong-password';
        $this->postJson('/api/v1/auth/password-recovery/reset', $payload)->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }
}


