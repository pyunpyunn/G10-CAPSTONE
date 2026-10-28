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

        if (Schema::hasTable('device_tokens')) {
            DB::table('device_tokens')->delete();
        }

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

        if (! Schema::hasTable('password_reset_requests')) {
            Schema::create('password_reset_requests', function (Blueprint $table): void {
                $table->uuid('request_id')->primary();
                $table->string('otp_subject_key', 64)->nullable()->unique();
                $table->string('verification_method');
                $table->timestamp('consumed_at')->nullable();
                $table->string('user_id');
                $table->string('household_id');
                $table->string('code_hash')->nullable();
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

        if (! Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('app_role')->nullable();
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
        DB::table('roles')->updateOrInsert([
            'role_key' => 'operations_admin',
        ], [
            'role_id' => 4,
            'role_name' => 'Operations/Admin',
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

    public function test_login_returns_password_reset_guidance_for_an_unsupported_stored_hash(): void
    {
        DB::table('users')->where('user_id', 'USR-RESCUER-BDRRM-SAR-001')
            ->update(['password' => str_repeat('x', 43)]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'BDRRM-SAR-001',
            'password' => 'password',
        ])->assertConflict()->assertJson([
            'message' => 'This account password cannot be verified. Use Forgot password to reset it, or contact HQ/Admin.',
        ]);
    }

    public function test_login_accepts_argon2id_hashes_alongside_bcrypt_hashes(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id is not available in this PHP build.');
        }

        DB::table('users')->where('user_id', 'USR-RESCUER-BDRRM-SAR-001')
            ->update(['password' => password_hash('password', PASSWORD_ARGON2ID)]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'BDRRM-SAR-001',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.role.role_key', 'rescuer');
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
        $this->assertDatabaseHas('device_tokens', [
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
        DB::table('device_tokens')->insert([
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

    public function test_device_bound_rescuer_tokens_cannot_use_legacy_routes_without_the_device_identifier(): void
    {
        $login = $this->postJson('/api/v1/mobile/login', [
            'login' => 'BDRRM-SAR-001',
            'password' => 'password',
            'device_uuid' => 'device-rescuer-bound',
        ])->assertOk();

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/rescuer/overview')
            ->assertForbidden()
            ->assertJson([
                'message' => 'The device identifier does not match this access token.',
            ]);
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

    public function test_profile_password_change_rejects_invalid_inputs_without_changing_password(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $user = \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001');
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $payload = $this->passwordChangePayload();
        foreach ([
            ['current_password' => 'wrong-password'],
            ['password_confirmation' => 'mismatch'],
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => 'password', 'password_confirmation' => 'password'],
            ['current_password' => ''],
            ['password_confirmation' => ''],
            ['password' => str_repeat('x', 129), 'password_confirmation' => str_repeat('x', 129)],
        ] as $invalid) {
            $this->patchJson('/api/v1/profile/password', array_replace($payload, $invalid))->assertUnprocessable();
            $this->assertTrue(Hash::check('password', $user->fresh()->password));
        }
    }

    public function test_profile_password_change_requires_authentication(): void
    {
        $this->patchJson('/api/v1/profile/password', $this->passwordChangePayload())->assertUnauthorized();
    }

    public function test_all_profile_roles_can_change_password_and_only_other_tokens_are_revoked(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach ([4, 5, 6] as $roleId) {
            $user = \App\Models\User::findOrFail('USR-RESCUER-BDRRM-SAR-001');
            $user->forceFill(['role_id' => $roleId, 'password' => Hash::make('password')])->save();
            $current = $user->createToken('current-device');
            $other = $user->createToken('other-device');
            $this->app['auth']->forgetGuards();
            $this->withToken($current->plainTextToken)
                ->patchJson('/api/v1/profile/password', $this->passwordChangePayload())->assertOk();
            $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
            $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
            $this->app['auth']->forgetGuards();
            $this->withToken($current->plainTextToken)->getJson('/api/v1/auth/me')->assertOk();
        }
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
            'login' => 'HH-OTP-001',
        ])->assertOk()->assertExactJson(['masked_contact_number' => '*********12']);

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09170000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_number');

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'HH-OTP-001',
            'contact_number' => '+63 917 123 4512',
        ])->assertOk()->assertJsonPath('message', 'Registered contact number verified.');
    }

    public function test_rescuer_id_recovery_uses_the_linked_registered_contact(): void
    {
        $this->postJson('/api/v1/auth/password-recovery/household/lookup', [
            'login' => 'BDRRM-SAR-001',
        ])->assertOk()->assertExactJson(['masked_contact_number' => '*********01']);

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'BDRRM-SAR-001',
            'contact_number' => '09170001001',
        ])->assertOk()->assertJsonPath('message', 'Registered contact number verified.');
    }

    public function test_existing_household_without_registered_phone_is_not_reported_as_missing_and_cannot_request_otp(): void
    {
        DB::table('households')->where('household_id', 'HH-OTP-001')->update(['contact_number' => null]);
        DB::table('users')->where('user_id', 'USR-HOUSEHOLD-OTP-001')->update(['contact_number' => null]);

        $this->postJson('/api/v1/auth/password-recovery/household/lookup', [
            'login' => 'HH-OTP-001',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.login.0', 'This account exists, but no valid registered recovery phone number is on file. Contact an administrator to update it before requesting an OTP.');

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.contact_number.0', 'This account exists, but no valid registered recovery phone number is on file. Contact an administrator to update it before requesting an OTP.');

        $this->assertDatabaseMissing('password_reset_requests', [
            'user_id' => 'USR-HOUSEHOLD-OTP-001',
        ]);
    }

    public function test_household_otp_resets_password_and_revokes_existing_sessions(): void
    {
        config([
            'services.onesignal.app_id' => '',
            'services.onesignal.api_key' => '',
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
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertOk();

        $this->assertNotNull($sentCode);
        Http::assertSent(fn ($request) => $request['phoneNumbers'] === ['+639171234512']);

        $response = $this->postJson('/api/v1/auth/password-recovery/household/reset', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
            'code' => $sentCode,
            'password' => 'new-household-password',
            'password_confirmation' => 'new-household-password',
        ]);
        $response->assertOk()->assertJsonPath('household_id', 'HH-OTP-001');
        $this->assertTrue(Hash::check('new-household-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('password_reset_requests', ['user_id' => $user->user_id, 'code_hash' => null]);
    }

    public function test_sms_gateway_allows_three_sends_then_returns_a_friendly_cooldown(): void
    {
        config([
            'services.sms_gateway.username' => 'test-user',
            'services.sms_gateway.password' => 'test-password',
            'services.sms_gateway.base_url' => 'https://sms.test',
        ]);
        Http::fake(['sms.test/*' => Http::response(['id' => 'gateway-sms-cooldown'], 200)]);

        $payload = [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/password-recovery/household/send-code', $payload)
                ->assertOk()
                ->assertJsonPath('delivery_channel', 'sms_gateway')
                ->assertJsonPath('message', 'Android SMS Gateway accepted the password reset code for delivery.');
        }

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', $payload)
            ->assertOk()
            ->assertJsonPath('cooldown', true)
            ->assertJsonPath('message', 'Please wait before requesting another verification code.')
            ->assertJsonPath('cooldown_seconds', 60);

        Http::assertSentCount(3);
    }

    public function test_household_otp_is_required_and_limited_to_five_attempts(): void
    {
        config([
            'services.onesignal.app_id' => '',
            'services.onesignal.api_key' => '',
            'services.sms_gateway.base_url' => 'https://sms.test',
            'services.sms_gateway.username' => 'test-user',
            'services.sms_gateway.password' => 'test-password',
        ]);
        Http::fake(['sms.test/*' => Http::response(['id' => 'sms-test-2'], 200)]);

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/password-recovery/household/reset', [
                'login' => 'HH-OTP-001',
                'contact_number' => '09171234512',
                'code' => '000000',
                'password' => 'new-household-password',
                'password_confirmation' => 'new-household-password',
            ])->assertUnprocessable()->assertJsonValidationErrors('code');
        }

        $this->assertDatabaseHas('password_reset_requests', ['user_id' => 'USR-HOUSEHOLD-OTP-001', 'code_hash' => null]);
        $this->assertTrue(Hash::check('old-password', \App\Models\User::findOrFail('USR-HOUSEHOLD-OTP-001')->password));
    }

    public function test_password_recovery_requires_a_configured_sms_provider(): void
    {
        config([
            'services.sms_gateway.base_url' => '',
            'services.sms_gateway.username' => '',
            'services.sms_gateway.password' => '',
            'services.semaphore.api_key' => 'unused-key',
            'services.onesignal.app_id' => 'unused-app',
            'services.onesignal.api_key' => 'unused-key',
        ]);
        Http::fake();

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertStatus(503)->assertJsonPath(
            'message',
            'Android SMS Gateway URL and credentials are not configured.'
        );

        Http::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_requests', [
            'user_id' => 'USR-HOUSEHOLD-OTP-001',
        ]);
    }

    public function test_otp_cannot_be_sent_to_a_different_phone_number(): void
    {
        Http::fake();
        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09178889912',
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_number');
        Http::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_requests', [
            'user_id' => 'USR-HOUSEHOLD-OTP-001',
        ]);
    }

    public function test_password_recovery_uses_only_gateway_even_when_other_providers_are_configured(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config([
            'services.sms_gateway.base_url' => 'https://sms-gateway.test',
            'services.sms_gateway.username' => 'gateway-user',
            'services.sms_gateway.password' => 'gateway-password',
            'services.semaphore.api_key' => 'unused-key',
            'services.onesignal.app_id' => 'unused-app',
            'services.onesignal.api_key' => 'unused-key',
        ]);
        Http::fake(['sms-gateway.test/*' => Http::response(['id' => 'private-otp'], 200)]);
        Http::preventStrayRequests();

        $response = $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertOk()->assertJsonPath('delivery_channel', 'sms_gateway');

        Http::assertSentCount(1);
        $sentCode = null;
        Http::assertSent(function ($request) use (&$sentCode): bool {
            preg_match('/code is (\d{6})/', $request['textMessage']['text'], $matches);
            $sentCode = $matches[1] ?? null;
            return str_starts_with($request->url(), 'https://sms-gateway.test/')
                && $request['phoneNumbers'] === ['+639171234512']
                && $sentCode !== null;
        });
        $this->assertStringNotContainsString($sentCode, $response->getContent());
        $recovery = DB::table('password_reset_requests')
            ->where('user_id', 'USR-HOUSEHOLD-OTP-001')->first();
        $this->assertNotSame($sentCode, $recovery->code_hash);
        $this->assertTrue(Hash::check($sentCode, $recovery->code_hash));
    }

    public function test_password_recovery_uses_sms_gate_cloud_server_credentials_and_api_path(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config([
            'services.semaphore.api_key' => '',
            'services.onesignal.app_id' => '',
            'services.onesignal.api_key' => '',
            'services.sms_gateway.base_url' => 'https://api.sms-gate.app/3rdparty/v1',
            'services.sms_gateway.username' => 'cloud-server-user',
            'services.sms_gateway.password' => 'cloud-server-password',
            'services.sms_gateway.message_path' => '/messages',
        ]);
        DB::table('password_reset_requests')
            ->where('user_id', 'USR-HOUSEHOLD-OTP-001')
            ->delete();
        $sentCode = null;
        Http::fake([
            'api.sms-gate.app/*' => function ($request) use (&$sentCode) {
                preg_match('/code is (\d{6})/', $request['textMessage']['text'], $matches);
                $sentCode = $matches[1] ?? null;
                return Http::response(['id' => 'sms-gate-cloud-otp-1'], 200);
            },
        ]);

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.sms-gate.app/3rdparty/v1/messages'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('cloud-server-user:cloud-server-password'))
                && $request['phoneNumbers'] === ['+639171234512'];
        });
        $this->assertNotNull($sentCode);
        $recovery = DB::table('password_reset_requests')
            ->where('user_id', 'USR-HOUSEHOLD-OTP-001')
            ->first();
        $this->assertTrue(Hash::check($sentCode, $recovery->code_hash));
    }

    public function test_gateway_failure_never_falls_back_to_other_providers(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config([
            'services.sms_gateway.base_url' => 'https://sms-gateway.test',
            'services.sms_gateway.username' => 'test-user',
            'services.sms_gateway.password' => 'test-password',
            'services.onesignal.app_id' => 'test-app-id',
            'services.onesignal.api_key' => 'test-api-key',
            'services.onesignal.base_url' => 'https://onesignal.test',
        ]);
        DB::table('password_reset_requests')
            ->where('user_id', 'USR-HOUSEHOLD-OTP-001')
            ->delete();
        Http::fake([
            'sms-gateway.test/*' => Http::response(['message' => 'gateway unavailable'], 503),
            'onesignal.test/*' => Http::response(['errors' => ['SMS is not enabled']], 400),
        ]);

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'HH-OTP-001',
            'contact_number' => '09171234512',
        ])->assertStatus(503)->assertJsonPath(
            'message',
            'Android SMS Gateway did not accept the message.'
        );

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'onesignal.test'));
        $this->assertDatabaseMissing('password_reset_requests', [
            'user_id' => 'USR-HOUSEHOLD-OTP-001',
        ]);
    }

    public function test_any_active_system_user_can_recover_with_their_registered_phone_number(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        DB::table('users')->insert([
            'user_id' => 'USR-SYSTEM-ADMIN-001',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'name' => 'System Administrator',
            'username' => 'system.admin',
            'email' => 'system.admin@example.test',
            'password' => Hash::make('old-admin-password'),
            'role_id' => 4,
            'contact_number' => '09178889912',
            'household_id' => null,
            'is_active' => 1,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/password-recovery/household/lookup', [
            'login' => 'system.admin',
        ])->assertOk()->assertExactJson(['masked_contact_number' => '*********12']);

        $this->postJson('/api/v1/auth/password-recovery/household/lookup', [
            'login' => 'outside.system',
        ])->assertUnprocessable()->assertJsonValidationErrors('login');

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'system.admin',
            'contact_number' => '09171234512',
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_number');

        $this->postJson('/api/v1/auth/password-recovery/household/validate-contact', [
            'login' => 'system.admin@example.test',
            'contact_number' => '09178889912',
        ])->assertOk();

        config([
            'services.semaphore.api_key' => 'semaphore-admin-key',
            'services.semaphore.sender_name' => 'RESQPERATION',
            'services.semaphore.base_url' => 'https://semaphore.test/api/v4',
            'services.sms_gateway.base_url' => 'https://sms-gateway.test',
            'services.sms_gateway.username' => 'test-user',
            'services.sms_gateway.password' => 'test-password',
            'services.onesignal.app_id' => 'test-app-id',
            'services.onesignal.api_key' => 'test-api-key',
            'services.onesignal.base_url' => 'https://onesignal.test',
        ]);
        DB::table('password_reset_requests')
            ->where('user_id', 'USR-SYSTEM-ADMIN-001')
            ->delete();
        $sentCode = null;
        Http::fake(function ($request) use (&$sentCode) {
            if (str_contains($request->url(), 'onesignal.test')) {
                return Http::response(['errors' => ['SMS is not enabled']], 400);
            }
            preg_match('/code is (\d{6})/', $request['textMessage']['text'], $matches);
            $sentCode = $matches[1] ?? null;
            return Http::response([[
                'message_id' => 1002,
                'recipient' => '09178889912',
                'status' => 'Queued',
            ]], 200);
        });

        $this->postJson('/api/v1/auth/password-recovery/household/send-code', [
            'login' => 'system.admin',
            'contact_number' => '09178889912',
        ])->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['phoneNumbers'] === ['+639178889912']);
        $this->assertNotNull($sentCode);
        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => 'USR-SYSTEM-ADMIN-001',
            'household_id' => 'USR-SYSTEM-ADMIN-001',
        ]);

        $this->postJson('/api/v1/auth/password-recovery/household/reset', [
            'login' => 'system.admin',
            'contact_number' => '09178889912',
            'code' => $sentCode,
            'password' => 'new-admin-password',
            'password_confirmation' => 'new-admin-password',
        ])->assertOk()
            ->assertJsonPath('account_identifier', 'system.admin')
            ->assertJsonPath('household_id', 'system.admin');

        $this->assertTrue(Hash::check(
            'new-admin-password',
            DB::table('users')->where('user_id', 'USR-SYSTEM-ADMIN-001')->value('password')
        ));
    }
}
