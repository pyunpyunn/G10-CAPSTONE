<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Presenters\SituationReportPresenter;
use App\Queries\DashboardQuery;
use App\Queries\SituationReportQuery;
use App\Services\Shared\BarangayProfileService;
use App\Services\Web\DashboardEventClosureWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DisasterEventClosureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t): void { $t->string('user_id')->primary(); $t->string('first_name')->nullable(); });
        Schema::create('disaster_events', function (Blueprint $t): void {
            $t->string('event_id')->primary(); $t->string('name'); $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamp('updated_at')->nullable();
        });
        Schema::create('situation_reports', function (Blueprint $t): void {
            $t->integer('sit_rep_id')->primary(); $t->string('report_number')->unique(); $t->string('disaster_id');
            foreach (['created_by_admin_id', 'household_id', 'report_status', 'reviewed_by_user_id', 'escalated_to', 'schema_version', 'sitrep_type', 'approved_by'] as $c) $t->string($c)->nullable();
            $t->text('summary'); $t->text('operator_inputs')->nullable(); $t->boolean('is_archived')->default(false);
            foreach (['reviewed_at', 'generated_at', 'archived_at', 'updated_at'] as $c) $t->timestamp($c)->nullable();
        });
        foreach (['household_status_logs', 'weather_logs', 'disaster_broadcasts'] as $table) {
            Schema::create($table, fn (Blueprint $t) => $t->string('disaster_id'));
        }
        Schema::create('resource_requests', fn (Blueprint $t) => $t->string('source_reference'));
        Schema::create('responder_assignments', function (Blueprint $t): void {
            $t->integer('assignment_id')->primary(); $t->string('disaster_id'); $t->string('status');
            $t->text('outcome_notes')->nullable(); $t->timestamp('updated_at')->nullable();
        });
        Schema::create('household_disasters', function (Blueprint $t): void {
            $t->string('disaster_id'); $t->boolean('needs_dispatch'); $t->timestamp('updated_at')->nullable();
        });
        Schema::create('incident_archives', function (Blueprint $t): void {
            $t->string('archive_id')->primary();
            foreach (['archive_type', 'disaster_id', 'reference_table', 'reference_id', 'archived_by_admin_id'] as $c) $t->string($c)->nullable();
            $t->text('archive_note'); $t->timestamp('archived_at'); $t->timestamp('created_at');
        });
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id('audit_log_id');
            foreach (['user_id', 'role_key', 'module', 'action', 'reference_table', 'reference_id', 'ip_address', 'user_agent'] as $c) $t->string($c)->nullable();
            $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->timestamp('created_at');
        });
        DB::table('users')->insert(['user_id' => 'OPERATOR', 'first_name' => 'Operator']);
        DB::table('disaster_events')->insert([
            ['event_id' => 'OLD', 'name' => 'Previous event', 'started_at' => now()->subDays(2), 'ended_at' => now()->subDay()],
            ['event_id' => 'CURRENT', 'name' => 'Current event', 'started_at' => now(), 'ended_at' => null],
        ]);
        DB::table('situation_reports')->insert(['sit_rep_id' => 1, 'report_number' => '1', 'disaster_id' => 'OLD', 'summary' => '{"original":true}']);
        DB::table('responder_assignments')->insert(['assignment_id' => 1, 'disaster_id' => 'CURRENT', 'status' => 'dispatched']);
        DB::table('household_disasters')->insert(['disaster_id' => 'CURRENT', 'needs_dispatch' => true]);
        $this->mock(DashboardQuery::class, fn ($mock) => $mock->shouldReceive('getHouseholdSummary')->andReturn(['reported' => 0, 'total' => 0, 'safe_total' => 0, 'evacuated' => 0, 'unsafe' => 0]));
        $query = \Mockery::mock(SituationReportQuery::class)->makePartial();
        $query->shouldReceive('findEvent')->andReturnUsing(fn ($id) => DB::table('disaster_events')->where('event_id', $id)->first());
        $query->shouldReceive('summarySources')->andReturn([]);
        $this->app->instance(SituationReportQuery::class, $query);
        $this->mock(BarangayProfileService::class, fn ($mock) => $mock->shouldReceive('current')->andReturn([]));
        $this->mock(SituationReportPresenter::class, fn ($mock) => $mock->shouldReceive('buildSummary')->andReturnUsing(
            fn ($event, $sources, $profile, $options) => ['report' => ['report_type' => 'final', 'approved_by' => null, 'report_number' => $options['report_number']], 'operator_inputs' => []]
        ));
    }

    private function request(): Request
    {
        $user = User::findOrFail('OPERATOR');
        $user->setRelation('role', new Role(['role_key' => 'operations_admin']));
        $request = Request::create('/api/v1/dashboard/active-event/close', 'POST');
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_closure_preserves_previous_report_and_commits_final_report_and_archive_once(): void
    {
        $workflow = app(DashboardEventClosureWorkflow::class);
        $result = $workflow->close($this->request());
        $this->assertSame('CURRENT', $result['event_id']);
        $this->assertNotNull(DB::table('disaster_events')->where('event_id', 'CURRENT')->value('ended_at'));
        $this->assertDatabaseHas('situation_reports', ['disaster_id' => 'CURRENT', 'report_number' => '2', 'sitrep_type' => 'final']);
        $this->assertSame('{"original":true}', DB::table('situation_reports')->where('sit_rep_id', 1)->value('summary'));
        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 1, 'status' => 'cancelled']);
        $this->assertDatabaseHas('household_disasters', ['disaster_id' => 'CURRENT', 'needs_dispatch' => false]);
        $this->assertDatabaseCount('incident_archives', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertNull($workflow->close($this->request()));
        $this->assertDatabaseCount('situation_reports', 2);
        $this->assertDatabaseCount('incident_archives', 1);
    }

    public function test_snapshot_failure_rolls_back_event_and_operational_changes(): void
    {
        $this->mock(SituationReportPresenter::class, fn ($mock) => $mock->shouldReceive('buildSummary')->andThrow(new \RuntimeException('Snapshot unavailable')));
        try {
            app(DashboardEventClosureWorkflow::class)->close($this->request());
            $this->fail('The closure must not commit without its required snapshot.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Snapshot unavailable', $error->getMessage());
        }
        $this->assertNull(DB::table('disaster_events')->where('event_id', 'CURRENT')->value('ended_at'));
        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 1, 'status' => 'dispatched']);
        $this->assertDatabaseHas('household_disasters', ['disaster_id' => 'CURRENT', 'needs_dispatch' => true]);
        $this->assertDatabaseCount('situation_reports', 1);
        $this->assertDatabaseCount('incident_archives', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
