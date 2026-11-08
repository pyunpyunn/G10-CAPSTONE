<?php

namespace Tests\Feature;

use App\Queries\SituationReportQuery;
use App\Services\Web\SituationHouseholdScope;
use App\Services\Web\SituationReportService;
use App\Services\Web\ReportGenerationService;
use App\Presenters\SituationReportDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SituationHouseholdExportTest extends TestCase
{
    private function locations(): void
    {
        $this->mock(SituationReportQuery::class, function ($mock) {
            $mock->shouldReceive('nextReportNumber')->andReturn('SITREP-20261108-0001');
            $mock->shouldReceive('sitioOptions')->andReturn(collect([
                (object) ['sitio_id' => 'S1', 'sitio_name' => 'Upper Sitio', 'puroks' => collect([(object) ['purok_id' => 'P1', 'purok_name' => 'Purok One'], (object) ['purok_id' => 'P2', 'purok_name' => 'Purok Two']])],
                (object) ['sitio_id' => 'S2', 'sitio_name' => 'Lower Sitio', 'puroks' => collect([(object) ['purok_id' => 'P3', 'purok_name' => 'Purok Three']])],
            ]));
        });
    }

    private function summary(): array
    {
        $household = ['household_id' => 'HH1', 'household' => 'HOUSEHOLD-ONE', 'sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P1', 'purok' => 'Purok One',
            'registered_members' => 3, 'affected_members' => 2, 'status' => 'unsafe', 'missing' => 1, 'injured' => 1, 'rescued' => 0,
            'immediate_needs' => 'Medical assistance', 'damage' => 'major_damage',
            'members' => [['name' => 'Member One', 'age' => 34, 'sex' => 'Female', 'status' => 'injured']]];
        return ['event' => ['name' => 'Flood', 'type' => 'Flood'], 'report' => ['report_number' => 'SR-TEST'],
            'included_sections' => ['II', 'III', 'VI'], 'household' => ['total' => 30, 'details' => [
                $household,
                array_replace($household, ['household' => 'OTHER-PUROK', 'purok_id' => 'P2']),
                array_replace($household, ['household' => 'OTHER-SITIO', 'sitio_id' => 'S2', 'purok_id' => 'P3']),
            ]]];
    }

    public function test_all_formats_export_selected_households_and_section_details(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $this->locations();
        $source = $this->summary();
        $this->mock(SituationReportService::class, function ($mock) use ($source) {
            $mock->shouldReceive('eventSummary')->with('EV1')->times(3)->andReturn(response()->json(['data' => ['summary' => $source]]));
        });
        foreach (['pdf', 'csv', 'xlsx'] as $format) {
            $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
                'report_type' => 'situation', 'event_id' => 'EV1', 'format' => $format, 'sitio_id' => 'S1', 'purok_id' => 'P1', 'include_households' => true,
            ]));
            $this->assertSame(200, $response->getStatusCode());
            $bytes = Storage::disk('local')->get('reports/'.$response->getData(true)['file_name']);
            if ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF', $bytes);
                $filtered = app(SituationHouseholdScope::class)->apply($source, ['sitio_id' => 'S1', 'purok_id' => 'P1']);
                $text = view('reports.situation', ['summary' => $filtered])->render();
            } elseif ($format === 'xlsx') {
                $path = tempnam(sys_get_temp_dir(), 'household-export'); file_put_contents($path, $bytes);
                $zip = new \ZipArchive; $zip->open($path); $text = '';
                for ($i=0; $i<$zip->numFiles; $i++) $text .= $zip->getFromIndex($i);
                $zip->close(); unlink($path);
            } else $text = $bytes;
            foreach (['HOUSEHOLD-ONE', 'Major Damage'] as $value) $this->assertStringContainsString($value, $text, $format);
            foreach (['OTHER-PUROK', 'OTHER-SITIO'] as $value) $this->assertStringNotContainsString($value, $text, $format);
        }
    }

    public function test_database_export_uses_current_summary_without_requiring_external_sync(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $this->locations();
        $source = $this->summary();
        $source['broadcast_status_by_purok'] = [
            ['sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P1', 'purok' => 'Purok One', 'status_key' => 'safe', 'label' => 'Safe', 'households' => 4],
        ];
        $this->mock(SituationReportService::class, function ($mock) use ($source) {
            $mock->shouldReceive('eventSummary')->with('EV1', null, true)->once()->andReturn(response()->json(['data' => ['summary' => $source]]));
            $mock->shouldNotReceive('show');
        });
        $this->mock(\App\Services\Web\SituationReportSync::class, function ($mock): void {
            $mock->shouldNotReceive('requireReady');
            $mock->shouldNotReceive('apply');
        });

        $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'event_id' => 'EV1', 'format' => 'csv', 'database_only' => true,
            'prepared_by' => 'Prepared officer', 'reviewed_by' => 'Review officer',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $csv = Storage::disk('local')->get('reports/'.$response->getData(true)['file_name']);
        $this->assertStringContainsString('Sitio,Purok,Safe', $csv);
        $this->assertStringContainsString('Purok One",4', $csv);
    }

    public function test_section_two_export_contains_only_selected_purok_and_three_requested_parts(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $this->locations();
        $source = $this->summary();
        $source['population_roster'] = [
            ['household_id' => 'HH1', 'household' => 'HOUSEHOLD-ONE', 'sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P1', 'purok' => 'Purok One', 'member_id' => 'M1', 'member_name' => 'Member One', 'status_key' => 'safe'],
            ['household_id' => 'HH1', 'household' => 'HOUSEHOLD-ONE', 'sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P1', 'purok' => 'Purok One', 'member_id' => 'M4', 'member_name' => 'Member Four', 'status_key' => 'unsafe'],
            ['household_id' => 'HH2', 'household' => 'OTHER-PUROK', 'sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P2', 'purok' => 'Purok Two', 'member_id' => 'M2', 'member_name' => 'Member Two', 'status_key' => 'injured'],
            ['household_id' => 'HH3', 'household' => 'OTHER-SITIO', 'sitio_id' => 'S2', 'sitio' => 'Lower Sitio', 'purok_id' => 'P3', 'purok' => 'Purok Three', 'member_id' => 'M3', 'member_name' => 'Member Three', 'status_key' => 'missing'],
        ];
        $source['broadcast_statuses'] = [['key' => 'safe', 'label' => 'Safe'], ['key' => 'unsafe', 'label' => 'Unsafe']];
        $source['broadcast_status_by_purok'] = [
            ['sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P1', 'purok' => 'Purok One', 'status_key' => 'safe', 'label' => 'Safe', 'households' => 1],
            ['sitio_id' => 'S1', 'sitio' => 'Upper Sitio', 'purok_id' => 'P2', 'purok' => 'Purok Two', 'status_key' => 'unsafe', 'label' => 'Unsafe', 'households' => 1],
        ];
        $this->mock(SituationReportService::class, function ($mock) use ($source) {
            $mock->shouldReceive('eventSummary')->with('EV1', null, true)->once()->andReturn(response()->json(['data' => ['summary' => $source]]));
        });
        $this->mock(\App\Services\Web\SituationReportSync::class, function ($mock): void {
            $mock->shouldNotReceive('requireReady');
            $mock->shouldNotReceive('apply');
        });

        $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'event_id' => 'EV1', 'format' => 'csv',
            'included_sections' => ['II'], 'database_only' => true, 'sitio_id' => 'S1', 'purok_id' => 'P1',
            'prepared_by' => 'Prepared officer', 'reviewed_by' => 'Review officer',
        ]));
        $csv = Storage::disk('local')->get('reports/'.$response->getData(true)['file_name']);

        $this->assertSame(200, $response->getStatusCode());
        foreach ([
            'Part 1 — Population by selected sitio and purok',
            'Part 2 — Affected areas by broadcast status (this event)',
            'Part 3 — Families and members grouped by sitio, purok and household',
            'Sitio,Purok,Safe,Unsafe',
            'Purok One",1,0',
            '"Sitio: Upper Sitio"',
            '"Purok: Purok One"',
            'Household,Member,"Latest member status"',
            'HOUSEHOLD-ONE,"Member Four",Unsafe',
            ',"Member One",Safe',
        ] as $value) {
            $this->assertStringContainsString($value, $csv);
        }
        $this->assertSame(1, substr_count($csv, 'HOUSEHOLD-ONE'));
        $this->assertStringNotContainsString('Status used in event broadcasts', $csv);
        $this->assertStringNotContainsString('Households reporting status', $csv);
        foreach (['OTHER-PUROK', 'OTHER-SITIO', 'Member Two', 'Member Three'] as $value) {
            $this->assertStringNotContainsString($value, $csv);
        }
        $this->assertStringNotContainsString('Damage Assessment', $csv);

        $scoped = app(SituationHouseholdScope::class)->apply($source, ['sitio_id' => 'S1', 'purok_id' => 'P1']);
        $document = app(SituationReportDocument::class)->data($scoped);
        $familyTable = $document['sections']['II']['tables'][2];
        $this->assertSame(['Household', 'Member', 'Latest member status'], $familyTable['headers']);
        $this->assertSame('Upper Sitio', $familyTable['groups'][0]['name']);
        $this->assertSame('Purok One', $familyTable['groups'][0]['puroks'][0]['name']);
        $this->assertSame('HOUSEHOLD-ONE', $familyTable['groups'][0]['puroks'][0]['households'][0]['household']);
        $this->assertTrue($familyTable['groups'][0]['puroks'][0]['households'][0]['members'][1]['last']);
        $html = view('reports.situation', ['summary' => $scoped])->render();
        $this->assertStringContainsString('Sitio: Upper Sitio', $html);
        $this->assertStringContainsString('Purok: Purok One', $html);
        $this->assertStringContainsString('rowspan="2"', $html);
        $this->assertStringContainsString('class="household-member household-member-last"', $html);
        $this->assertStringContainsString('None (not a correction)', $html);

        $file = app(\App\Services\Web\ReportFileService::class)->generateSituationSpreadsheet($scoped, 'xlsx');
        $path = tempnam(sys_get_temp_dir(), 'household-group-export');
        file_put_contents($path, Storage::disk('local')->get('reports/'.$file['file_name']));
        $zip = new \ZipArchive;
        $zip->open($path);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $summarySheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('Section II Families', $workbook);
        $this->assertStringNotContainsString('Latest member status', $summarySheet);
        $this->assertStringContainsString('None (not a correction)', $summarySheet);
        $this->assertStringContainsString('Latest member status', $sheet);
        $this->assertStringNotContainsString('None (not a correction)', $sheet);
        $this->assertStringContainsString('style="thick"', $styles);
        preg_match_all('/<mergeCell ref="([^"]+)"/', $sheet, $mergeMatches);
        $this->assertNotEmpty(array_filter($mergeMatches[1], fn ($range) => preg_match('/^A\d+:A\d+$/', $range) === 1));
        $this->assertNotEmpty(array_filter($mergeMatches[1], fn ($range) => preg_match('/^A\d+:C\d+$/', $range) === 1));
        $this->assertStringContainsString('.household-family-row, .household-family-table { page-break-inside: avoid;', file_get_contents(resource_path('views/reports/situation.blade.php')));
    }

    public function test_household_scope_preserves_event_totals_and_all_puroks_option(): void
    {
        $this->locations();
        $r = app(SituationHouseholdScope::class)->apply($this->summary(), ['sitio_id' => 'S1']);
        $this->assertCount(2, $r['household']['details']);
        $this->assertSame(30, $r['household']['total']);
        $this->assertCount(3, app(SituationHouseholdScope::class)->apply($this->summary(), [])['household']['details']);
    }

    public function test_purok_from_another_sitio_is_rejected(): void
    {
        $this->locations();
        $this->expectException(ValidationException::class);
        app(SituationHouseholdScope::class)->apply($this->summary(), ['sitio_id' => 'S1', 'purok_id' => 'P3']);
    }

    public function test_purok_can_be_selected_across_all_sitios(): void
    {
        $this->locations();
        $scoped = app(SituationHouseholdScope::class)->apply($this->summary(), ['purok_id' => 'P3']);

        $this->assertSame('All sitios · Purok Three', $scoped['household_scope']['label']);
        $this->assertSame(['OTHER-SITIO'], array_column($scoped['household']['details'], 'household'));
        $this->assertSame(['Lower Sitio · Purok Three'], array_map(
            fn ($area) => $area['sitio'].' · '.$area['purok'],
            $scoped['household_scope']['areas'],
        ));
    }

    public function test_household_tables_respect_checkbox_and_section_inclusion(): void
    {
        $s = $this->summary(); $s['include_households'] = false;
        $document = app(SituationReportDocument::class);
        $this->assertSame([], array_column($document->tables($document->data($s)), 0));
        $s['include_households'] = true; $s['included_sections'] = ['VI'];
        $r=$document->data($s);
        $this->assertSame(['IV'],array_keys($r['sections']));
        $this->assertStringContainsString('Major Damage',json_encode($r['sections']['IV']));
        $this->assertSame([],array_column($document->tables($r),0));
    }

    public function test_saved_exports_filter_only_snapshot_data_and_keep_snapshot_unchanged(): void
    {
        Storage::fake('local'); config(['reports.disk' => 'local']); $this->locations();
        $s = $this->summary();
        $this->mock(SituationReportService::class, function ($mock) use ($s) {
            $mock->shouldReceive('show')->with(7)->once()->andReturn(response()->json(['data' => ['report' => ['summary' => $s]]]));
            $mock->shouldNotReceive('eventSummary');
        });
        $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'sit_rep_id' => 7, 'format' => 'csv', 'sitio_id' => 'S1', 'purok_id' => 'P2',
        ]));
        $csv = Storage::disk('local')->get('reports/'.$response->getData(true)['file_name']);
        $this->assertStringContainsString('OTHER-PUROK', $csv);
        $this->assertStringNotContainsString('HOUSEHOLD-ONE', $csv);
        $this->assertCount(3, $s['household']['details']);
    }
    public function test_evacuation_households_and_members_follow_scope_without_reducing_site_capacity(): void
    {
        $this->locations();
        $s = $this->summary();
        $s['evacuation'] = ['households' => $s['household']['details'], 'members' => $s['household']['details'],
            'sites' => [['name' => 'School Shelter', 'capacity' => 20, 'occupancy' => 12, 'vacancy' => 8]]];
        $filtered = app(SituationHouseholdScope::class)->apply($s, ['sitio_id' => 'S1', 'purok_id' => 'P1']);
        $this->assertCount(1, $filtered['evacuation']['households']);
        $this->assertCount(1, $filtered['evacuation']['members']);
        $this->assertSame('HOUSEHOLD-ONE', $filtered['evacuation']['households'][0]['household']);
        $this->assertSame($s['evacuation']['sites'], $filtered['evacuation']['sites']);
        $this->assertCount(3, $s['evacuation']['members']);
    }

}
