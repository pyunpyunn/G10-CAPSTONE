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
    use \Tests\Support\DispatchLifecycleFixture;
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
        (require database_path('migrations/2026_10_24_000001_add_field_report_catalogs.php'))->up();
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
        $assignmentWorkflow->updateAssignmentStatus($this->request(['status' => 'on_scene']), 7);
        $completed = $assignmentWorkflow->updateAssignmentStatus($this->request(['status' => 'completed', 'household_id' => 'HH-4', 'household_status_key' => 'safe']), 7);
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

    public function test_household_cannot_be_completed_before_acceptance_and_arrival(): void
    {
        $workflow = new RescuerAssignmentWorkflow(
            Mockery::mock(RoutingService::class),
            app(RescuerMobileSupport::class),
            app(RescuerAssignmentQuery::class),
        );

        try {
            $workflow->updateAssignmentStatus($this->request(['status' => 'completed']), 7);
            $this->fail('Expected completion before acceptance and arrival to be rejected.');
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

    public function test_area_dispatch_advances_households_and_keeps_other_rescuers_work_open(): void
    {
        (require database_path('migrations/2026_10_23_000001_allow_household_queue_field_reports.php'))->up();
        DB::table('households')->insert(['household_id' => 'HH-5', 'household_name' => 'Next family']);
        DB::table('household_members')->insert(['member_id' => 'M-3', 'household_id' => 'HH-5', 'first_name' => 'Next', 'last_name' => 'Member']);
        DB::table('responder_assignments')->where('assignment_id', 7)->update([
            'household_id' => null,
            'route_notes' => json_encode(['selected_responder_ids' => [5, 6], 'responder_households' => [5 => ['HH-4', 'HH-5'], 6 => ['HH-4']]]),
        ]);
        $reports = app(RescuerFieldReportWorkflow::class);
        $workflow = app(RescuerAssignmentWorkflow::class);
        $this->assertSame('HH-4', $reports->assignmentHousehold($this->request([]), 7)->getData(true)['data']['household']['household_id']);
        $reports->store($this->request($this->validPayload()));
        $this->arrive($workflow);
        $workflow->updateAssignmentStatus($this->request(['status' => 'completed', 'household_id' => 'HH-4', 'household_status_key' => 'safe']), 7);
        $this->assertSame('HH-5', $reports->assignmentHousehold($this->request([]), 7)->getData(true)['data']['household']['household_id']);
        try {
            $workflow->updateAssignmentStatus($this->request(['status' => 'completed', 'household_id' => 'HH-4']), 7);
            $this->fail('A stale completion must not advance another household.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $next = $this->validPayload();
        $next['household_id'] = 'HH-5';
        $next['idempotency_key'] = 'report-7-HH-5';
        $next['members'] = [['member_id' => 'M-3', 'status_key' => 'safe']];
        $next['injured_count'] = 0;
        $reports->store($this->request($next));
        $this->arrive($workflow);
        $workflow->updateAssignmentStatus($this->request(['status' => 'completed', 'household_id' => 'HH-5', 'household_status_key' => 'safe']), 7);
        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 7, 'status' => 'dispatched', 'completed_at' => null]);
        $this->assertDatabaseHas('responders', ['responder_id' => 5, 'is_deployed' => false]);
        $this->assertDatabaseHas('responders', ['responder_id' => 6, 'is_deployed' => false]);
        $reports->store($this->request($this->validPayload(), 'rescuer-6'));
        $this->arrive($workflow, 'rescuer-6');
        $workflow->updateAssignmentStatus($this->request(['status' => 'completed', 'household_id' => 'HH-4', 'household_status_key' => 'safe'], 'rescuer-6'), 7);
        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 7, 'status' => 'completed']);
        $this->assertDatabaseCount('responder_field_reports', 3);
    }

    public function test_web_ranking_dispatch_mobile_routes_optional_reports_and_queue_completion(): void
    {
        $this->lifecycleFixture();
        \Illuminate\Support\Facades\Bus::fake();
        config(['services.osrm.base_url' => 'https://routing.test']);
        \Illuminate\Support\Facades\Http::fake(['https://routing.test/*' => function ($request) {
            preg_match('~/driving/([^?]+)~', $request->url(), $match);
            $points = array_map(fn ($pair) => array_map('floatval', explode(',', $pair)), explode(';', $match[1]));
            return \Illuminate\Support\Facades\Http::response(['routes'=>[['distance'=>1000,'duration'=>120,
                'geometry'=>['coordinates'=>$points], 'legs'=>[['steps'=>[['name'=>'Test Road','distance'=>1000,
                    'maneuver'=>['type'=>'depart','location'=>$points[0]]]]]]]]]);
        }]);
        // Start with unsafe reports, not precomputed priority numbers.
        foreach (['HH-4','HH-5','HH-6'] as $id) {
            app(\App\Actions\UpdateHouseholdStatus::class)->apply('EVT-1', $id, 2, 'needs_help', 'test_household', 'Unsafe test data', null, null);
            DB::table('household_status_logs')->insert(['household_id'=>$id,'disaster_id'=>'EVT-1','status_id'=>2,'source'=>'household','submitted_at'=>now()]);
        }
        $before = app(\App\Queries\DashboardQuery::class)->getHouseholdSummary('EVT-1');
        $this->assertSame(3, $before['unsafe']);
        $ranked = app(\App\Queries\SitioPriorityQuery::class)->ranked('EVT-1');
        $this->assertSame(1, $ranked[0]['sitio_id']);
        $this->assertGreaterThan($ranked[1]['priority_score'], $ranked[0]['priority_score']);
        $web = $this->request(['sitio_id'=>1,'purok_id'=>1,'team_id'=>1,'selected_responder_ids'=>[5,6],
            'responder_count'=>2,'household_ids'=>['HH-4','HH-5','HH-6'],
            'household_groups'=>[['HH-4','HH-5'],['HH-6']], 'households_to_cover'=>3,
            'assigned_area'=>'Purok 1, Test Priority','priority_level'=>'critical','status'=>'dispatched']);
        $web->setUserResolver(fn () => (object)['user_id'=>'test-hq','role'=>(object)['role_key'=>'chair']]);
        $created = app(\App\Services\Web\RescueDispatchService::class)->store($web);
        $this->assertSame(201, $created->getStatusCode());
        $id = $created->getData(true)['data']['assignment_id'];
        $this->assertSame('queued', $created->getData(true)['data']['push_delivery']['status']);
        \Illuminate\Support\Facades\Bus::assertChained([\App\Jobs\SendDispatchPush::class]);
        $query = app(RescuerAssignmentQuery::class);
        $this->assertSame('HH-4', $query->list(5,10)->first()->household_id);
        $this->assertSame('HH-6', $query->list(6,10)->first()->household_id);
        $workflow = app(RescuerAssignmentWorkflow::class);
        $change = function ($status, $actor=5, $extra=[]) use ($workflow,$id) {
            return $workflow->updateAssignmentStatus($this->request(['status'=>$status]+$extra,'rescuer-'.$actor),$id);
        };
        $change('accepted',5,['latitude'=>10.28,'longitude'=>123.87]);
        $enRoute = $change('en_route',5,['latitude'=>10.281,'longitude'=>123.871]);
        $this->assertSame('HH-4',$enRoute['route']->household_id);
        $this->assertNotEmpty(json_decode($enRoute['route']->route_polyline,true));
        $fresh = $workflow->storeLocation($this->request(['latitude'=>10.282,'longitude'=>123.872,'refresh_route'=>true]),$id);
        $this->assertSame(200,$fresh->getStatusCode());
        $this->assertNotEmpty($fresh->getData(true)['data']['route']['coordinates']);
        $this->assertDatabaseHas('responder_routes',['assignment_id'=>$id,'responder_id'=>5,'start_latitude'=>10.282,'start_longitude'=>123.872]);
        $mapRoutes = app(\App\Queries\MappingQuery::class)->getDispatchRoutes('EVT-1');
        $this->assertCount(1,$mapRoutes);
        $this->assertSame(5,(int)$mapRoutes[0]['responder_id']);
        $filter = new \ReflectionMethod(\App\Services\Web\MappingService::class,'routesInsideBoundary');
        $this->assertCount(1,$filter->invoke(app(\App\Services\Web\MappingService::class),$mapRoutes,['barangay_id'=>1,'boundary'=>null]));
        $change('accepted',6,['latitude'=>10.28,'longitude'=>123.87]);
        $this->assertSame('en_route',$query->find($id,5)->status);
        $this->assertSame('accepted',$query->find($id,6)->status);
        $change('on_scene');
        // No field report: explicit safe confirmation still creates official status/history.
        $next = $change('completed',5,['household_id'=>'HH-4','household_status_key'=>'safe']);
        $this->assertSame('HH-5',$next['assignment']->household_id);
        $this->assertSame('dispatched',$next['assignment']->status);
        $this->assertNull($next['route']); // Never show the previous household's directions.
        $this->assertDatabaseCount('responder_field_reports',0);
        $this->assertDatabaseHas('household_disasters',['household_id'=>'HH-4','current_status_id'=>1,'needs_dispatch'=>false]);
        $middle = app(\App\Queries\DashboardQuery::class)->getHouseholdSummary('EVT-1');
        $this->assertSame(2,$middle['unsafe']); $this->assertSame(1,$middle['safe_total']);
        $change('accepted',5,['latitude'=>10.29,'longitude'=>123.88]);
        $secondRoute = $change('en_route',5,['latitude'=>10.29,'longitude'=>123.88]);
        $this->assertSame('HH-5',$secondRoute['route']->household_id);
        $this->assertNotSame($enRoute['route']->route_id,$secondRoute['route']->route_id);
        $change('on_scene');
        $report=$this->validPayload(); $report['assignment_id']=$id; $report['household_id']='HH-5';
        $report['members']=[['member_id'=>'M-3','status_key'=>'safe']]; $report['injured_count']=0;
        app(RescuerFieldReportWorkflow::class)->store($this->request($report));
        $this->assertDatabaseHas('member_disaster_statuses',['member_id'=>'M-3','status_id'=>1]);
        $this->assertDatabaseHas('household_disasters',['household_id'=>'HH-5','current_status_id'=>1]);
        $change('completed',5,['household_id'=>'HH-5','household_status_key'=>'safe']);
        $this->assertNull($query->find($id,5)->household_id);
        $this->assertSame('completed',$query->find($id,5)->status);
        $this->assertSame('accepted',$query->find($id,6)->status);
        $this->assertDatabaseHas('responders',['responder_id'=>5,'is_deployed'=>false]);
        $this->assertDatabaseHas('responders',['responder_id'=>6,'is_deployed'=>true]);
        $change('en_route',6,['latitude'=>10.28,'longitude'=>123.87]); $change('on_scene',6);
        $change('completed',6,['household_id'=>'HH-6','household_status_key'=>'safe']);
        $this->assertDatabaseHas('responder_assignments',['assignment_id'=>$id,'status'=>'completed']);
        $this->assertSame(0,app(\App\Queries\DashboardQuery::class)->getHouseholdSummary('EVT-1')['unsafe']);
        $this->assertSame(3,app(\App\Queries\DashboardQuery::class)->getHouseholdSummary('EVT-1')['safe_total']);
        $this->assertSame(3,app(\App\Queries\SitioPriorityQuery::class)->ranked('EVT-1')[0]['status_counts']['safe']);
        $coverage = app(\App\Queries\RescueDispatchQuery::class)->teamCoverage('EVT-1');
        $this->assertSame(100,$coverage[0]['coverage_percent']);
        $outcomes = app(\App\Queries\RescueDispatchQuery::class)->reportOutcomes(DB::table('responder_assignments')->where('assignment_id',$id)->first());
        $this->assertSame(3,$outcomes['safe_count']);
    }

    public function test_completion_without_status_confirmation_is_atomic_and_field_report_is_optional(): void
    {
        $workflow = app(RescuerAssignmentWorkflow::class);
        $this->arrive($workflow);
        try {
            $workflow->updateAssignmentStatus($this->request(['status'=>'completed','household_id'=>'HH-4']),7);
            $this->fail('An explicit household status is required.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('household_status_key',$error->errors());
        }
        $this->assertDatabaseCount('household_status_logs',0);
        $this->assertDatabaseHas('responder_assignments',['assignment_id'=>7,'status'=>'on_scene']);
        DB::table('household_statuses')->insert(['status_id'=>3,'status_key'=>'evacuated','status_label'=>'Evacuated']);
        $workflow->updateAssignmentStatus($this->request(['status'=>'completed','household_id'=>'HH-4','household_status_key'=>'evacuated']),7);
        $this->assertDatabaseCount('responder_field_reports',0);
        $this->assertDatabaseHas('household_disasters',['household_id'=>'HH-4','current_status_id'=>3,'needs_dispatch'=>false]);
        $this->assertDatabaseHas('household_status_logs',['household_id'=>'HH-4','status_id'=>3,'source'=>'responder_completion']);
    }

    public function test_report_catalogs_defaults_and_casualty_totals_come_from_database(): void
    {
        DB::table('member_statuses')->insert([
            ['status_id'=>3,'status_key'=>'unknown','status_label'=>'Unchecked','counts_as_death'=>false,'counts_as_injury'=>false],
            ['status_id'=>4,'status_key'=>'fatal_case','status_label'=>'Confirmed death','counts_as_death'=>true,'counts_as_injury'=>false],
        ]);
        DB::table('property_damage_levels')->insert(['damage_key'=>'custom_level','label'=>'Custom database level','sort_order'=>10,'is_active'=>true]);
        DB::table('member_disaster_statuses')->insert(['disaster_id'=>'EVT-1','household_id'=>'HH-4','member_id'=>'M-1','status_id'=>2]);
        $data=app(RescuerFieldReportWorkflow::class)->assignmentHousehold($this->request([]),7)->getData(true)['data'];
        $this->assertSame('injured',$data['members'][0]['member_status']['key']);
        $this->assertSame('unknown',$data['members'][1]['member_status']['key']);
        $this->assertContains('custom_level',array_column($data['property_damage_options'],'key'));
        $payload=$this->validPayload(); unset($payload['incident_type']);
        $payload['property_damage']='custom_level';
        $payload['members']=[['member_id'=>'M-1','status_key'=>'injured'],['member_id'=>'M-2','status_key'=>'fatal_case']];
        $payload['death_count']=999; $payload['injured_count']=999;
        app(RescuerFieldReportWorkflow::class)->store($this->request($payload));
        $this->assertDatabaseHas('responder_field_reports',['assignment_id'=>7,'property_damage'=>'custom_level','incident_type'=>null,'death_count'=>1,'injured_count'=>1]);
    }

    public function test_dispatch_history_is_paginated_and_limited_to_the_rescuer(): void
    {
        $this->lifecycleFixture();
        foreach (range(1,23) as $id) DB::table('responder_assignments')->insert(['assignment_id'=>$id,'responder_id'=>$id===23?6:5,'disaster_id'=>'EVT-1','assignment_code'=>'TEST-'.$id,'status'=>'completed','assigned_at'=>now()]);
        $controller=app(\App\Http\Controllers\Api\RescuerDispatchHistoryController::class);
        $response=$controller->index($this->request([]),app(RescuerMobileSupport::class),app(\App\Presenters\RescuerAssignmentPresenter::class))->getData(true)['data'];
        $this->assertSame(22,$response['pagination']['total']);
        $this->assertSame(2,$response['pagination']['last_page']);
        $this->assertCount(20,$response['assignments']);
        $this->assertNotContains(23,array_column($response['assignments'],'assignment_id'));
    }

    private function arrive(RescuerAssignmentWorkflow $workflow, string $user = 'rescuer-5'): void
    {
        foreach (['accepted', 'en_route', 'on_scene'] as $status) $workflow->updateAssignmentStatus($this->request(['status' => $status], $user), 7);
    }

    private function request(array $payload, string $userId = 'rescuer-5'): Request
    {
        $request = Request::create('/api/v1/rescuer/field-reports', 'POST', $payload);
        $request->setUserResolver(fn () => (object) ['user_id' => $userId, 'role' => (object)['role_key' => 'rescuer']]);

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
