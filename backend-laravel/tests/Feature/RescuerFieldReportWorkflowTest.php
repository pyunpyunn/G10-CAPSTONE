<?php

namespace Tests\Feature;

use App\Services\Mobile\RescuerFieldReportWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RescuerFieldReportWorkflowTest extends TestCase
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
            $table->integer('status_log_id')->primary();
            $table->string('disaster_id');
            $table->string('household_id');
            $table->integer('status_id');
            $table->string('source')->nullable();
            $table->string('submitted_by_user_id')->nullable();
            $table->integer('responder_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('battery_level')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->increments('household_disaster_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('initial_status_id');
            $table->integer('current_status_id')->nullable();
        });
        DB::table('disaster_events')->insert([
            'event_id' => 'EVT-1', 'name' => 'Flood response', 'started_at' => now(), 'ended_at' => null,
        ]);
        DB::table('household_statuses')->insert([
            'status_id' => 2, 'status_key' => 'needs_help', 'status_label' => 'Needs help',
        ]);
    }

    public function test_store_preserves_response_and_saves_field_report_status(): void
    {
        $request = Request::create('/api/v1/rescuer/field-reports', 'POST', [
            'household_id' => 'HH-4',
            'household_head' => 'Alex Rivera',
            'address' => 'Zone 4',
            'status_key' => 'needs_help',
            'latitude' => 10.2,
            'longitude' => 123.8,
            'battery_level' => 40,
            'notes' => 'Needs drinking water',
            'injured_count' => 2,
            'members' => [['name' => 'Sam Rivera', 'condition' => 'Injured']],
        ]);

        $response = app(RescuerFieldReportWorkflow::class)->store($request);
        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Field report submitted to HQ.', $payload['message']);
        $this->assertSame(['status_log_id' => 1], $payload['data']);
        $this->assertDatabaseHas('household_status_logs', [
            'status_log_id' => 1,
            'disaster_id' => 'EVT-1',
            'household_id' => 'HH-4',
            'status_id' => 2,
            'source' => 'responder_field_report',
            'notes' => "Household head: Alex Rivera\nAddress: Zone 4\nNotes: Needs drinking water\nInjuries: 2\nMembers: [{\"name\":\"Sam Rivera\",\"condition\":\"Injured\"}]",
        ]);
    }

    public function test_invalid_member_pair_is_rejected_before_any_write(): void
    {
        $request = Request::create('/api/v1/rescuer/field-reports', 'POST', [
            'household_id' => 'HH-4',
            'household_head' => 'Alex Rivera',
            'address' => 'Zone 4',
            'status_key' => 'needs_help',
            'notes' => 'Needs drinking water',
            'members' => [['name' => 'Sam Rivera', 'condition' => '']],
        ]);

        try {
            app(RescuerFieldReportWorkflow::class)->store($request);
            $this->fail('Expected validation failure for incomplete member details.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('members.1', $exception->errors());
        }

        $this->assertDatabaseCount('household_status_logs', 0);
    }

    public function test_failure_while_saving_latest_household_status_rolls_back_the_report_log(): void
    {
        Schema::table('household_disasters', function (Blueprint $table): void {
            $table->string('required_guard');
        });
        $request = Request::create('/api/v1/rescuer/field-reports', 'POST', [
            'household_id' => 'HH-4',
            'household_head' => 'Alex Rivera',
            'address' => 'Zone 4',
            'status_key' => 'needs_help',
            'notes' => 'Needs drinking water',
        ]);

        try {
            app(RescuerFieldReportWorkflow::class)->store($request);
            $this->fail('Expected the household status write to fail.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('household_status_logs', 0);
        }
    }
}



