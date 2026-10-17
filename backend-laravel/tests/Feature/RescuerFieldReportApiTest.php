<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RescuerFieldReportApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $migration = require base_path('database/migrations/2026_10_17_000001_add_assignment_field_report_records.php');
        $migration->up();
        $this->seedData();
    }

    public function test_assignment_cannot_be_completed_until_its_field_report_is_saved(): void
    {
        $this->actingAs(User::query()->findOrFail('rescuer-1'), 'sanctum');

        $this->getJson('/api/v1/rescuer/assignments/101/household')
            ->assertOk()
            ->assertJsonPath('data.household.household_id', 'household-1')
            ->assertJsonPath('data.household.household_head_name', 'Ana Cruz')
            ->assertJsonPath('data.members.0.name', 'Ana Cruz');

        $this->patchJson('/api/v1/rescuer/assignments/101/status', ['status' => 'completed'])
            ->assertConflict()
            ->assertJsonPath('message', 'Submit the household field report before completing this assignment.');

        $response = $this->postJson('/api/v1/rescuer/field-reports', $this->validReportPayload());

        $response->assertCreated()
            ->assertJsonPath('data.assignment_id', 101);

        $this->assertDatabaseHas('responder_field_reports', [
            'assignment_id' => 101,
            'responder_id' => 11,
            'household_id' => 'household-1',
            'incident_type' => 'flood',
            'property_damage' => 'minor_damage',
            'death_count' => 0,
            'injured_count' => 1,
        ]);
        $this->assertDatabaseHas('responder_field_report_members', [
            'report_id' => $response->json('data.report_id'),
            'member_id' => 'member-1',
            'status_key' => 'injured',
        ]);
        $this->assertDatabaseHas('responder_field_report_members', [
            'report_id' => $response->json('data.report_id'),
            'member_id' => 'member-2',
            'status_key' => 'safe',
        ]);
        $this->assertDatabaseHas('member_disaster_statuses', [
            'disaster_id' => 'event-1',
            'member_id' => 'member-1',
            'status_id' => 3,
        ]);

        $this->patchJson('/api/v1/rescuer/assignments/101/status', ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.assignment.status_key', 'completed');
    }

    public function test_repeating_a_saved_request_is_idempotent_and_a_second_report_is_rejected(): void
    {
        $this->actingAs(User::query()->findOrFail('rescuer-1'), 'sanctum');
        $payload = $this->validReportPayload();

        $first = $this->postJson('/api/v1/rescuer/field-reports', $payload)->assertCreated();
        $retry = $this->postJson('/api/v1/rescuer/field-reports', $payload)->assertOk();

        $this->assertSame($first->json('data.report_id'), $retry->json('data.report_id'));
        $this->assertDatabaseCount('responder_field_reports', 1);
        $this->assertDatabaseCount('responder_field_report_members', 2);
        $this->assertDatabaseCount('household_status_logs', 1);

        $payload['idempotency_key'] = 'a-different-retry-key';
        $this->postJson('/api/v1/rescuer/field-reports', $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'A field report has already been saved for this assignment.');
        $this->assertDatabaseCount('responder_field_reports', 1);
    }

    public function test_invalid_counts_and_missing_required_fields_do_not_save_a_report(): void
    {
        $this->actingAs(User::query()->findOrFail('rescuer-1'), 'sanctum');
        $payload = $this->validReportPayload();
        $payload['death_count'] = -1;

        $this->postJson('/api/v1/rescuer/field-reports', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('death_count');

        $payload = $this->validReportPayload();
        unset($payload['incident_type']);
        $payload['injured_count'] = -1;
        $this->postJson('/api/v1/rescuer/field-reports', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['incident_type', 'injured_count']);

        $this->assertDatabaseCount('responder_field_reports', 0);
        $this->assertDatabaseCount('household_status_logs', 0);
    }

    public function test_rescuers_cannot_read_or_submit_another_rescuers_assignment(): void
    {
        $this->actingAs(User::query()->findOrFail('rescuer-2'), 'sanctum');
        $payload = $this->validReportPayload();

        $this->getJson('/api/v1/rescuer/assignments/101/household')->assertNotFound();
        $this->postJson('/api/v1/rescuer/field-reports', $payload)->assertNotFound();
        $this->patchJson('/api/v1/rescuer/assignments/101/status', ['status' => 'completed'])->assertNotFound();

        $this->assertDatabaseCount('responder_field_reports', 0);
    }

    public function test_non_rescuer_accounts_cannot_submit_field_reports(): void
    {
        $this->actingAs(User::query()->findOrFail('resident-1'), 'sanctum');

        $this->postJson('/api/v1/rescuer/field-reports', $this->validReportPayload())->assertForbidden();
        $this->assertDatabaseCount('responder_field_reports', 0);
    }

    private function validReportPayload(): array
    {
        return [
            'assignment_id' => 101,
            'household_id' => 'household-1',
            'idempotency_key' => 'submission-101',
            'incident_type' => 'flood',
            'property_damage' => 'minor_damage',
            'death_count' => 0,
            'injured_count' => 1,
            'notes' => 'Water entered the ground floor.',
            'members' => [
                ['member_id' => 'member-1', 'status_key' => 'injured'],
                ['member_id' => 'member-2', 'status_key' => 'safe'],
            ],
        ];
    }

    private function createSchema(): void
    {
        foreach ([
            'responder_field_report_members',
            'responder_field_reports',
            'household_status_logs',
            'household_disasters',
            'member_disaster_statuses',
            'member_statuses',
            'household_statuses',
            'household_members',
            'households',
            'responder_assignments',
            'responders',
            'disaster_events',
            'roles',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('role_key');
            $table->string('role_name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->unsignedInteger('role_id');
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('responders', function (Blueprint $table): void {
            $table->unsignedBigInteger('responder_id')->primary();
            $table->string('user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('full_name')->nullable();
            $table->timestamps();
        });

        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });

        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('assignment_id')->primary();
            $table->unsignedBigInteger('responder_id');
            $table->string('disaster_id');
            $table->string('household_id')->nullable();
            $table->string('assignment_code')->nullable();
            $table->string('assigned_area')->nullable();
            $table->string('priority_level')->nullable();
            $table->string('dispatch_notes')->nullable();
            $table->string('status');
            $table->json('route_notes')->nullable();
            $table->json('outcome_notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_name')->nullable();
            $table->string('household_code')->nullable();
            $table->string('household_number')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('is_household_head')->default(false);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->unsignedBigInteger('status_id')->primary();
            $table->string('status_key');
            $table->string('status_label');
        });

        Schema::create('member_statuses', function (Blueprint $table): void {
            $table->unsignedBigInteger('status_id')->primary();
            $table->string('status_key');
            $table->string('status_label');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('member_disaster_statuses', function (Blueprint $table): void {
            $table->bigIncrements('member_status_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->string('member_id');
            $table->unsignedBigInteger('status_id');
            $table->string('report_source')->nullable();
            $table->string('reported_by_user_id')->nullable();
            $table->unsignedBigInteger('responder_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['disaster_id', 'member_id']);
        });

        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->bigIncrements('status_log_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->unsignedBigInteger('status_id');
            $table->string('source');
            $table->string('submitted_by_user_id')->nullable();
            $table->unsignedBigInteger('responder_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('responder_field_reports', function (Blueprint $table): void {
            $table->string('report_id')->primary();
            $table->unsignedBigInteger('responder_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->bigIncrements('household_disaster_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->unsignedBigInteger('current_status_id')->nullable();
            $table->string('last_status_source')->nullable();
            $table->text('last_status_notes')->nullable();
            $table->string('last_reported_by_user_id')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->integer('last_battery_level')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->string('priority_level')->nullable();
            $table->boolean('needs_dispatch')->default(false);
            $table->unsignedBigInteger('last_responder_id')->nullable();
            $table->timestamps();
            $table->unique(['disaster_id', 'household_id']);
        });
    }

    private function seedData(): void
    {
        DB::table('roles')->insert([
            ['role_id' => 1, 'role_key' => 'rescuer', 'role_name' => 'Rescuer'],
            ['role_id' => 2, 'role_key' => 'household_resident', 'role_name' => 'Household Resident'],
        ]);
        DB::table('users')->insert([
            ['user_id' => 'rescuer-1', 'role_id' => 1, 'name' => 'Rescuer One', 'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 'rescuer-2', 'role_id' => 1, 'name' => 'Rescuer Two', 'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 'resident-1', 'role_id' => 2, 'name' => 'Resident', 'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('responders')->insert([
            ['responder_id' => 11, 'user_id' => 'rescuer-1', 'created_at' => now(), 'updated_at' => now()],
            ['responder_id' => 12, 'user_id' => 'rescuer-2', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('disaster_events')->insert([
            'event_id' => 'event-1',
            'name' => 'Test flood',
            'started_at' => now(),
            'ended_at' => null,
        ]);
        DB::table('responder_assignments')->insert([
            [
                'assignment_id' => 101,
                'responder_id' => 11,
                'disaster_id' => 'event-1',
                'household_id' => 'household-1',
                'status' => 'on_scene',
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assignment_id' => 102,
                'responder_id' => 12,
                'disaster_id' => 'event-1',
                'household_id' => 'household-1',
                'status' => 'on_scene',
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('households')->insert(['household_id' => 'household-1']);
        DB::table('household_members')->insert([
            ['member_id' => 'member-1', 'household_id' => 'household-1', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'is_household_head' => true],
            ['member_id' => 'member-2', 'household_id' => 'household-1', 'first_name' => 'Ben', 'last_name' => 'Cruz', 'is_household_head' => false],
        ]);
        DB::table('household_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'status_label' => 'Safe'],
            ['status_id' => 2, 'status_key' => 'needs_help', 'status_label' => 'Needs help'],
        ]);
        DB::table('member_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'unknown', 'status_label' => 'Unknown / No Report', 'is_active' => true],
            ['status_id' => 2, 'status_key' => 'safe', 'status_label' => 'Safe', 'is_active' => true],
            ['status_id' => 3, 'status_key' => 'injured', 'status_label' => 'Injured', 'is_active' => true],
        ]);
    }
}
