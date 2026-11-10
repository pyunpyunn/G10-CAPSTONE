<?php

namespace Tests\Feature;

use App\Queries\DashboardImpactQuery;
use App\Queries\ImpactReportQuery;
use App\Queries\RescueCriteriaHistoryQuery;
use App\Queries\SitioPriorityQuery;
use App\Services\Web\ReportFileService;
use App\Services\Web\ReportGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class ImpactReportQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('disaster_events', function ($table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        DB::table('disaster_events')->insert([
            'event_id' => 'EVENT-1', 'name' => 'Flood', 'started_at' => '2026-10-01 08:00:00',
            'ended_at' => '2026-10-01 18:00:00', 'deleted_at' => null,
        ]);
        $priorities = \Mockery::mock(SitioPriorityQuery::class);
        $priorities->shouldReceive('ranked')->with('EVENT-1')->andReturn([[
            'rank' => 1, 'sitio' => 'Club', 'priority_score' => 55,
            'band' => ['key' => 'first', 'label' => 'Deploy first'],
            'households' => 10, 'impacted_households' => 5, 'special_needs_members' => 2,
            'unchecked_members' => 3, 'no_contact_households' => 0,
            'status_counts' => ['unsafe' => 5, 'safe' => 5],
            'contributions' => ['impact' => 25, 'special_needs' => 10, 'unreported' => 10, 'no_contact' => 10],
        ]]);
        $this->app->instance(SitioPriorityQuery::class, $priorities);
    }

    public function test_sitio_rankings_use_highest_recorded_status_and_priority_values(): void
    {
        $series = [
            ['sitio_id' => 1, 'sitio' => 'Alpha', 'is_demo' => false, 'points' => [
                $this->point('2026-10-01T08:00:00+08:00', ['unsafe' => 1, 'safe' => 1], 1, 2, 1, 2, 0, 1, 1, 2),
                $this->point('2026-10-01T09:00:00+08:00', ['unsafe' => 2], 2, 2, 0, 2, 1, 1, 0, 2),
            ]],
            ['sitio_id' => 2, 'sitio' => 'Beta', 'is_demo' => false, 'points' => [
                $this->point('2026-10-01T08:00:00+08:00', ['unsafe' => 3], 0, 3, 0, 3, 0, 1, 0, 3),
            ]],
            ['sitio_id' => 'demo-1', 'sitio' => 'Demo Sitio', 'is_demo' => true, 'points' => []],
        ];
        $history = \Mockery::mock(RescueCriteriaHistoryQuery::class);
        $history->shouldReceive('sitioSeries')->once()->andReturn($series);
        $this->app->instance(RescueCriteriaHistoryQuery::class, $history);

        $report = app(ImpactReportQuery::class)->sitioRanking('EVENT-1', ['status', 'priority']);

        $unsafe = collect($report['status_groups'])->firstWhere('status', 'unsafe')['entries'];
        $beta = collect($unsafe)->firstWhere('sitio', 'Beta');
        $this->assertSame(3, $beta['quantity']);
        $this->assertSame(1, $beta['rank']);
        $alphaPriority = collect($report['priority_rows'])->firstWhere('sitio', 'Alpha');
        $this->assertSame(45.0, $alphaPriority['peak']['score']);
        $this->assertSame(['Alpha', 'Beta'], $report['chart']['labels']);
        $this->assertNotContains('Demo Sitio', array_column($report['priority_rows'], 'sitio'));
        $this->assertSame(55, $report['ui_rows'][0]['priority_score']);
        $html = view('reports.impact', ['report' => $report])->render();
        $this->assertStringContainsString('Deploy first', $html);
        $this->assertStringContainsString('55%', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('Latest recorded score', $html);
        $this->assertStringContainsString('Highest recorded quantities by criterion', $html);
        $priorityHtml = substr($html, strpos($html, '<h2>Priority rankings'));
        $this->assertStringNotContainsString('2026-10-01T08:00:00+08:00', $priorityHtml);
        $path = tempnam(sys_get_temp_dir(), 'ranking-test-');
        try {
            app(\App\Services\Web\ImpactReportSpreadsheet::class)->write($path, $report);
            $reader = new Xlsx;
            $reader->setIncludeCharts(true);
            $book = $reader->load($path);
            $this->assertSame('Graphs', $book->getActiveSheet()->getTitle());
            $this->assertSame('Deploy first', $book->getSheetByName('Report')->getCell('D6')->getValue());
            $this->assertSame(0, $book->getSheetByName('Report')->getCell('I6')->getValue());
            $this->assertSame('55%', $book->getSheetByName('Report')->getCell('C6')->getFormattedValue());
            $this->assertTrue($book->getSheetByName('Report')->getStyle('A5')->getFont()->getBold());
            $plot = $book->getSheetByName('Graphs')->getChartCollection()[0]->getPlotAreaOrThrow()->getPlotGroup()[0];
            $this->assertSame(DataSeries::GROUPING_STACKED, $plot->getPlotGrouping());
            $this->assertEquals([50], $plot->getPlotValues()[0]->getDataValues());
            $book->disconnectWorksheets();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
            $this->assertStringStartsWith('%PDF-', $pdf->output());
            $this->assertStringNotContainsString('no font file found', $pdf->getDomPDF()->getCanvas()->get_cpdf()->messages);
        } finally {
            unlink($path);
        }
    }

    public function test_purok_timeline_uses_actual_status_keys_and_excludes_demo_data(): void
    {
        $history = \Mockery::mock(RescueCriteriaHistoryQuery::class);
        $history->shouldReceive('sitioSeries')->once()->andReturn([
            ['sitio_id' => 1, 'sitio' => 'Alpha', 'is_demo' => false, 'points' => [
                ['observed_at' => '2026-10-01T09:00:00+08:00', 'hours_since_alert' => 1.0, 'purok_impact' => 60.0],
                ['observed_at' => '2026-10-01T10:00:00+08:00', 'hours_since_alert' => 2.0, 'purok_impact' => 80.0],
            ]],
            ['sitio_id' => 2, 'sitio' => 'Beta', 'is_demo' => false, 'points' => [
                ['observed_at' => '2026-10-01T09:30:00+08:00', 'hours_since_alert' => 1.5, 'purok_impact' => 30.0],
            ]],
            ['sitio_id' => 'demo-1', 'sitio' => 'Demo Sitio', 'is_demo' => true, 'points' => []],
        ]);
        $this->app->instance(RescueCriteriaHistoryQuery::class, $history);

        $report = app(ImpactReportQuery::class)->purokImpact('EVENT-1');

        $this->assertSame('line', $report['chart']['kind']);
        $this->assertSame(100, $report['chart']['max_value']);
        $this->assertSame([1.0, 1.5, 2.0], $report['chart']['x_values']);
        $this->assertSame(['1.00h', '1.50h', '2.00h'], $report['chart']['labels']);
        $this->assertSame('Alpha', $report['chart']['series'][0]['label']);
        $this->assertSame([60.0, null, 80.0], $report['chart']['series'][0]['values']);
        $this->assertSame([null, 30.0, null], $report['chart']['series'][1]['values']);
        $this->assertSame([1.0, 2.0, 10.0], array_column($report['chart']['series'][0]['samples'], 'x'));
        $this->assertSame([60.0, 80.0, 80.0], array_column($report['chart']['series'][0]['samples'], 'y'));
        $this->assertSame(10.0, $report['chart']['x_max_value']);
        $this->assertNotContains('Demo Sitio', array_column($report['chart']['series'], 'label'));
    }

    public function test_pdf_household_charts_fit_all_sitios_on_one_page_with_slanted_labels(): void
    {
        $names = ['Club', 'Kawayan', 'Kolo', 'Larima', 'Lawis', 'Manga', 'Manggasan', 'Tromar', 'Tupas', 'Viking', 'Ybanez Compounds', 'Camansi', 'Paglaum', 'San'];
        $report = [
            'type' => 'ranking', 'title' => 'Sitio ranking', 'generated_at' => '2026-11-10 20:00:00',
            'status_groups' => [], 'priority_rows' => [], 'ranking_types' => ['status', 'priority'],
            'ui_rows' => array_map(fn ($name, $index) => [
                'rank' => $index + 1, 'sitio' => $name, 'priority_score' => 55,
                'band' => ['key' => 'high', 'label' => 'High'], 'households' => 10,
                'status_counts' => ['safe' => 5, 'unsafe' => 5], 'contributions' => ['impact' => 30, 'special_needs' => 25],
                'impacted_households' => 5, 'special_needs_members' => 2, 'unchecked_members' => 0, 'no_contact_households' => 0,
            ], $names, array_keys($names)),
        ];
        $html = view('reports.impact', ['report' => $report])->render();
        preg_match_all('/data:image\/svg\+xml;base64,([^\"]+)/', $html, $images);
        $this->assertCount(2, $images[1]);
        preg_match('/<style>(.*?)<\/style>/s', $html, $style);
        foreach (['status', 'priority'] as $index => $mode) {
            $svg = base64_decode($images[1][$index]);
            foreach ($names as $name) {
                $this->assertStringContainsString('>'.$name.'</text>', $svg);
            }
            $this->assertSame(14, substr_count($svg, 'rotate(-45'));
            $chart = view('reports.partials.impact-household-chart', ['report' => $report, 'mode' => $mode])->render();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<html><head>'.$style[0].'</head><body>'.$chart.'</body></html>')->setPaper('a4', 'landscape');
            $this->assertStringStartsWith('%PDF-', $pdf->output());
            $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        }
    }

    public function test_past_event_report_includes_database_totals(): void
    {
        $dashboard = \Mockery::mock(DashboardImpactQuery::class);
        $dashboard->shouldReceive('history')->with('yearly')->once()->andReturn([
            'periods' => [['label' => '2026', 'deaths' => 1, 'injured' => 2, 'missing' => 0, 'damaged_houses' => 1]],
            'event_count' => 1,
            'assessed_households' => 2,
            'totals' => ['deaths' => 1, 'injured' => 2, 'missing' => 0, 'damaged_houses' => 1],
        ]);
        $this->app->instance(DashboardImpactQuery::class, $dashboard);

        $report = app(ImpactReportQuery::class)->pastEvents('yearly');

        $this->assertSame(1, $report['event_count']);
        $this->assertSame(1, $report['totals']['deaths']);
        $this->assertSame(['2026'], $report['chart']['labels']);
        $this->assertSame('yearly', $report['period']);
    }

    public function test_report_generation_routes_sitio_ranking_to_the_database_exporter(): void
    {
        $impact = \Mockery::mock(ImpactReportQuery::class);
        $report = ['type' => 'ranking', 'title' => 'Sitio ranking'];
        $impact->shouldReceive('sitioRanking')->with('EVENT-1', ['status', 'priority'])->once()->andReturn($report);
        $this->app->instance(ImpactReportQuery::class, $impact);
        $files = \Mockery::mock(ReportFileService::class);
        $files->shouldReceive('generateImpactReport')->with('xlsx', $report)->once()->andReturn(['status' => 'success']);
        $this->app->instance(ReportFileService::class, $files);

        $response = app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'sitio-ranking', 'event_id' => 'EVENT-1',
            'ranking_types' => ['status', 'priority'], 'format' => 'xlsx',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $response->getData(true)['status']);
    }

    public function test_excel_graph_export_has_readable_report_and_separate_chart_sheet(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $report = [
            'type' => 'ranking',
            'title' => 'Sitio ranking',
            'event_name' => 'Flood',
            'generated_at' => '2026-11-09 00:45:00',
            'status_groups' => [[
                'status' => 'unsafe',
                'entries' => [['rank' => 1, 'sitio' => 'Alpha', 'quantity' => 5, 'current' => 2, 'peak_at' => '2026-11-09 00:00:00']],
            ]],
            'priority_rows' => [[
                'rank' => 1,
                'sitio' => 'Alpha',
                'current_score' => 25,
                'peak' => ['score' => 45, 'at' => '2026-11-09 00:00:00'],
                'metric_peaks' => ['impact_open' => ['quantity' => 3, 'at' => '2026-11-09 00:00:00']],
            ]],
            'chart' => [
                'kind' => 'ranking',
                'labels' => ['Alpha'],
                'status_series' => [['label' => 'unsafe', 'color' => '#dc2626', 'values' => [5]]],
                'priority_series' => [['label' => 'Highest priority score', 'color' => '#2563eb', 'values' => [45], 'labels' => ['Alpha']]],
            ],
        ];

        $result = app(ReportFileService::class)->generateImpactReport('xlsx', $report);
        $path = Storage::disk('local')->path('reports/'.$result['file_name']);
        $reader = new Xlsx;
        $reader->setIncludeCharts(true);
        $workbook = $reader->load($path);

        $this->assertSame(['Report', 'Graphs', 'Chart data'], $workbook->getSheetNames());
        $this->assertSame('Sitio ranking', $workbook->getSheetByName('Report')->getCell('A1')->getValue());
        $this->assertCount(2, $workbook->getSheetByName('Graphs')->getChartCollection());
        $plot = $workbook->getSheetByName('Graphs')->getChartCollection()[0]->getPlotAreaOrThrow()->getPlotGroup()[0];
        $this->assertEquals([5], $plot->getPlotValues()[0]->getDataValues());
        $this->assertSame(1, $plot->getPlotValues()[0]->getPointCount());
        $this->assertSame('veryHidden', $workbook->getSheetByName('Chart data')->getSheetState());
        $this->assertSame('Unsafe', $workbook->getSheetByName('Report')->getCell('C15')->getValue());
        $this->assertSame(3, $workbook->getSheetByName('Report')->getCell('E15')->getValue());
        $this->assertSame('09 Nov 2026, 00:00', $workbook->getSheetByName('Report')->getCell('F15')->getValue());
    }

    public function test_past_event_excel_has_a_compact_totals_table(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $report = [
            'type' => 'past-event-impact',
            'title' => 'Past disaster events — overall impact in Mambaling',
            'period' => 'monthly',
            'generated_at' => '2026-11-09 00:45:00',
            'event_count' => 1,
            'assessed_households' => 2,
            'totals' => ['deaths' => 1, 'injured' => 2, 'missing' => 0, 'damaged_houses' => 1],
            'periods' => [
                ['label' => 'Oct 2026', 'deaths' => 1, 'injured' => 2, 'missing' => 0, 'damaged_houses' => 1],
                ['label' => 'Nov 2026', 'deaths' => 0, 'injured' => 0, 'missing' => 1, 'damaged_houses' => 0],
            ],
            'chart' => [
                'kind' => 'line', 'axis_label' => 'Monthly period', 'value_label' => 'Recorded count',
                'labels' => ['Oct 2026', 'Nov 2026'],
                'series' => [['label' => 'Deaths', 'color' => '#dc2626', 'values' => [1, 0]]],
            ],
        ];

        $result = app(ReportFileService::class)->generateImpactReport('xlsx', $report);
        $path = Storage::disk('local')->path('reports/'.$result['file_name']);
        $reader = new Xlsx;
        $reader->setIncludeCharts(true);
        $workbook = $reader->load($path);

        $this->assertCount(1, $workbook->getSheetByName('Graphs')->getChartCollection());
        $sheet = $workbook->getSheetByName('Report');
        $this->assertSame('Impact', $sheet->getCell('A8')->getValue());
        $this->assertSame('Recorded total', $sheet->getCell('C8')->getValue());
        $this->assertSame('Deaths', $sheet->getCell('A9')->getValue());
        $this->assertSame(0, $sheet->getCell('C11')->getValue());
        $this->assertStringNotContainsString('Manila', $sheet->getCell('A3')->getValue());
        $html = view('reports.impact', ['report' => $report])->render();
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $pdf = app(ReportFileService::class)->generateImpactReport('pdf', $report);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get('reports/'.$pdf['file_name']));
    }

    public function test_impact_pdf_renders_a_real_svg_graph(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $report = [
            'type' => 'purok-impact', 'title' => 'Purok impact by sitio over time',
            'event_name' => 'Flood', 'generated_at' => '2026-11-09 00:45:00',
            'summaries' => [['sitio' => 'Alpha', 'latest' => 60, 'peak' => 60, 'peak_at' => '2026-11-09 00:00:00']],
            'chart' => [
                'kind' => 'line', 'axis_label' => 'Hours since the disaster broadcast',
                'value_label' => 'Weighted purok impact per sitio', 'max_value' => 100,
                'x_values' => [1.0, 2.0],
                'labels' => ['1.00h', '2.00h'],
                'series' => [
                    ['label' => 'Alpha', 'color' => '#2563eb', 'values' => [20, 60]],
                    ['label' => 'Club', 'color' => '#dc2626', 'values' => [null, 35], 'samples' => [['x' => 1.5, 'y' => 35]]],
                    ['label' => 'Empty', 'color' => '#059669', 'values' => [null, null], 'samples' => []],
                ],
            ],
        ];
        $html = view('reports.impact', ['report' => $report])->render();
        preg_match('/data:image\/svg\+xml;base64,([^\"]+)/', $html, $match);
        $svg = base64_decode($match[1]);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('100%', $svg);
        $this->assertStringContainsString('0.00h', $svg);
        $this->assertStringContainsString('2.00h', $svg);

        $result = app(ReportFileService::class)->generateImpactReport('pdf', $report);
        $contents = Storage::disk('local')->get('reports/'.$result['file_name']);
        $this->assertStringStartsWith('%PDF-', $contents);

        $result = app(ReportFileService::class)->generateImpactReport('xlsx', $report);
        $path = Storage::disk('local')->path('reports/'.$result['file_name']);
        $reader = new Xlsx;
        $reader->setIncludeCharts(true);
        $workbook = $reader->load($path);
        $chart = $workbook->getSheetByName('Graphs')->getChartCollection()[0];
        $sheet = $workbook->getSheetByName('Report');
        $this->assertSame('Sitio', $sheet->getCell('B6')->getValue());
        $this->assertSame('Alpha', $sheet->getCell('B7')->getValue());
        $this->assertSame('60.0%', $sheet->getCell('C7')->getFormattedValue());
        $this->assertSame('09 Nov 2026, 00:00', $sheet->getCell('E7')->getValue());
        $this->assertStringNotContainsString('Manila', $sheet->getCell('A3')->getValue());
        $this->assertSame(DataSeries::TYPE_SCATTERCHART, $chart->getPlotAreaOrThrow()->getPlotGroup()[0]->getPlotType());
        $this->assertSame('0', $chart->getChartAxisX()->getAxisOptionsProperty('minimum'));
        $this->assertSame('2', $chart->getChartAxisX()->getAxisOptionsProperty('maximum'));
        $this->assertSame('0', $chart->getChartAxisY()->getAxisOptionsProperty('minimum'));
        $this->assertSame('100', $chart->getChartAxisY()->getAxisOptionsProperty('maximum'));
        $this->assertCount(2, $chart->getPlotAreaOrThrow()->getPlotGroup()[0]->getPlotValues());

        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($path));
        try {
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($archive->getFromName('xl/charts/chart1.xml')));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
            $this->assertSame(0, $xpath->query('//c:scatterChart/c:grouping')->length);
            $this->assertSame('smoothMarker', $xpath->evaluate('string(//c:scatterChart/c:scatterStyle/@val)'));
            $this->assertSame(0, $xpath->query('//c:plotArea/c:catAx')->length);
            $this->assertSame(2, $xpath->query('//c:plotArea/c:valAx')->length);
            $this->assertSame(0, $xpath->query('//c:valAx/c:auto | //c:valAx/c:lblAlgn | //c:valAx/c:lblOffset')->length);
            $this->assertSame(['scatterStyle', 'varyColors', 'ser', 'ser', 'axId', 'axId'],
                array_map(fn ($node) => $node->localName, iterator_to_array($xpath->query('//c:scatterChart/*'))));
            $this->assertSame('1.5', $xpath->evaluate('string((//c:scatterChart/c:ser)[2]/c:xVal/c:numRef/c:numCache/c:pt/c:v)'));
            $this->assertSame('35', $xpath->evaluate('string((//c:scatterChart/c:ser)[2]/c:yVal/c:numRef/c:numCache/c:pt/c:v)'));
            $this->assertSame(2, $xpath->query('//c:scatterChart/c:ser/c:smooth[@val="1"]')->length);
            $this->assertSame(['orientation', 'max', 'min'],
                array_map(fn ($node) => $node->localName, iterator_to_array($xpath->query('(//c:valAx/c:scaling)[1]/*'))));
        } finally {
            $archive->close();
        }
    }

    private function point(string $at, array $statuses, int $impact, int $households, int $special, int $vulnerable, int $noContact, int $noContactTotal, int $unreported, int $members): array
    {
        return [
            'observed_at' => $at,
            'counts' => [
                'status_counts' => $statuses, 'impact_open' => $impact, 'household_total' => $households,
                'special_open' => $special, 'vulnerable_total' => $vulnerable, 'no_contact_open' => $noContact,
                'no_contact_total' => $noContactTotal, 'unreported_open' => $unreported, 'member_total' => $members,
            ],
        ];
    }
}
