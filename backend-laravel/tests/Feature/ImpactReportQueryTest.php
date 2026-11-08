<?php

namespace Tests\Feature;

use App\Queries\DashboardImpactQuery;
use App\Queries\ImpactReportQuery;
use App\Queries\RescueCriteriaHistoryQuery;
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
        $this->assertNotContains('Demo Sitio', array_column($report['chart']['series'], 'label'));
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
        $this->assertSame('veryHidden', $workbook->getSheetByName('Chart data')->getSheetState());
        $this->assertSame('Unsafe: 3 (2026-11-09 00:00:00)', $workbook->getSheetByName('Report')->getCell('B11')->getValue());
    }

    public function test_past_event_excel_graph_has_non_overlapping_summary_cards(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $report = [
            'type' => 'past-event-impact',
            'title' => 'Past disaster events — overall impact in Mambaling',
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
        $this->assertContains('A8:D8', $workbook->getSheetByName('Report')->getMergeCells());
        $this->assertContains('E8:H8', $workbook->getSheetByName('Report')->getMergeCells());
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
                'series' => [['label' => 'Alpha', 'color' => '#2563eb', 'values' => [20, 60]]],
            ],
        ];
        $html = view('reports.impact', ['report' => $report])->render();
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('100%', $html);
        $this->assertStringContainsString('0.00h', $html);
        $this->assertStringContainsString('2.00h', $html);

        $result = app(ReportFileService::class)->generateImpactReport('pdf', $report);
        $contents = Storage::disk('local')->get('reports/'.$result['file_name']);
        $this->assertStringStartsWith('%PDF-', $contents);

        $result = app(ReportFileService::class)->generateImpactReport('xlsx', $report);
        $path = Storage::disk('local')->path('reports/'.$result['file_name']);
        $reader = new Xlsx;
        $reader->setIncludeCharts(true);
        $workbook = $reader->load($path);
        $chart = $workbook->getSheetByName('Graphs')->getChartCollection()[0];
        $this->assertSame(DataSeries::TYPE_SCATTERCHART, $chart->getPlotAreaOrThrow()->getPlotGroup()[0]->getPlotType());
        $this->assertSame('0', $chart->getChartAxisX()->getAxisOptionsProperty('minimum'));
        $this->assertSame('2', $chart->getChartAxisX()->getAxisOptionsProperty('maximum'));
        $this->assertSame('0', $chart->getChartAxisY()->getAxisOptionsProperty('minimum'));
        $this->assertSame('100', $chart->getChartAxisY()->getAxisOptionsProperty('maximum'));
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
