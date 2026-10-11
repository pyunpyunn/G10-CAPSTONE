<?php

namespace Tests\Feature;

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

    private function householdRequest(string $uri, string $method, array $input, string $householdId): Request
    {
        $request = Request::create($uri, $method, $input);
        $request->setUserResolver(fn () => (object) [
            'household_id' => $householdId,
            'user_id' => 'USR-'.$householdId,
        ]);

        return $request;
    }
}