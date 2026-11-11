<?php

namespace Tests\Feature;

use App\Services\HouseholdMobileService;
use App\Models\User;
use App\Services\Mobile\HouseholdTrustedHouseholdWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdTrustedHouseholdWorkflowTest extends TestCase
{
    public function test_household_can_configure_and_verify_pin_and_view_incoming_requests(): void
    {
        $this->createHouseholdTables();
        DB::table('households')->insert([
            ['household_id' => 'HH-1', 'household_name' => 'Cruz Household'],
            ['household_id' => 'HH-2', 'household_name' => 'Santos Household'],
        ]);
        DB::table('trusted_households')->insert([
            'connection_id' => 'TH-1',
            'requesting_household_id' => 'HH-1',
            'trusted_household_id' => 'HH-2',
            'reason' => 'Family support',
            'validation_status' => 'pending',
            'member_relationships' => json_encode([]),
            'created_by_user_id' => 'USR-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workflow = app(HouseholdTrustedHouseholdWorkflow::class);
        $overview = $workflow->trustedHouseholds($this->householdRequest('/household/trusted-households', 'GET', [], 'HH-2'));

        $this->assertFalse($overview->getData(true)['data']['pin_configured']);
        $this->assertSame('TH-1', $overview->getData(true)['data']['incoming_requests'][0]['connection_id']);

        $saveResponse = $workflow->saveTrustedPin($this->householdRequest('/household/trusted-pin', 'PUT', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ], 'HH-2'));

        $this->assertSame(200, $saveResponse->getStatusCode());
        $storedHash = DB::table('household_trusted_pins')->where('household_id', 'HH-2')->value('pin_hash');
        $this->assertTrue(Hash::check('1234', $storedHash));

        $verifyResponse = $workflow->verifyTrustedPin($this->householdRequest('/household/trusted-pin/verify', 'POST', [
            'pin' => '1234',
        ], 'HH-2'));

        $this->assertSame(200, $verifyResponse->getStatusCode());
    }

    public function test_household_can_recover_pin_with_account_password(): void
    {
        $this->createHouseholdTables();
        DB::table('household_trusted_pins')->insert([
            'household_id' => 'HH-2',
            'pin_hash' => Hash::make('1234'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = new User([
            'user_id' => 'USR-HH-2',
            'household_id' => 'HH-2',
            'role' => 'admin',
            'password' => Hash::make('account-password'),
        ]);
        $service = app(HouseholdMobileService::class);

        $denied = $service->saveTrustedPin($this->householdRequest('/household/trusted-pin', 'PUT', [
            'pin' => '5678',
            'pin_confirmation' => '5678',
            'current_password' => 'incorrect-password',
        ], 'HH-2', $user));

        $this->assertSame(422, $denied->getStatusCode());
        $this->assertTrue(Hash::check('1234', DB::table('household_trusted_pins')->where('household_id', 'HH-2')->value('pin_hash')));

        $recovered = $service->saveTrustedPin($this->householdRequest('/household/trusted-pin', 'PUT', [
            'pin' => '5678',
            'pin_confirmation' => '5678',
            'current_password' => 'account-password',
        ], 'HH-2', $user));

        $this->assertSame(200, $recovered->getStatusCode());
        $this->assertTrue(Hash::check('5678', DB::table('household_trusted_pins')->where('household_id', 'HH-2')->value('pin_hash')));
    }

    public function test_only_the_target_household_can_accept_an_incoming_request(): void
    {
        $this->createHouseholdTables();
        DB::table('trusted_households')->insert([
            'connection_id' => 'TH-2',
            'requesting_household_id' => 'HH-1',
            'trusted_household_id' => 'HH-2',
            'validation_status' => 'pending',
            'created_by_user_id' => 'USR-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workflow = app(HouseholdTrustedHouseholdWorkflow::class);
        $denied = $workflow->respondToTrustedHousehold($this->householdRequest('/household/trusted-households/TH-2', 'PATCH', [
            'decision' => 'accept',
        ], 'HH-3'), 'TH-2');

        $this->assertSame(404, $denied->getStatusCode());
        $this->assertDatabaseHas('trusted_households', ['connection_id' => 'TH-2', 'validation_status' => 'pending']);

        $accepted = $workflow->respondToTrustedHousehold($this->householdRequest('/household/trusted-households/TH-2', 'PATCH', [
            'decision' => 'accept',
        ], 'HH-2'), 'TH-2');

        $this->assertSame(200, $accepted->getStatusCode());
        $this->assertDatabaseHas('trusted_households', [
            'connection_id' => 'TH-2',
            'trusted_household_id' => 'HH-2',
            'validation_status' => 'validated',
            'validated_by_user_id' => 'USR-HH-2',
        ]);
    }

    public function test_trusted_household_pin_verification_uses_the_selected_households_pin(): void
    {
        $this->createHouseholdTables();
        DB::table('trusted_households')->insert([
            'connection_id' => 'TH-3',
            'requesting_household_id' => 'HH-1',
            'trusted_household_id' => 'HH-2',
            'validation_status' => 'validated',
            'created_by_user_id' => 'USR-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('household_trusted_pins')->insert([
            ['household_id' => 'HH-1', 'pin_hash' => Hash::make('1111'), 'created_at' => now(), 'updated_at' => now()],
            ['household_id' => 'HH-2', 'pin_hash' => Hash::make('2222'), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(HouseholdMobileService::class);
        $targetPinResponse = $service->verifyTrustedHouseholdPin($this->householdRequest(
            '/household/trusted-households/TH-3/pin/verify',
            'POST',
            ['pin' => '2222'],
            'HH-1'
        ), 'TH-3');
        $ownPinResponse = $service->verifyTrustedHouseholdPin($this->householdRequest(
            '/household/trusted-households/TH-3/pin/verify',
            'POST',
            ['pin' => '1111'],
            'HH-1'
        ), 'TH-3');
        $unrelatedHouseholdResponse = $service->verifyTrustedHouseholdPin($this->householdRequest(
            '/household/trusted-households/TH-3/pin/verify',
            'POST',
            ['pin' => '2222'],
            'HH-3'
        ), 'TH-3');
        DB::table('household_trusted_pins')->where('household_id', 'HH-2')->delete();
        $missingPinResponse = $service->verifyTrustedHouseholdPin($this->householdRequest(
            '/household/trusted-households/TH-3/pin/verify',
            'POST',
            ['pin' => '2222'],
            'HH-1'
        ), 'TH-3');

        $this->assertSame(200, $targetPinResponse->getStatusCode());
        $this->assertSame(422, $ownPinResponse->getStatusCode());
        $this->assertSame(404, $unrelatedHouseholdResponse->getStatusCode());
        $this->assertSame(409, $missingPinResponse->getStatusCode());
        $this->assertSame('The selected household has not set a trusted PIN yet.', $missingPinResponse->getData(true)['message']);
    }

    private function createHouseholdTables(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_name')->nullable();
            $table->string('household_code')->nullable();
        });

        Schema::create('trusted_households', function (Blueprint $table): void {
            $table->string('connection_id')->primary();
            $table->string('requesting_household_id');
            $table->string('trusted_household_id');
            $table->string('reason')->nullable();
            $table->string('validation_status')->nullable();
            $table->text('member_relationships')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->string('validated_by_user_id')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('household_trusted_pins', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('pin_hash');
            $table->string('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    private function householdRequest(string $uri, string $method, array $input, string $householdId, ?User $user = null): Request
    {
        $request = Request::create($uri, $method, $input);
        $request->setUserResolver(fn () => $user ?? (object) [
            'household_id' => $householdId,
            'user_id' => 'USR-'.$householdId,
        ]);

        return $request;
    }
}