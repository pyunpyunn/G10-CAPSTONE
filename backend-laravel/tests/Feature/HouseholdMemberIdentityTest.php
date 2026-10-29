<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Shared\HouseholdMemberIdentity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdMemberIdentityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('roles', function (Blueprint $t): void {
            $t->increments('role_id'); $t->string('role_key'); $t->string('role_name');
        });
        Schema::create('households', function (Blueprint $t): void {
            $t->string('household_id')->primary(); $t->softDeletes();
        });
        Schema::create('household_members', function (Blueprint $t): void {
            $t->string('member_id')->primary(); $t->string('household_id');
            $t->string('first_name')->nullable(); $t->string('last_name')->nullable();
            $t->string('contact_number')->nullable(); $t->boolean('is_household_head')->default(false);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t): void {
            $t->string('user_id')->primary(); $t->integer('role_id');
            foreach (['member_id', 'household_id', 'username', 'first_name', 'last_name', 'name', 'email', 'password', 'temp_password', 'contact_number'] as $c) $t->string($c)->nullable();
            $t->boolean('is_active')->default(true); $t->boolean('must_change_password')->default(true);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t): void {
            $t->id(); $t->morphs('tokenable'); $t->string('name'); $t->string('token', 64)->unique();
            $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
        DB::table('roles')->insert(['role_id' => 6, 'role_key' => 'household_resident', 'role_name' => 'Household resident']);
        DB::table('households')->insert(['household_id' => 'HH-2026-8560']);
        DB::table('household_members')->insert([
            ['member_id' => 'HM-000001', 'household_id' => 'HH-2026-8560', 'first_name' => 'Owner', 'last_name' => 'One', 'is_household_head' => true],
            ['member_id' => 'HM-000002', 'household_id' => 'HH-2026-8560', 'first_name' => 'Other', 'last_name' => 'Two', 'is_household_head' => false],
        ]);
        DB::table('users')->insert([
            'user_id' => 'U1', 'role_id' => 6, 'household_id' => 'HH-2026-8560',
            'username' => 'old.household', 'password' => Hash::make('existing-password'),
        ]);
    }

    private function upgrade(): void
    {
        $migration = require database_path('migrations/2026_10_26_000001_use_household_scoped_member_ids.php');
        $migration->up();
    }

    public function test_upgrade_does_not_copy_plaintext_temporary_passwords(): void
    {
        DB::table('users')->where('user_id', 'U1')->update(['temp_password' => 'legacy-temporary-password']);
        $passwordHash = DB::table('users')->where('user_id', 'U1')->value('password');

        $this->upgrade();

        $created = DB::table('users')->where('user_id', '!=', 'U1')->first();
        $this->assertNotNull($created);
        $this->assertNull($created->temp_password);
        $this->assertSame($passwordHash, $created->password);
        $this->assertTrue(Hash::check('existing-password', $created->password));
    }

    public function test_plaintext_cleanup_preserves_hashes_and_leaves_partner_roles_and_invalid_hashes_for_review(): void
    {
        DB::table('roles')->insert(['role_id' => 99, 'role_key' => 'partner_role', 'role_name' => 'Partner']);
        $hash = DB::table('users')->where('user_id', 'U1')->value('password');
        DB::table('users')->where('user_id', 'U1')->update(['temp_password' => 'legacy']);
        DB::table('users')->insert([
            ['user_id' => 'partner', 'role_id' => 99, 'password' => $hash, 'temp_password' => 'partner-legacy'],
            ['user_id' => 'invalid', 'role_id' => 6, 'password' => 'not-a-hash', 'temp_password' => 'preserve-for-review'],
        ]);
        $result = app(\App\Services\Shared\LegacyResidentCredentialCleanup::class)->run(DB::connection());
        $this->assertSame(['cleared' => 1, 'unresolved' => 1], $result);
        $this->assertDatabaseHas('users', ['user_id' => 'U1', 'password' => $hash, 'temp_password' => null]);
        $this->assertDatabaseHas('users', ['user_id' => 'partner', 'temp_password' => 'partner-legacy']);
        $this->assertDatabaseHas('users', ['user_id' => 'invalid', 'temp_password' => 'preserve-for-review']);
    }

    public function test_upgrade_preserves_household_passwords_and_updates_relational_and_json_references(): void
    {
        Schema::create('member_disaster_statuses', function (Blueprint $t): void {
            $t->id(); $t->string('member_id'); $t->string('reported_by_member_id');
            $t->foreign('member_id')->references('member_id')->on('household_members');
            $t->text('notes');
        });
        DB::table('member_disaster_statuses')->insert(['member_id' => 'HM-000002', 'reported_by_member_id' => 'HM-000001', 'notes' => '{"member_id":"HM-000002"}']);
        $hash = DB::table('users')->value('password');
        $this->upgrade();
        $this->assertDatabaseHas('households', ['household_id' => 'HH-2026-8560']);
        $this->assertDatabaseHas('household_members', ['member_id' => 'HM-8560-1']);
        $this->assertDatabaseHas('users', ['user_id' => 'U1', 'member_id' => 'HM-8560-1', 'password' => $hash]);
        $this->assertDatabaseHas('users', ['member_id' => 'HM-8560-2', 'password' => $hash]);
        $this->assertDatabaseHas('member_disaster_statuses', ['member_id' => 'HM-8560-2', 'reported_by_member_id' => 'HM-8560-1', 'notes' => '{"member_id":"HM-8560-2"}']);
        $this->upgrade();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('household_member_id_mappings', 2);
    }

    public function test_sequence_is_per_household_and_supports_two_digit_increments(): void
    {
        for ($n = 3; $n <= 12; $n++) DB::table('household_members')->insert(['member_id' => sprintf('HM-%06d', $n), 'household_id' => 'HH-2026-8560']);
        $this->upgrade();
        $this->assertDatabaseHas('household_members', ['member_id' => 'HM-8560-12']);
        $this->assertSame('HM-8560-13', app(HouseholdMemberIdentity::class)->next('HH-2026-8560'));
        $this->assertSame('HM-8560-14', app(HouseholdMemberIdentity::class)->next('HH-2026-8560'));
        $this->assertSame('HM-8561-1', app(HouseholdMemberIdentity::class)->next('HH-2026-8561'));
    }

    public function test_household_login_uses_shared_credentials_and_requires_a_device_owner(): void
    {
        $this->upgrade();
        $access = require database_path('migrations/2026_10_26_000003_create_household_login_and_device_owners.php');
        $access->up();
        $this->postJson('/api/v1/auth/login', ['login' => 'HH-2026-8560', 'password' => 'existing-password', 'device_uuid' => 'phone-1'])
            ->assertOk()->assertJsonPath('owner_selection_required', true)->assertJsonCount(2, 'owner_options.members');
        foreach (['HM-8560-1', 'old.household', 'HM-000001'] as $login) {
            $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => 'existing-password'])->assertUnprocessable();
        }
    }

    public function test_member_can_save_only_own_contact_and_both_records_are_updated(): void
    {
        $this->upgrade();
        Sanctum::actingAs(User::findOrFail('U1'));
        $this->patchJson('/api/v1/household/members/HM-8560-1', ['contact_number' => '09171234567'])
            ->assertOk()->assertJsonPath('data.user.full_name', 'Owner One');
        $this->assertDatabaseHas('household_members', ['member_id' => 'HM-8560-1', 'contact_number' => '09171234567']);
        $this->assertDatabaseHas('users', ['user_id' => 'U1', 'contact_number' => '09171234567']);
        $this->patchJson('/api/v1/household/members/HM-8560-2', ['contact_number' => '09170000000'])->assertForbidden();
        $this->patchJson('/api/v1/household/members/HM-8560-1', ['contact_number' => '09170000000', 'first_name' => 'Changed'])->assertUnprocessable();
        $this->patchJson('/api/v1/household/members/HM-8560-1', ['contact_number' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseHas('household_members', ['member_id' => 'HM-8560-1', 'first_name' => 'Owner', 'contact_number' => '09171234567']);
    }

    public function test_member_from_another_household_cannot_be_used_as_account_owner(): void
    {
        $this->upgrade();
        DB::table('users')->where('user_id', 'U1')->update(['household_id' => 'HH-OTHER']);
        $this->postJson('/api/v1/auth/login', ['login' => 'HM-8560-1', 'password' => 'existing-password'])->assertUnprocessable();
    }

    public function test_device_registration_ignores_a_spoofed_member_id(): void
    {
        $this->upgrade();
        Schema::create('device_tokens', function (Blueprint $t): void {
            $t->id(); $t->string('household_id'); $t->string('user_id'); $t->string('member_id'); $t->string('device_uuid'); $t->timestamps();
        });
        Sanctum::actingAs(User::findOrFail('U1'));
        $this->postJson('/api/v1/household/device-location', [
            'device_uuid' => 'phone-1', 'member_id' => 'HM-8560-2',
        ])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['device_uuid' => 'phone-1', 'member_id' => 'HM-8560-1', 'user_id' => 'U1']);
    }

    public function test_household_geotag_skips_setup_for_a_member_without_a_registered_device(): void
    {
        $this->upgrade();
        Schema::create('geotagged_locations', function (Blueprint $t): void {
            $t->id('location_id'); $t->string('household_id'); $t->double('latitude'); $t->double('longitude');
        });
        DB::table('geotagged_locations')->insert(['household_id' => 'HH-2026-8560', 'latitude' => 10.287, 'longitude' => 123.88]);
        foreach (User::whereNotNull('member_id')->get() as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/household/overview')->assertOk()
                ->assertJsonPath('data.setup.is_setup_complete', true)
                ->assertJsonPath('data.setup.has_device', false);
        }
    }
    private function ownerAccess(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->upgrade();
        $migration = require database_path('migrations/2026_10_26_000003_create_household_login_and_device_owners.php');
        $migration->up();
        Schema::create('device_tokens', function (Blueprint $t): void {
            $t->id(); $t->string('household_id'); $t->string('user_id'); $t->string('member_id'); $t->string('device_uuid'); $t->timestamps();
        });
    }

    private function ownerRequest(string $token, string $method, string $path, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($token)->json($method, '/api/v1/'.$path, $data);
    }

    private function householdLogin(string $uuid, string $password = 'existing-password'): array
    {
        $this->app['auth']->forgetGuards();
        return $this->postJson('/api/v1/auth/login', ['login' => 'HH-2026-8560', 'password' => $password, 'device_uuid' => $uuid])->assertOk()->json();
    }

    public function test_pending_login_cannot_read_or_write_member_data_before_choosing_an_owner(): void
    {
        $this->ownerAccess();
        $login = $this->householdLogin('phone-a');
        $this->ownerRequest($login['token'], 'GET', 'household/overview')->assertForbidden()->assertJsonPath('code', 'DEVICE_OWNER_REQUIRED');
        $this->ownerRequest($login['token'], 'PATCH', 'household/members/HM-8560-1', ['contact_number' => '09171234567'])->assertForbidden();
        $this->ownerRequest($login['token'], 'POST', 'household/device-owner/select', ['member_id' => 'HM-OTHER-1'])->assertUnprocessable();
        $this->assertDatabaseCount('household_device_owners', 0);
        $this->ownerRequest($login['token'], 'GET', 'household/device-owner/options')->assertOk()->assertJsonCount(2, 'data.members');
    }

    public function test_same_household_credentials_save_different_owners_on_different_phones(): void
    {
        $this->ownerAccess();
        foreach (['phone-a' => 'HM-8560-1', 'phone-b' => 'HM-8560-2'] as $uuid => $memberId) {
            $login = $this->householdLogin($uuid);
            $selected = $this->ownerRequest($login['token'], 'POST', 'household/device-owner/select', ['member_id' => $memberId, 'device_uuid' => 'must-not-change-another-phone'])
                ->assertOk()->assertJsonPath('user.member_id', $memberId)->assertJsonPath('owner_selection_required', false)->json();
            $this->assertDatabaseHas('household_device_owners', ['device_uuid' => $uuid, 'member_id' => $memberId]);
            $this->assertDatabaseHas('device_tokens', ['device_uuid' => $uuid, 'member_id' => $memberId]);
            $again = $this->householdLogin($uuid);
            $this->assertFalse($again['owner_selection_required']);
            $this->assertSame($memberId, $again['user']['member_id']);
        }
        $this->assertDatabaseCount('household_device_owners', 2);
        $this->assertTrue($this->householdLogin('new-phone')['owner_selection_required']);
    }

    public function test_reset_owner_only_changes_the_current_phone_and_keeps_a_lost_response_retryable(): void
    {
        $this->ownerAccess();
        $tokens = [];
        foreach (['phone-a' => 'HM-8560-1', 'phone-b' => 'HM-8560-2'] as $uuid => $memberId) {
            $login = $this->householdLogin($uuid);
            $tokens[$uuid] = $this->ownerRequest($login['token'], 'POST', 'household/device-owner/select', ['member_id' => $memberId])->assertOk()->json('token');
        }
        $reset = $this->ownerRequest($tokens['phone-a'], 'POST', 'household/device-owner/reset', ['device_uuid' => 'phone-b'])->assertOk()->assertJsonPath('owner_selection_required', true)->json();
        $this->assertDatabaseMissing('household_device_owners', ['device_uuid' => 'phone-a']);
        $this->assertDatabaseHas('household_device_owners', ['device_uuid' => 'phone-b', 'member_id' => 'HM-8560-2']);
        $this->ownerRequest($tokens['phone-b'], 'GET', 'auth/me')->assertOk();
        // Losing the reset response must not strand this phone with an invalid old session.
        $reset = $this->ownerRequest($tokens['phone-a'], 'POST', 'household/device-owner/reset')->assertOk()->json();
        $replacement = $this->ownerRequest($reset['token'], 'POST', 'household/device-owner/select', ['member_id' => 'HM-8560-2'])->assertOk()->json('token');
        $this->ownerRequest($tokens['phone-a'], 'GET', 'auth/me')->assertUnauthorized();
        $this->ownerRequest($replacement, 'GET', 'auth/me')->assertOk()->assertJsonPath('data.member_id', 'HM-8560-2');
    }

    public function test_owner_selection_can_be_retried_after_a_lost_response_but_not_changed_without_reset(): void
    {
        $this->ownerAccess();
        $pending = $this->householdLogin('phone-a')['token'];
        $first = $this->ownerRequest($pending, 'POST', 'household/device-owner/select', ['member_id' => 'HM-8560-2'])->assertOk()->json('token');
        $second = $this->ownerRequest($pending, 'POST', 'household/device-owner/select', ['member_id' => 'HM-8560-2'])->assertOk()->json('token');
        $this->ownerRequest($pending, 'POST', 'household/device-owner/select', ['member_id' => 'HM-8560-1'])->assertStatus(409);
        $this->ownerRequest($first, 'GET', 'auth/me')->assertUnauthorized();
        $this->ownerRequest($second, 'GET', 'auth/me')->assertOk()->assertJsonPath('data.member_id', 'HM-8560-2');
        $this->ownerRequest($pending, 'GET', 'household/device-owner/options')->assertUnauthorized();
        $this->assertDatabaseCount('household_device_owners', 1);
    }

    public function test_owner_session_has_no_idle_expiration_and_contact_updates_are_member_scoped(): void
    {
        $this->ownerAccess();
        $pending = $this->householdLogin('phone-a')['token'];
        $token = $this->ownerRequest($pending, 'POST', 'household/device-owner/select', ['member_id' => 'HM-8560-2'])->assertOk()->json('token');
        $this->assertNull(config('sanctum.expiration'));
        $this->assertNull(DB::table('personal_access_tokens')->where('name', 'household-device:phone-a')->value('expires_at'));
        $this->travel(60)->days();
        $this->ownerRequest($token, 'GET', 'auth/me')->assertOk();
        $this->ownerRequest($token, 'PATCH', 'household/members/HM-8560-2', ['contact_number' => '09171234567'])->assertOk();
        $this->ownerRequest($token, 'PATCH', 'household/members/HM-8560-1', ['contact_number' => '09170000000'])->assertForbidden();
        $this->assertDatabaseHas('household_members', ['member_id' => 'HM-8560-2', 'contact_number' => '09171234567']);
        $this->assertDatabaseHas('household_members', ['member_id' => 'HM-8560-1', 'contact_number' => null]);
    }

    public function test_password_change_from_any_selected_member_changes_the_shared_household_password(): void
    {
        $this->ownerAccess();
        $pending = $this->householdLogin('phone-b')['token'];
        $token = $this->ownerRequest($pending, 'POST', 'household/device-owner/select', ['member_id' => 'HM-8560-2'])->assertOk()->json('token');
        $this->ownerRequest($token, 'PATCH', 'profile/password', ['current_password' => 'existing-password', 'password' => 'new-shared-password', 'password_confirmation' => 'new-shared-password'])->assertOk();
        $this->assertTrue(Hash::check('new-shared-password', User::findOrFail('U1')->password));
        $this->assertFalse($this->householdLogin('phone-b', 'new-shared-password')['owner_selection_required']);
    }

}
