<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDeviceRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('role_key');
            $table->string('role_name');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->unsignedInteger('role_id');
            $table->string('household_id')->nullable();
            $table->string('member_id')->nullable();
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
        });
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('device_uuid', 150);
            $table->string('household_id', 255)->nullable();
            $table->string('user_id', 255)->nullable();
            $table->string('member_id')->nullable();
            $table->string('device_name', 100)->nullable();
            $table->string('platform', 30)->nullable();
            $table->string('app_role', 30)->nullable();
            $table->string('push_provider', 50)->nullable();
            $table->string('player_id', 255)->nullable();
            $table->string('push_token', 255)->nullable();
            $table->string('one_signal_user_id', 255)->nullable();
            $table->string('notification_permission_status', 30)->nullable();
            $table->unsignedInteger('battery_level')->nullable();
            $table->unsignedInteger('signal_strength')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('logged_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'role_id' => 6,
            'role_key' => 'household_resident',
            'role_name' => 'Household resident',
        ]);
        DB::table('users')->insert([
            'user_id' => 'household-user-1',
            'role_id' => 6,
            'household_id' => 'HH-100',
            'member_id' => 'HM-100-1',
        ]);
        DB::table('household_members')->insert([
            'member_id' => 'HM-100-1',
            'household_id' => 'HH-100',
        ]);
    }

    public function test_each_registered_household_device_keeps_its_household_and_onesignal_subscription(): void
    {
        Sanctum::actingAs(User::findOrFail('household-user-1'));

        $this->postJson('/api/v1/mobile/device-token', $this->registration('phone-1', 'subscription-1'))
            ->assertOk()
            ->assertJsonPath('data.push_token_saved', true);
        $this->postJson('/api/v1/mobile/device-token', $this->registration('phone-2', 'subscription-2'))
            ->assertOk()
            ->assertJsonPath('data.push_token_saved', true);

        $this->assertDatabaseCount('device_tokens', 2);
        $this->assertDatabaseHas('device_tokens', [
            'device_uuid' => 'phone-1',
            'household_id' => 'HH-100',
            'user_id' => 'household-user-1',
            'player_id' => 'subscription-1',
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'device_uuid' => 'phone-2',
            'household_id' => 'HH-100',
            'user_id' => 'household-user-1',
            'player_id' => 'subscription-2',
        ]);

        $this->postJson('/api/v1/mobile/device-token', $this->registration('phone-1', 'subscription-1-rotated'))
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 2);
        $this->assertDatabaseHas('device_tokens', [
            'device_uuid' => 'phone-1',
            'household_id' => 'HH-100',
            'user_id' => 'household-user-1',
            'player_id' => 'subscription-1-rotated',
        ]);
    }

    public function test_household_device_registration_requires_an_account_household_link(): void
    {
        DB::table('users')->where('user_id', 'household-user-1')->update(['household_id' => null]);
        Sanctum::actingAs(User::findOrFail('household-user-1'));

        $this->postJson('/api/v1/mobile/device-token', $this->registration('phone-1', 'subscription-1'))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is not linked to a household record.');

        $this->assertDatabaseCount('device_tokens', 0);
    }

    private function registration(string $deviceUuid, string $playerId): array
    {
        return [
            'device_uuid' => $deviceUuid,
            'device_name' => 'Household mobile',
            'platform' => 'android',
            'player_id' => $playerId,
            'push_provider' => 'onesignal',
            'notification_permission_status' => 'granted',
        ];
    }
}
