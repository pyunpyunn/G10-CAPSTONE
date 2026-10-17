<?php

namespace Tests\Feature;

use App\Services\Web\ReportFileService;
use App\Services\Web\ReportGenerationService;
use App\Services\Web\SituationReportService;
use Illuminate\Http\Request;
use Tests\TestCase;

class SituationRedesignTest extends TestCase
{
    public function test_draft_pdf_receives_preparer_reviewer_period_and_section_selection(): void
    {
        $summary = ['report' => ['report_number' => 'DRAFT'], 'actions_text' => '', 'included_sections' => []];
        $this->mock(SituationReportService::class, function ($mock) use ($summary): void {
            $mock->shouldReceive('eventSummary')->once()->with('EV-1')->andReturn(response()->json(['data' => ['summary' => $summary]]));
        });
        $this->mock(ReportFileService::class, function ($mock): void {
            $mock->shouldReceive('generateSituationPdf')->once()->with(\Mockery::on(function ($summary): bool {
                $this->assertSame('Duty preparer', $summary['report']['prepared_by']);
                $this->assertSame('Duty reviewer', $summary['report']['reviewed_by']);
                $this->assertSame('CUSTOM-1', $summary['report']['report_number']);
                $this->assertSame('Oct 16, 2026 8:00 AM', $summary['report']['period_start']);
                $this->assertSame('Oct 16, 2026 6:00 PM', $summary['report']['period_end']);
                $this->assertSame(['II', 'VIII'], $summary['included_sections']);
                $this->assertSame('Recorded actions', $summary['actions_text']);
                return true;
            }))->andReturn(['status' => 'success']);
        });
        $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'event_id' => 'EV-1', 'format' => 'pdf',
            'report_number' => 'CUSTOM-1', 'prepared_by' => 'Duty preparer', 'reviewed_by' => 'Duty reviewer',
            'period_start' => '2026-10-16T08:00', 'period_end' => '2026-10-16T18:00',
            'included_sections' => ['II', 'VIII'], 'actions_text' => 'Recorded actions',
        ]));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_saved_snapshot_export_ignores_client_metadata_changes(): void
    {
        $summary = ['report' => ['report_number' => 'SAVED', 'prepared_by' => 'Original'], 'actions_text' => 'Original actions', 'included_sections' => ['I']];
        $this->mock(SituationReportService::class, function ($mock) use ($summary): void {
            $mock->shouldReceive('show')->once()->with(9)->andReturn(response()->json(['data' => ['report' => ['summary' => $summary]]]));
        });
        $this->mock(ReportFileService::class, function ($mock) use ($summary): void {
            $mock->shouldReceive('generateSituationPdf')->once()->with($summary)->andReturn(['status' => 'success']);
        });
        app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'sit_rep_id' => 9, 'format' => 'pdf', 'prepared_by' => 'Changed',
            'included_sections' => ['I'], 'actions_text' => 'Changed actions',
        ]));
    }

    public function test_saved_report_can_export_population_only_without_changing_its_metadata(): void
    {
        $summary = ['report' => ['report_number' => 'SAVED', 'prepared_by' => 'Original'], 'actions_text' => 'Original actions', 'included_sections' => ['I', 'II', 'VIII']];
        $this->mock(SituationReportService::class, function ($mock) use ($summary): void {
            $mock->shouldReceive('show')->once()->with(9)->andReturn(response()->json(['data' => ['report' => ['summary' => $summary]]]));
        });
        $this->mock(ReportFileService::class, function ($mock) use ($summary): void {
            $mock->shouldReceive('generateSituationPdf')->once()->with(array_replace($summary, ['included_sections' => ['II']]))->andReturn(['status' => 'success']);
        });
        app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'sit_rep_id' => 9, 'format' => 'pdf', 'prepared_by' => 'Changed',
            'included_sections' => ['II'], 'actions_text' => 'Changed actions',
        ]));
    }

    public function test_summary_uses_registered_purok_ids_and_latest_valid_event_status(): void
    {
        $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
        $tables = [
            'households' => ['household_id','address_id','deleted_at'],
            'addresses' => ['address_id','purok_id'],
            'puroks' => ['purok_id','sitio_id','purok_name'],
            'sitios' => ['sitio_id','sitio_name','barangay_id'],
            'household_status_logs' => ['status_log_id','household_id','disaster_id','status_id','submitted_at'],
            'household_statuses' => ['status_id','status_key'],
            'weather_logs' => ['weather_log_id','disaster_id','observed_at','created_at'],
            'evacuation_centers' => ['current_event_id','deleted_at','name'],
            'responder_assignments' => ['assignment_id','team_id','responder_id','disaster_id','route_notes','outcome_notes','assigned_at','assigned_area','status','household_id','affected_area_id'],
            'affected_areas' => ['affected_area_id','disaster_id','sitio_id','purok_id'],
            'rescue_teams' => ['team_id','team_name'], 'responders' => ['responder_id','full_name'],
            'disaster_broadcasts' => ['disaster_id','sent_at','target_purok_id','target_area_id'],
            'resource_requests' => ['source_reference','created_at'],
        ];
        foreach ($tables as $name => $columns) $schema->create($name, function (\Illuminate\Database\Schema\Blueprint $t) use ($columns): void {
            foreach ($columns as $column) $t->string($column)->nullable();
        });
        $this->mock(\App\Services\Shared\BarangayProfileService::class, fn ($mock) => $mock->shouldReceive('current')->andReturn(['barangay_id' => 1]));
        $db = \Illuminate\Support\Facades\DB::class;
        $db::table('sitios')->insert([
            ['sitio_id'=>1,'sitio_name'=>'Sitio A','barangay_id'=>1], ['sitio_id'=>2,'sitio_name'=>'Sitio B','barangay_id'=>1], ['sitio_id'=>3,'sitio_name'=>'Elsewhere','barangay_id'=>2], ['sitio_id'=>4,'sitio_name'=>'Sitio Empty','barangay_id'=>1],
        ]);
        foreach ([10=>1,20=>2,30=>3] as $purok=>$sitio) $db::table('puroks')->insert(['purok_id'=>$purok,'sitio_id'=>$sitio,'purok_name'=>'Purok 1']);
        foreach ([1=>10,2=>20,3=>30,4=>null,5=>10] as $id=>$purok) {
            $db::table('addresses')->insert(['address_id'=>$id,'purok_id'=>$purok]);
            $db::table('households')->insert(['household_id'=>'HH-'.$id,'address_id'=>$id,'deleted_at'=>$id===5?'2026-10-16':null]);
        }
        $db::table('household_statuses')->insert([['status_id'=>1,'status_key'=>'unsafe'],['status_id'=>2,'status_key'=>'safe_at_home'],['status_id'=>3,'status_key'=>'unchecked']]);
        foreach ([[1,'HH-1','EV-1',1,'08:00'],[2,'HH-1','EV-1',2,'09:00'],[3,'HH-1','EV-1',3,'10:00'],[4,'HH-2','OTHER',1,'11:00'],[5,'HH-3','EV-1',1,'11:00']] as [$id,$household,$event,$status,$time]) {
            $db::table('household_status_logs')->insert(['status_log_id'=>$id,'household_id'=>$household,'disaster_id'=>$event,'status_id'=>$status,'submitted_at'=>'2026-10-16 '.$time]);
        }
        $db::table('weather_logs')->insert([
            ['weather_log_id'=>1,'disaster_id'=>'EV-1','observed_at'=>'2026-10-16 08:00:00','created_at'=>'2026-10-16 08:00:00'],
            ['weather_log_id'=>2,'disaster_id'=>null,'observed_at'=>'2026-10-16 09:00:00','created_at'=>'2026-10-16 09:00:00'],
        ]);
        $sources = app(\App\Queries\SituationReportQuery::class)->summarySources('EV-1');
        $this->assertSame('2', (string) $sources['weather']->weather_log_id);
        $this->assertSame(2, $sources['household_total']);
        $this->assertSame(1, $sources['household_reported']);
        $this->assertSame(1, (int) $sources['household_counts']['safe_at_home']);
        $this->assertCount(2, $sources['purok_rows']);
        $this->assertSame(['10','20'], $sources['purok_rows']->pluck('purok_id')->all());
        $this->assertSame(2, (int) $sources['purok_rows']->sum('total'));
        $this->assertSame(1, (int) $sources['purok_rows'][0]->safe);
        $this->assertSame(0, (int) $sources['purok_rows'][1]->reported);

        $db::table('affected_areas')->insert([
            ['affected_area_id'=>100, 'disaster_id'=>'EV-1', 'sitio_id'=>1, 'purok_id'=>null],
            ['affected_area_id'=>200, 'disaster_id'=>'EV-1', 'sitio_id'=>null, 'purok_id'=>20],
        ]);
        $db::table('responder_assignments')->insert([
            ['assignment_id'=>1, 'disaster_id'=>'EV-1', 'household_id'=>'HH-1', 'affected_area_id'=>null],
            ['assignment_id'=>2, 'disaster_id'=>'EV-1', 'household_id'=>null, 'affected_area_id'=>100],
            ['assignment_id'=>3, 'disaster_id'=>'EV-1', 'household_id'=>null, 'affected_area_id'=>200],
            ['assignment_id'=>4, 'disaster_id'=>'EV-1', 'household_id'=>null, 'affected_area_id'=>null],
        ]);
        $db::table('disaster_broadcasts')->insert([
            ['disaster_id'=>'EV-1', 'target_purok_id'=>10, 'target_area_id'=>null],
            ['disaster_id'=>'EV-1', 'target_purok_id'=>null, 'target_area_id'=>200],
            ['disaster_id'=>'EV-1', 'target_purok_id'=>null, 'target_area_id'=>null],
        ]);
        $query = app(\App\Queries\SituationReportQuery::class);
        $this->assertSame(['1','2','4'], $query->sitioOptions()->pluck('sitio_id')->all());
        $scoped = $query->summarySources('EV-1', '1');
        $this->assertSame(1, $scoped['household_total']);
        $this->assertSame(1, $scoped['household_reported']);
        $this->assertCount(1, $scoped['purok_rows']);
        $this->assertSame('10', (string) $scoped['purok_rows'][0]->purok_id);
        $this->assertSame(['1','2'], $scoped['assignments']->pluck('assignment_id')->sort()->values()->all());
        $this->assertSame(['1','2'], $scoped['assignment_outcomes']->pluck('assignment_id')->all());
        $this->assertCount(1, $scoped['broadcasts']);
        $empty = $query->summarySources('EV-1', '4');
        $this->assertSame(0, $empty['household_total']);
        $this->assertCount(0, $empty['purok_rows']);
        $this->assertCount(0, $empty['assignments']);
    }

    public function test_sitio_selection_is_forwarded_to_the_report_source_and_export(): void
    {
        $this->mock(SituationReportService::class, function ($mock): void {
            $mock->shouldReceive('eventSummary')->once()->with('EV-1', '12')->andReturn(response()->json(['data' => ['summary' => [
                'report' => [], 'event' => ['sitio_name' => 'Sitio A'], 'household' => ['total' => 5], 'actions_text' => '', 'included_sections' => [],
            ]]]));
        });
        $this->mock(ReportFileService::class, function ($mock): void {
            $mock->shouldReceive('generateSituationPdf')->once()->with(\Mockery::on(fn ($summary) => $summary['event']['sitio_name'] === 'Sitio A' && $summary['included_sections'] === ['II']))
                ->andReturn(['status' => 'success']);
        });
        $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'event_id' => 'EV-1', 'sitio_id' => '12', 'format' => 'pdf', 'included_sections' => ['II'],
        ]));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_sitio_filter_cannot_silently_export_an_unfiltered_saved_snapshot(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'sit_rep_id' => 9, 'sitio_id' => '12', 'format' => 'pdf', 'included_sections' => ['II'],
        ]));
    }

    public function test_report_period_end_cannot_precede_period_start(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'event_id' => 'EV-1', 'format' => 'pdf',
            'period_start' => '2026-10-16T18:00', 'period_end' => '2026-10-16T08:00',
        ]));
    }
}
