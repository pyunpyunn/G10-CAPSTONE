<?php

namespace Tests\Feature;

use App\Queries\RescuerAssignmentQuery;
use App\Services\Mobile\RescuerAssignmentWorkflow;
use App\Services\Mobile\RescuerMobileSupport;
use App\Services\Mobile\RescuerRouteWriter;
use App\Services\Shared\RoutingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AcceptedDispatchRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('responder_assignments', function (Blueprint $t): void {
            $t->integer('assignment_id')->primary(); $t->integer('responder_id');
            $t->integer('team_id')->nullable(); $t->string('household_id')->nullable();
            $t->string('assigned_area')->nullable(); $t->text('route_notes')->nullable();
            $t->string('status'); $t->text('outcome_notes')->nullable();
            foreach (['assigned_at', 'accepted_at', 'en_route_at', 'arrived_at', 'completed_at', 'updated_at'] as $column) $t->timestamp($column)->nullable();
        });
        Schema::create('geotagged_locations', function (Blueprint $t): void {
            $t->integer('location_id')->primary(); $t->string('household_id');
            $t->double('latitude'); $t->double('longitude'); $t->string('location_label')->nullable(); $t->timestamp('updated_at');
        });
        Schema::create('responder_routes', function (Blueprint $t): void {
            $t->integer('route_id')->primary(); $t->integer('assignment_id'); $t->integer('responder_id')->nullable();
            $t->string('household_id')->nullable(); $t->string('route_name')->nullable(); $t->string('route_status')->nullable();
            foreach (['start_latitude','start_longitude','end_latitude','end_longitude','estimated_distance_km','estimated_duration_min'] as $column) $t->double($column)->nullable();
            $t->text('route_polyline')->nullable(); $t->json('navigation_steps')->nullable(); $t->timestamps();
        });
        Schema::create('route_coordinates', function (Blueprint $t): void {
            $t->integer('coordinate_id')->primary(); $t->integer('route_id'); $t->double('latitude'); $t->double('longitude');
            $t->integer('sequence_order'); $t->double('accuracy_m')->nullable(); $t->timestamp('recorded_at')->nullable();
        });
        DB::table('responder_assignments')->insert(['assignment_id'=>12, 'responder_id'=>5, 'status'=>'dispatched',
            'route_notes'=>json_encode(['selected_responder_ids'=>[5,6,7], 'responder_households'=>[5=>['HH-A'],6=>['HH-B'],7=>[]]])]);
        DB::table('geotagged_locations')->insert([
            ['location_id'=>1,'household_id'=>'HH-A','latitude'=>10.291,'longitude'=>123.881,'updated_at'=>now()->subHour()],
            ['location_id'=>2,'household_id'=>'HH-A','latitude'=>10.292,'longitude'=>123.882,'updated_at'=>now()],
            ['location_id'=>3,'household_id'=>'HH-B','latitude'=>10.293,'longitude'=>123.883,'updated_at'=>now()],
        ]);
        config(['services.osrm.base_url'=>'https://routing.test']);
        Http::fake(['https://routing.test/*'=>function ($request) {
            preg_match('~/driving/([^?]+)~', $request->url(), $match);
            $points = array_map(fn ($pair) => array_map('floatval', explode(',', $pair)), explode(';', $match[1]));
            return Http::response(['routes'=>[['distance'=>1200,'duration'=>180,'geometry'=>['coordinates'=>$points],
                'legs'=>[['steps'=>[['maneuver'=>['type'=>'depart','location'=>$points[0]],'name'=>'Main Road','distance'=>1200],
                    ['maneuver'=>['type'=>'arrive','location'=>$points[1]],'distance'=>0]]]]]]]);
        }]);
    }

    public function test_acceptance_persists_road_route_to_coverage_household_without_a_worker(): void
    {
        $support = Mockery::mock(RescuerMobileSupport::class)->makePartial();
        $support->shouldReceive('responderForUser')->andReturn((object) ['responder_id'=>5,'team_id'=>null]);
        $support->shouldReceive('writeAuditLog')->andReturnNull();
        $workflow = new RescuerAssignmentWorkflow(app(RoutingService::class), $support, app(RescuerAssignmentQuery::class));
        foreach (['accepted', 'en_route'] as $status) {
            $request = Request::create('/', 'PATCH', ['status'=>$status,'latitude'=>10.29,'longitude'=>123.88]);
            $request->setUserResolver(fn () => (object) ['user_id'=>'rescuer-5']);
            $result = $workflow->updateAssignmentStatus($request,12);
            $this->assertNotNull($result['route']->route_polyline);
            $this->assertSame('HH-A',$result['route']->household_id);
            $this->assertSame(10.292,(float) $result['route']->end_latitude);
            $this->assertNotEmpty(json_decode($result['route']->navigation_steps,true));
            $this->assertDatabaseHas('responder_assignments',['assignment_id'=>12,'status'=>$status,'arrived_at'=>null]);
        }
        $this->assertDatabaseCount('responder_routes',1);
    }

    public function test_group_responders_get_separate_routes_and_empty_coverage_gets_no_destination(): void
    {
        $writer=app(RescuerRouteWriter::class);
        foreach ([5,6,7] as $id) $writer->refreshPlannedRoadRoute(12,['responder_id'=>$id,'latitude'=>10.29,'longitude'=>123.88]);
        $this->assertDatabaseCount('responder_routes',2);
        $this->assertDatabaseHas('responder_routes',['responder_id'=>5,'household_id'=>'HH-A','end_latitude'=>10.292]);
        $this->assertDatabaseHas('responder_routes',['responder_id'=>6,'household_id'=>'HH-B','end_latitude'=>10.293]);
        $this->assertNull(app(RescuerAssignmentQuery::class)->routeForAssignment(12,7));
    }

    public function test_routing_failure_does_not_invent_a_straight_line(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*'=>Http::response([],503)]);
        app(RescuerRouteWriter::class)->refreshPlannedRoadRoute(12,['responder_id'=>5,'latitude'=>10.29,'longitude'=>123.88]);
        $this->assertDatabaseCount('responder_routes',0);
    }
}
