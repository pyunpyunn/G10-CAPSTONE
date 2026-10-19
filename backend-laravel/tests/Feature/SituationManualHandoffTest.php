<?php

namespace Tests\Feature;

use App\Queries\SituationReportQuery;
use App\Services\Web\SituationReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SituationManualHandoffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('situation_reports',function ($t) { $t->integer('sit_rep_id')->primary(); $t->string('disaster_id'); $t->string('report_number'); $t->text('summary'); });
        (require database_path('migrations/2026_10_18_000021_create_situation_report_submissions.php'))->up();
        DB::table('situation_reports')->insert(['sit_rep_id'=>1,'disaster_id'=>'EV1','report_number'=>'1','summary'=>'{"locked":"original"}']);
    }

    public function test_v3_metadata_migration_preserves_existing_snapshot_json(): void
    {
        $migration=require database_path('migrations/2026_10_18_000020_add_sitrep_v3_metadata.php');
        $migration->up();
        $row=DB::table('situation_reports')->first();
        $this->assertSame('2.0',$row->schema_version);
        $this->assertSame('{"locked":"original"}',$row->summary);
        $this->assertNull($row->operator_inputs);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('situation_reports','schema_version'));
        $this->assertSame('{"locked":"original"}',DB::table('situation_reports')->value('summary'));
    }

    public function test_historical_snapshot_projection_does_not_change_its_original_data(): void
    {
        $original=['included_sections'=>['III','IV','VI','VIII'],'actions_text'=>'Plan','report'=>['report_number'=>'OLD-1']];
        $copy=\App\Support\SituationReportSnapshot::forRendering($original);
        $this->assertSame(['IV','VI'],$copy['included_sections']);
        $this->assertSame('4.0',$copy['schema_version']);
        $this->assertArrayNotHasKey('schema_version',$original);
        $this->assertSame(['III','IV','VI','VIII'],$original['included_sections']);
        $this->assertSame('OLD-1',$copy['report']['report_number']);
    }

    public function test_manual_submission_is_separate_from_the_locked_report(): void
    {
        $this->mock(SituationReportQuery::class,fn ($mock) => $mock->shouldReceive('findReport')->with(1)->andReturn((object) ['sit_rep_id'=>1]));
        $request=Request::create('/','POST',['submitted_at'=>'2026-10-17T09:30:00+08:00','delivered_by'=>'Barangay Staff','received_by'=>'City Receiving Staff']);
        $request->setUserResolver(fn () => new \App\Models\User(['user_id'=>'ADMIN-1']));
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $response=app(SituationReportService::class)->recordSubmission($request,1);
        $this->assertSame(201,$response->getStatusCode());
        $entry=DB::table('situation_report_submissions')->first();
        $this->assertSame('Barangay Staff',$entry->delivered_by);
        $this->assertSame('City Receiving Staff',$entry->received_by);
        $this->assertSame('{"locked":"original"}',DB::table('situation_reports')->value('summary'));
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_numbering_is_sequential_and_independent_for_each_event(): void
    {
        $query=app(SituationReportQuery::class);
        $this->assertSame('2',$query->nextReportNumber('EV1'));
        $this->assertSame('3',$query->nextReportNumber('EV1'));
        $this->assertSame('1',$query->nextReportNumber('EV2'));
        $this->assertSame('2',$query->nextReportNumber('EV2'));
    }

    public function test_print_layout_has_manual_receipt_confidential_annexes_and_supersession(): void
    {
        $html=view('reports.situation',['summary'=>[
            'schema_version'=>'4.0','included_sections'=>['III','IV'],
            'report'=>['supersedes'=>['report_number'=>'2']],
        ]])->render();
        foreach (['Signature / date','Supersedes Situation Report No.'] as $text) $this->assertStringContainsString($text,$html);
        $this->assertStringNotContainsString('Received by',$html);
        $this->assertStringNotContainsString('Annex ',$html);
        $this->assertStringNotContainsString('OCD Region',$html);
        $this->assertStringNotContainsString('NDRRMC',$html);
    }
}
