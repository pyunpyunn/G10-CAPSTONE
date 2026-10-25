<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
                $table->string('household_id')->nullable();
                $table->boolean('is_active')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('households')) {
            Schema::create('households', function (Blueprint $table): void {
                $table->string('household_id')->primary();
                $table->string('contact_number')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('household_password_recovery_otps')) {
            Schema::create('household_password_recovery_otps', function (Blueprint $table): void {
                $table->string('user_id')->primary();
                $table->string('household_id');
                $table->string('code_hash');
                $table->unsignedTinyInteger('failed_attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('sent_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('responders')) {
            Schema::create('responders', function (Blueprint $table) {
                $table->unsignedBigInteger('responder_id')->primary();
                $table->string('user_id');
                $table->integer('team_id')->nullable();
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

        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function (Blueprint $table): void {
                $table->id();
                $table->string('user_id');
                $table->integer('team_id');
                $table->string('device_uuid')->unique();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
            });
        }

        DB::table('roles')->updateOrInsert([
            'role_key' => 'rescuer',
        ], [
            'role_id' => 5,
            'role_name' => 'Rescuer',
        ]);
        DB::table('roles')->updateOrInsert([
            'role_key' => 'household_resident',
        ], [
            'role_id' => 6,
            'role_name' => 'Household Resident',
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
            'team_id' => 1,
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

        DB::table('households')->updateOrInsert([
            'household_id' => 'HH-OTP-001',
        ], [
            'contact_number' => '09171234512',
            'deleted_at' => null,
        ]);
        DB::table('users')->updateOrInsert([
            'user_id' => 'USR-HOUSEHOLD-OTP-001',
        ], [
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'name' => 'Ana Santos',
            'username' => 'household.otp.001',
            'email' => 'household.otp.001@example.test',
            'password' => Hash::make('old-password'),
            'role_id' => 6,
            'contact_number' => '09171234512',
            'household_id' => 'HH-OTP-001',
            'is_active' => 1,
            'deleted_at' => null,
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

    public function test_device_bound_mobile_login_registers_team_and_issues_scoped_token(): void
    {
        $response = $this->postJson('/api/v1/mobile/login', [
            'login' => 'BDRRM-SAR-001',
            'password' => 'password',
            'device_uuid' => 'device-rescuer-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('device_uuid', 'device-rescuer-1')
            ->assertJsonPath('team_id', 1);
        $this->assertDatabaseHas('devices', [
            'device_uuid' => 'device-rescuer-1',
            'user_id' => 'USR-RESCUER-BDRRM-SAR-001',
            'team_id' => 1,
        ]);

        $token = \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001')->tokens()->latest('id')->first();
        $this->assertSame('mobile-device:device-rescuer-1', $token->name);
        $this->assertContains('mobile:rescuer', $token->abilities);
        $this->assertContains('team:1', $token->abilities);
    }

    public function test_device_bound_mobile_login_rejects_device_uuid_owned_by_another_user(): void
    {
        DB::table('devices')->insert([
            'user_id' => 'OTHER-USER',
            'team_id' => 1,
            'device_uuid' => 'shared-device',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/mobile/login', [
            'login' => 'BDRRM-SAR-001',
            'password' => 'password',
            'device_uuid' => 'shared-device',
        ])->assertConflict();
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

    public function test_household_recovery_masks_and_validates_the_registered_household_contact(): void
    {
        $this->postJson('/api/v1/auth/password-recovery/household/lookup', [
            'login' => 'household.otp.001',
        ])->assertOk()->assertExactJson(['masked_contact_number' => '*********12']);

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'household.otp.001',
            'contact_number' => '09170000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_number');

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'household.otp.001',
            'contact_number' => '+63 917 123 4512',
        ])->assertOk()->assertJsonPath('message', 'Registered contact number verified.');
    }

    public function test_household_otp_resets_password_and_revokes_existing_sessions(): void
    {
        config([
            'services.sms_gateway.base_url' => 'https://sms.test',
            'services.sms_gateway.username' => 'test-user',
            'services.sms_gateway.password' => 'test-password',
        ]);
        $sentCode = null;
        Http::fake(function ($request) use (&$sentCode) {
            preg_match('/code is (\d{6})/', $request['textMessage']['text'], $matches);
            $sentCode = $matches[1] ?? null;
            return Http::response(['id' => 'sms-test-1'], 200);
        });

        $user = \App\Models\User::findOrFail('USR-HOUSEHOLD-OTP-001');
        $user->createToken('household-old-session');
        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'household.otp.001',
            'contact_number' => '09171234512',
        ])->assertOk();

        $this->assertNotNull($sentCode);
        Http::assertSent(fn ($request) => $request['phoneNumbers'] === ['+639171234512']);

        $response = $this->postJson('/api/v1/auth/password-recovery/household/reset', [
            'login' => 'household.otp.001',
            'contact_number' => '09171234512',
            'code' => $sentCode,
            'password' => 'new-household-password',
            'password_confirmation' => 'new-household-password',
        ]);
        $response->assertOk()->assertJsonPath('household_id', 'HH-OTP-001');
        $this->assertTrue(Hash::check('new-household-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('household_password_recovery_otps', ['user_id' => $user->user_id]);
    }

    public function test_household_otp_is_required_and_limited_to_five_attempts(): void
    {
        config([
            'services.sms_gateway.base_url' => 'https://sms.test',
            'services.sms_gateway.username' => 'test-user',
            'services.sms_gateway.password' => 'test-password',
        ]);
        Http::fake(['sms.test/*' => Http::response(['id' => 'sms-test-2'], 200)]);

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'household.otp.001',
            'contact_number' => '09171234512',
        ])->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/password-recovery/household/reset', [
                'login' => 'household.otp.001',
                'contact_number' => '09171234512',
                'code' => '000000',
                'password' => 'new-household-password',
                'password_confirmation' => 'new-household-password',
            ])->assertUnprocessable()->assertJsonValidationErrors('code');
        }

        $this->assertDatabaseMissing('household_password_recovery_otps', ['user_id' => 'USR-HOUSEHOLD-OTP-001']);
        $this->assertTrue(Hash::check('old-password', \App\Models\User::findOrFail('USR-HOUSEHOLD-OTP-001')->password));
    }

    public function test_household_otp_uses_onesignal_sms_when_the_sms_gateway_is_not_configured(): void
    {
        config([
            'services.sms_gateway.base_url' => '',
            'services.sms_gateway.username' => '',
            'services.sms_gateway.password' => '',
            'services.onesignal.app_id' => 'test-app-id',
            'services.onesignal.api_key' => 'test-api-key',
            'services.onesignal.base_url' => 'https://onesignal.test',
        ]);
        Http::fake(['onesignal.test/*' => Http::response(['id' => 'onesignal-sms-test'], 200)]);

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'household.otp.001',
            'contact_number' => '09171234512',
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/notifications?c=sms')
                && $request['target_channel'] === 'sms'
                && $request['include_phone_numbers'] === ['+639171234512']
                && preg_match('/\d{6}/', $request['contents']['en']) === 1;
        });
        $this->assertDatabaseHas('household_password_recovery_otps', [
            'user_id' => 'USR-HOUSEHOLD-OTP-001',
            'household_id' => 'HH-OTP-001',
        ]);
    }
}
