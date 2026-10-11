<?php

namespace Tests\Feature;

use App\Actions\UpdateHouseholdStatus;
use App\Jobs\SendDispatchChangePush;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateHouseholdStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->integer('household_disaster_id')->primary();
            $table->string('disaster_id');
            $table->string('household_id');
            $table->integer('current_status_id');
            $table->boolean('needs_dispatch');
            $table->string('priority_level');
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->string('assignment_code');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->integer('responder_id');
            $table->integer('team_id');
            $table->string('status');
            $table->text('route_notes')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->integer('responder_id')->primary();
            $table->boolean('is_deployed');
            $table->string('duty_status');
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary();
            $table->string('duty_status');
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('household_disasters')->insert([
            'household_disaster_id' => 1, 'disaster_id' => 'EVT-1', 'household_id' => 'HH-1',
            'current_status_id' => 4, 'needs_dispatch' => 1, 'priority_level' => 'urgent',
        ]);
        DB::table('responder_assignments')->insert([
            'assignment_id' => 8, 'assignment_code' => 'DSP-8', 'disaster_id' => 'EVT-1',
            'household_id' => 'HH-1', 'responder_id' => 10, 'team_id' => 3,
            'status' => 'en_route', 'route_notes' => json_encode(['selected_responder_ids' => [10, 11]]),
        ]);
        DB::table('responders')->insert([
            ['responder_id' => 10, 'is_deployed' => 1, 'duty_status' => 'deployed'],
            ['responder_id' => 11, 'is_deployed' => 1, 'duty_status' => 'deployed'],
        ]);
        DB::table('rescue_teams')->insert(['team_id' => 3, 'duty_status' => 'en_route']);
    }

    public function test_safe_report_withdraws_assignment_and_releases_selected_rescuers(): void
    {
        Queue::fake();

        app(UpdateHouseholdStatus::class)->apply('EVT-1', 'HH-1', 1, 'safe', 'household_mobile', null, null, null);

        $this->assertDatabaseHas('household_disasters', ['household_id' => 'HH-1', 'current_status_id' => 1, 'needs_dispatch' => 0, 'priority_level' => 'monitor']);
        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 8, 'status' => 'cancelled']);
        $this->assertDatabaseHas('responders', ['responder_id' => 10, 'duty_status' => 'available', 'is_deployed' => 0]);
        $this->assertDatabaseHas('responders', ['responder_id' => 11, 'duty_status' => 'available', 'is_deployed' => 0]);
        $this->assertDatabaseHas('rescue_teams', ['team_id' => 3, 'duty_status' => 'available']);
        Queue::assertPushed(SendDispatchChangePush::class, 1);
    }

    public function test_unsafe_report_keeps_live_assignment_and_urgent_priority(): void
    {
        Queue::fake();

        app(UpdateHouseholdStatus::class)->apply('EVT-1', 'HH-1', 4, 'unsafe', 'household_mobile', null, null, null);

        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 8, 'status' => 'en_route']);
        $this->assertDatabaseHas('household_disasters', ['household_id' => 'HH-1', 'needs_dispatch' => 1, 'priority_level' => 'urgent']);
        Queue::assertNotPushed(SendDispatchChangePush::class);
    }
}
