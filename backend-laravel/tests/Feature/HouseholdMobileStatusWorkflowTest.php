<?php

namespace Tests\Feature;

use App\Services\Mobile\HouseholdMobileStatusWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdMobileStatusWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
            $table->string('status_label')->nullable();
        });
        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->increments('status_log_id');
            $table->string('disaster_id')->nullable();
            $table->string('household_id');
            $table->integer('status_id');
            $table->string('source')->nullable();
            $table->string('submitted_by_user_id')->nullable();
            $table->integer('device_token_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable();
            $table->decimal('location_accuracy_m', 8, 2)->nullable();
            $table->integer('battery_level')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->increments('household_disaster_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('initial_status_id')->nullable();
            $table->integer('current_status_id')->nullable();
            $table->string('last_status_source')->nullable();
            $table->text('last_status_notes')->nullable();
            $table->string('last_reported_by_user_id')->nullable();
            $table->integer('last_device_token_id')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->integer('last_battery_level')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->string('priority_level')->nullable();
            $table->boolean('needs_dispatch')->nullable();
            $table->timestamps();
        });
        DB::table('disaster_events')->insert([
            'event_id' => 'EVT-1', 'name' => 'Flood response', 'started_at' => now(), 'ended_at' => null,
        ]);
        DB::table('household_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'status_label' => 'Safe'],
            ['status_id' => 2, 'status_key' => 'needs_help', 'status_label' => 'Needs help'],
        ]);
    }

    public function test_store_status_preserves_contract_and_updates_the_active_household_status(): void
    {
        $request = $this->householdRequest([
            'status_key' => 'needs_help', 'notes' => 'Need water', 'latitude' => 10.2, 'longitude' => 123.8,
        ]);
        $response = app(HouseholdMobileStatusWorkflow::class)->storeStatus($request);

        $this->assertSame(201, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('Household status update saved.', $payload['message']);
        $this->assertSame(['status_log_id' => 1], $payload['data']);
        $this->assertDatabaseHas('household_status_logs', [
            'status_log_id' => 1, 'household_id' => 'HH-1', 'disaster_id' => 'EVT-1',
            'status_id' => 2, 'source' => 'household_mobile',
        ]);
        $this->assertDatabaseHas('household_disasters', [
            'household_id' => 'HH-1', 'disaster_id' => 'EVT-1', 'current_status_id' => 2,
            'priority_level' => 'urgent', 'needs_dispatch' => 1,
        ]);
    }

    public function test_member_status_rejects_missing_member_before_persisting(): void
    {
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('name');
        });

        $request = $this->householdRequest(['status_key' => 'safe']);
        $response = app(HouseholdMobileStatusWorkflow::class)->storeMemberStatus($request, 'MISSING');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['message' => 'Household member was not found.'], $response->getData(true));
        $this->assertDatabaseCount('household_status_logs', 0);
    }

    private function householdRequest(array $input): Request
    {
        $request = Request::create('/api/v1/household/status', 'POST', $input);
        $request->setUserResolver(fn () => (object) ['household_id' => 'HH-1', 'user_id' => 'USR-1']);
        return $request;
    }
}



