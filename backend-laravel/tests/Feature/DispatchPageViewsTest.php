<?php

namespace Tests\Feature;

use App\Queries\RescueDispatchQuery;
use App\Queries\SitioPriorityQuery;
use App\Presenters\RescueDispatchPresenter;
use App\Services\Web\RescueDispatchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class DispatchPageViewsTest extends TestCase
{
    private function service($query): RescueDispatchService
    {
        return new RescueDispatchService(Mockery::mock(\App\Services\Shared\OneSignalNotificationService::class),
            new RescueDispatchPresenter(),$query,Mockery::mock(\App\Services\Web\RescueDispatchWorkflow::class),Mockery::mock(\App\Queries\AreaCoverageQuery::class));
    }
    private function query()
    {
        $query=Mockery::mock(RescueDispatchQuery::class);
        $query->shouldReceive('activeEvent')->once()->andReturn((object)['event_id'=>'EV-1','name'=>'Flood','started_at'=>null]);
        $query->shouldNotReceive('riskAreas'); $query->shouldNotReceive('activity');
        return $query;
    }
    public function test_management_queries_only_its_paged_assignment_list(): void
    {
        $query=$this->query();
        $query->shouldNotReceive('teamCards'); $query->shouldNotReceive('responders');
        $query->shouldReceive('dispatches')->once()->andReturn(new LengthAwarePaginator([(object)[
            'assignment_id'=>12,'assignment_code'=>'DSP-12','team_name'=>'SAR','assigned_area'=>'A',
            'priority_level'=>'high','assigned_at'=>null,'status'=>'completed',
        ]],1,20));
        $data=$this->service($query)->index(Request::create('/api/v1/dispatches','GET',['view'=>'management']))->getData(true)['data'];
        $this->assertSame(1,$data['dispatches']['meta']['total']);
        $this->assertSame('completed',$data['dispatches']['data'][0]['status']['key']);
        $this->assertArrayNotHasKey('route_notes',$data['dispatches']['data'][0]);
        $this->assertArrayNotHasKey('risk_areas',$data);
        $this->assertArrayNotHasKey('teams',$data);
    }
    public function test_overview_combines_priorities_and_summary_without_loading_hidden_panel_data(): void
    {
        $query=$this->query();
        $query->shouldReceive('teamCards')->once()->andReturn(collect());
        $query->shouldReceive('summary')->once()->andReturn(['completed'=>1]);
        $query->shouldReceive('activeDispatches')->once()->andReturn([]);
        $query->shouldNotReceive('dispatches'); $query->shouldNotReceive('teamCoverage'); $query->shouldNotReceive('responders');
        $ranked=Mockery::mock(SitioPriorityQuery::class);
        $ranked->shouldReceive('ranked')->with('EV-1')->once()->andReturn([['sitio_id'=>1,'sitio'=>'A']]);
        $this->app->instance(SitioPriorityQuery::class,$ranked);
        $data=$this->service($query)->index(Request::create('/api/v1/dispatches','GET',['view'=>'overview']))->getData(true)['data'];
        $this->assertSame(1,$data['summary']['completed']);
        $this->assertSame([], $data['priorities'][0]['activeDispatches']);
        $this->assertArrayNotHasKey('responders',$data);
        $this->assertArrayNotHasKey('dispatch_history',$data);
    }
}
