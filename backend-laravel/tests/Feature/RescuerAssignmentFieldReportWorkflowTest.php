<?php

namespace Tests\Feature;

use App\Services\Mobile\RescuerFieldReportWorkflow;
use App\Queries\RescuerAssignmentQuery;
use App\Services\Mobile\RescuerAssignmentWorkflow;
use App\Services\Mobile\RescuerMobileSupport;
use App\Services\Shared\RoutingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class RescuerAssignmentFieldReportWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        DB::table('disaster_events')->insert(['event_id' => 'EVT-1', 'name' => 'Flood response', 'started_at' => now()]);
        DB::table('household_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'status_label' => 'Safe'],
            ['status_id' => 2, 'status_key' => 'needs_help', 'status_label' => 'Needs help'],
        ]);
        DB::table('member_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'status_label' => 'Safe', 'is_active' => true],
            ['status_id' => 2, 'status_key' => 'injured', 'status_label' => 'Injured', 'is_active' => true],
        ]);
        DB::table('responders')->insert([
            ['responder_id' => 5, 'user_id' => 'rescuer-5'],
            ['responder_id' => 6, 'user_id' => 'rescuer-6'],
        ]);
        DB::table('responder_assignments')->insert([
            'assignment_id' => 7,
            'responder_id' => 5,
            'disaster_id' => 'EVT-1',
            'household_id' => 'HH-4',
            'status' => 'dispatched',
        ]);
        DB::table('households')->insert(['household_id' => 'HH-4', 'household_name' => 'Rivera family']);
        DB::table('household_members')->insert([
            ['member_id' => 'M-1', 'household_id' => 'HH-4', 'first_name' => 'Alex', 'last_name' => 'Rivera', 'is_household_head' => true],
            ['member_id' => 'M-2', 'household_id' => 'HH-4', 'first_name' => 'Sam', 'last_name' => 'Rivera', 'is_household_head' => false],
        ]);
        DB::table('household_disasters')->insert([
            'household_disaster_id' => 1,
            'household_id' => 'HH-4',
            'disaster_id' => 'EVT-1',
            'initial_status_id' => 1,
            'current_status_id' => 1,
        ]);
    }

    public function test_assignment_report_saves_member_assessments_and_accepts_an_identical_retry(): void
    {
        DB::table('responder_assignments')->where('assignment_id', 7)->update(['status' => 'en-route']);
        $workflow = app(RescuerFieldReportWorkflow::class);
        $first = $workflow->store($this->request($this->validPayload()));
        $firstData = $first->getData(true)['data'];
        $second = $workflow->store($this->request($this->validPayload()));

        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(201, $second->getStatusCode());
        $this->assertSame($firstData['report_id'], $second->getData(true)['data']['report_id']);
        $this->assertDatabaseCount('responder_field_reports', 1);
        $this->assertDatabaseCount('responder_field_report_members', 2);
        $this->assertDatabaseCount('household_status_logs', 1);
        $this->assertDatabaseCount('member_disaster_statuses', 2);
        $this->assertDatabaseHas('responder_field_reports', [
            'assignment_id' => 7,
            'household_id' => 'HH-4',
            'incident_type' => 'flood',
            'status_log_id' => $firstData['status_log_id'],
        ]);
        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 7, 'status' => 'en-route']);

        $differentPayload = $this->validPayload();
        $differentPayload['notes'] = 'A conflicting second report.';
        try {
            app(RescuerFieldReportWorkflow::class)->store($this->request($differentPayload));
            $this->fail('Expected a different report for the same assignment to be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $assignmentWorkflow = new RescuerAssignmentWorkflow(
            Mockery::mock(RoutingService::class),
            app(RescuerMobileSupport::class),
            app(RescuerAssignmentQuery::class),
        );
        $completed = $assignmentWorkflow->updateAssignmentStatus($this->request(['status' => 'completed']), 7);
        $this->assertSame('completed', $completed['assignment']->status);
    }

    public function test_assignment_report_rejects_incomplete_or_foreign_member_assessments(): void
    {
        $payload = $this->validPayload();
        $payload['members'] = [['member_id' => 'M-1', 'status_key' => 'injured']];

        try {
            app(RescuerFieldReportWorkflow::class)->store($this->request($payload));
            $this->fail('Expected incomplete household member assessments to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('members', $exception->errors());
        }

        $this->assertDatabaseCount('responder_field_reports', 0);
        $this->assertDatabaseCount('household_status_logs', 0);
    }

    public function test_assigned_household_details_are_limited_to_the_assignment_responder(): void
    {
        $workflow = app(RescuerFieldReportWorkflow::class);
        $response = $workflow->assignmentHousehold($this->request([]), 7);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('HH-4', $response->getData(true)['data']['household']['household_id']);
        $this->assertCount(2, $response->getData(true)['data']['members']);

        $notAssigned = $workflow->assignmentHousehold($this->request([], 'rescuer-6'), 7);
        $this->assertSame(404, $notAssigned->getStatusCode());

        $foreignReport = app(RescuerFieldReportWorkflow::class)->store($this->request($this->validPayload(), 'rescuer-6'));
        $this->assertSame(404, $foreignReport->getStatusCode());
    }

    public function test_household_assignment_cannot_be_completed_before_its_field_report(): void
    {
        $workflow = new RescuerAssignmentWorkflow(
            Mockery::mock(RoutingService::class),
            app(RescuerMobileSupport::class),
            app(RescuerAssignmentQuery::class),
        );

        try {
            $workflow->updateAssignmentStatus($this->request(['status' => 'completed']), 7);
            $this->fail('Expected assignment completion to require a field report.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 7, 'status' => 'dispatched']);
    }

    public function test_safe_report_does_not_cancel_the_assignment_before_rescuer_completion(): void
    {
        $payload = $this->validPayload();
        $payload['members'] = [
            ['member_id' => 'M-1', 'status_key' => 'safe'],
            ['member_id' => 'M-2', 'status_key' => 'safe'],
        ];

        app(RescuerFieldReportWorkflow::class)->store($this->request($payload));

        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 7, 'status' => 'dispatched']);
        $this->assertDatabaseHas('household_disasters', ['household_id' => 'HH-4', 'disaster_id' => 'EVT-1', 'current_status_id' => 1]);
    }

    public function test_migration_extends_legacy_report_table_without_removing_existing_rows(): void
    {
        Schema::dropIfExists('responder_field_report_members');
        Schema::dropIfExists('responder_field_reports');
        Schema::create('responder_field_reports', function (Blueprint $table): void {
            $table->integer('report_id')->primary();
            $table->unsignedInteger('responder_id');
            $table->string('disaster_id')->nullable();
            $table->string('household_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        DB::table('responder_field_reports')->insert([
            'report_id' => 91,
            'responder_id' => 5,
            'disaster_id' => 'EVT-1',
            'household_id' => 'HH-4',
            'created_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_17_000001_add_assignment_field_report_records.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('responder_field_reports', 'assignment_id'));
        $this->assertTrue(Schema::hasTable('responder_field_report_members'));
        $this->assertDatabaseHas('responder_field_reports', ['report_id' => 91, 'household_id' => 'HH-4']);
    }

    private function request(array $payload, string $userId = 'rescuer-5'): Request
    {
        $request = Request::create('/api/v1/rescuer/field-reports', 'POST', $payload);
        $request->setUserResolver(fn () => (object) ['user_id' => $userId]);

        return $request;
    }

    private function validPayload(): array
    {
        return [
            'assignment_id' => 7,
            'household_id' => 'HH-4',
            'idempotency_key' => 'field-report-7',
            'incident_type' => 'flood',
            'property_damage' => 'minor_damage',
            'death_count' => 0,
            'injured_count' => 1,
            'notes' => 'Water entered the ground floor.',
            'members' => [
                ['member_id' => 'M-1', 'status_key' => 'injured'],
                ['member_id' => 'M-2', 'status_key' => 'safe'],
            ],
        ];
    }

    private function createTables(): void
    {
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
            $table->string('status_label');
        });
        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->increments('status_log_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->unsignedInteger('status_id');
            $table->string('source');
            $table->string('submitted_by_user_id')->nullable();
            $table->unsignedInteger('responder_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('battery_level')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_name')->nullable();
            $table->string('deleted_at')->nullable();
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('is_household_head')->default(false);
            $table->string('deleted_at')->nullable();
        });
        Schema::create('member_statuses', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
            $table->string('status_label');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('member_disaster_statuses', function (Blueprint $table): void {
            $table->increments('member_status_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->string('member_id');
            $table->unsignedInteger('status_id');
            $table->string('report_source')->nullable();
            $table->string('reported_by_user_id')->nullable();
            $table->unsignedInteger('responder_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['disaster_id', 'member_id']);
        });
        Schema::create('responder_field_reports', function (Blueprint $table): void {
            $table->increments('report_id');
            $table->unsignedInteger('responder_id');
            $table->string('disaster_id')->nullable();
            $table->string('household_id')->nullable();
            $table->unsignedInteger('assignment_id')->nullable()->unique();
            $table->string('submitted_by_user_id')->nullable();
            $table->unsignedInteger('status_log_id')->nullable();
            $table->string('incident_type', 80)->nullable();
            $table->string('incident_type_other')->nullable();
            $table->string('property_damage', 80)->nullable();
            $table->unsignedInteger('death_count')->default(0);
            $table->unsignedInteger('injured_count')->default(0);
            $table->string('idempotency_key', 100)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('responder_field_report_members', function (Blueprint $table): void {
            $table->bigIncrements('detail_id');
            $table->integer('report_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->string('member_id');
            $table->unsignedInteger('status_id')->nullable();
            $table->string('status_key', 50);
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->increments('responder_id');
            $table->string('user_id');
            $table->unsignedInteger('team_id')->nullable();
            $table->boolean('is_deployed')->default(false);
            $table->string('duty_status')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->increments('assignment_id');
            $table->unsignedInteger('responder_id');
            $table->unsignedInteger('team_id')->nullable();
            $table->string('disaster_id');
            $table->string('household_id')->nullable();
            $table->string('status');
            $table->text('route_notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->increments('household_disaster_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->unsignedInteger('initial_status_id');
            $table->unsignedInteger('current_status_id')->nullable();
            $table->boolean('needs_dispatch')->nullable();
            $table->timestamps();
        });
    }
}