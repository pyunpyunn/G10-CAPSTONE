<?php

namespace Tests\Feature;

use App\Services\Shared\SituationExternalSource;
use App\Services\Shared\EvaTrackReportClient;
use App\Services\Web\SituationReportSync;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SituationReportSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['situation_sync.enabled'=>true]); Http::preventStrayRequests();
    }

    private function sources(int $times=1): void
    {
        $this->mock(SituationExternalSource::class,function ($m) use ($times) {
            $m->shouldReceive('fetch')->with('safetrack','EV1')->times($times)->andReturn([
                'households'=>[['household_id'=>'H1','member_count'=>4]],
                'members'=>[['member_id'=>'M1','age'=>27,'gender'=>'Female','is_pwd'=>false]],
            ]);
            $m->shouldReceive('fetch')->with('evatrack','EV1')->times($times)->andReturn([
                'sites'=>[['site_id'=>'E1','name'=>'Latest School','status'=>'Open','capacity'=>20,'occupancy'=>5,'vacancy'=>15]],
            ]);
            $m->shouldReceive('fetch')->with('trackingaid','EV1')->times($times)->andReturn([
                'inventory'=>[['item'=>'Water','quantity'=>0,'unit'=>'boxes','status'=>'Available']],
                'requests'=>[['item'=>'Boat','quantity'=>1,'source'=>'TrackingAid','status'=>'Pending']],
            ]);
        });
    }

    public function test_complete_sync_enriches_findings_and_preserves_recorded_zero(): void
    {
        $this->sources(); $service=app(SituationReportSync::class);
        $sync=$service->requireReady('EV1');
        $this->assertTrue($sync['ready']);
        $this->assertSame($sync['sync_id'],$service->requireReady('EV1',$sync['sync_id'])['sync_id']);
        $summary=$service->apply([
            'household'=>['puroks'=>[['sitio'=>'Alaska','purok'=>'P1','purok_id'=>'P1','affected'=>1]],
                'details'=>[['household_id'=>'H1','sitio'=>'Alaska','purok'=>'P1','purok_id'=>'P1','members'=>[['member_id'=>'M1','status'=>'injured']]]]],
            'evacuation'=>['members'=>[],'households'=>[],'sites'=>[]],
        ],$sync);
        $this->assertSame(4,$summary['population_tables']['affected'][0]['persons']);
        $this->assertSame(1,$summary['population_tables']['affected'][0]['families']);
        $this->assertSame(27,$summary['household']['details'][0]['members'][0]['age']);
        $this->assertSame('injured',$summary['household']['details'][0]['members'][0]['status']);
        $this->assertSame('Latest School',$summary['evacuation']['sites'][0]['name']);
        $this->assertSame(0,$summary['inventory']['rows'][0]['quantity']);
        $this->assertSame('Boat',$summary['resources']['rows'][0]['item']);
        $this->assertArrayNotHasKey('payload',$summary['external_sync']);
    }

    public function test_failure_blocks_generation_and_never_exposes_upstream_secrets(): void
    {
        $this->mock(SituationExternalSource::class,fn ($m)=>$m->shouldReceive('fetch')->andThrow(new \RuntimeException('private-secret-key')));
        $service=app(SituationReportSync::class); $sync=$service->run('EV1');
        $this->assertFalse($sync['ready']);
        $this->assertStringNotContainsString('private-secret-key',json_encode($service->status($sync)));
        try { $service->requireReady('EV1',$sync['sync_id']); $this->fail('Incomplete sync must block generation.'); }
        catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(409,$e->getResponse()->getStatusCode()); }
    }

    public function test_expired_preflight_is_refetched_automatically(): void
    {
        $this->sources(2); $service=app(SituationReportSync::class);
        $first=$service->requireReady('EV1'); $this->travel(61)->seconds();
        $second=$service->requireReady('EV1',$first['sync_id']);
        $this->assertNotSame($first['sync_id'],$second['sync_id']);
    }

    public function test_csv_is_downloaded_without_credentials_and_normalized(): void
    {
        config(['evatrack.reports_url'=>'http://evatrack.test']);
        $this->mock(EvaTrackReportClient::class,fn ($m)=>$m->shouldReceive('generate')->once()->andReturn(response()->json([
            'download_url'=>'http://evatrack.test/report.csv?signature=abc','expires_at'=>now()->addMinutes(30)->toIso8601String(),
        ])));
        Http::fake(['evatrack.test/*'=>Http::response("center_id,center_name,status,capacity,occupancy\nE1,School,Open,10,0\n")]);
        $result=app(SituationExternalSource::class)->fetch('evatrack','EV1');
        $this->assertSame(0,$result['sites'][0]['occupancy']);
        $this->assertSame(10,$result['sites'][0]['vacancy']);
        Http::assertSent(fn ($r)=>!$r->hasHeader('X-API-KEY'));
    }

    public function test_foreign_download_origin_is_rejected_before_http(): void
    {
        config(['evatrack.reports_url'=>'http://evatrack.test']);
        $this->mock(EvaTrackReportClient::class,fn ($m)=>$m->shouldReceive('generate')->andReturn(response()->json([
            'download_url'=>'http://untrusted.test/report.csv','expires_at'=>now()->addMinutes(30)->toIso8601String(),
        ])));
        try { app(SituationExternalSource::class)->fetch('evatrack','EV1'); $this->fail('Foreign download rejected.'); }
        catch (\RuntimeException) { Http::assertNothingSent(); }
    }

    public function test_new_export_is_blocked_when_sync_fails_before_a_file_is_created(): void
    {
        $this->mock(\App\Services\Web\SituationReportService::class,fn ($m)=>$m->shouldReceive('eventSummary')->with('EV1')->andReturn(response()->json(['data'=>['summary'=>[
            'schema_version'=>'4.0','included_sections'=>['I'],'event'=>['event_id'=>'EV1'],
        ]]])));
        $this->mock(SituationExternalSource::class,fn ($m)=>$m->shouldReceive('fetch')->andThrow(new \RuntimeException('Offline')));
        $this->mock(\App\Services\Web\ReportFileService::class,fn ($m)=>$m->shouldNotReceive('generateSituationPdf'));
        try {
            app(\App\Services\Web\ReportGenerationService::class)->generate(\Illuminate\Http\Request::create('/','POST',[
                'report_type'=>'situation','event_id'=>'EV1','format'=>'pdf','included_sections'=>['I'],
            ]));
            $this->fail('A failed sync must prevent file creation.');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(409,$e->getResponse()->getStatusCode()); }
    }

    public function test_saved_snapshot_export_never_fetches_or_rewrites_external_data(): void
    {
        $snapshot=['schema_version'=>'4.0','included_sections'=>['I'],'report'=>['report_number'=>'OLD-1']];
        $this->mock(\App\Services\Web\SituationReportService::class,fn ($m)=>$m->shouldReceive('show')->with(1)->andReturn(response()->json(['data'=>['report'=>['summary'=>$snapshot]]])));
        $this->mock(SituationExternalSource::class,fn ($m)=>$m->shouldNotReceive('fetch'));
        $this->mock(\App\Services\Web\ReportFileService::class,fn ($m)=>$m->shouldReceive('generateSituationPdf')->once()->with($snapshot)->andReturn(['status'=>'success']));
        $response=app(\App\Services\Web\ReportGenerationService::class)->generate(\Illuminate\Http\Request::create('/','POST',[
            'report_type'=>'situation','sit_rep_id'=>1,'format'=>'pdf',
        ]));
        $this->assertSame(200,$response->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_sync_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/situation-reports/sync',['event_id'=>'EV1'])->assertUnauthorized();
        Http::assertNothingSent();
    }
}
