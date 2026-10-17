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
            foreach (['HOUSEHOLD-ONE', 'Member One', 'Medical assistance', 'Major Damage'] as $value) $this->assertStringContainsString($value, $text, $format);
            foreach (['OTHER-PUROK', 'OTHER-SITIO'] as $value) $this->assertStringNotContainsString($value, $text, $format);
        }
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

    public function test_household_tables_respect_checkbox_and_section_inclusion(): void
    {
        $s = $this->summary(); $s['include_households'] = false;
        $document = app(SituationReportDocument::class);
        $this->assertSame(['Population'], array_column($document->tables($document->data($s)), 0));
        $s['include_households'] = true; $s['included_sections'] = ['VI'];
        $this->assertSame(['Household Damage'], array_column($document->tables($document->data($s)), 0));
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
}
