<?php

namespace Tests\Feature;

use App\Services\Web\ReportFileService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SituationReportLayoutTest extends TestCase
{
    private function summary(array $sections): array
    {
        return [
            'included_sections' => $sections, 'barangay_profile' => ['name' => 'Barangay Sample'],
            'event' => ['name' => 'Flood response', 'type' => 'Flood', 'declared_at' => 'Oct 17, 2026 8:00 AM', 'finished_at' => 'Ongoing', 'severity' => 'High', 'sitio_name' => 'Sitio A', 'coverage' => 'Sitio A · 3 households'],
            'report' => ['report_number' => 'SITREP-TEST', 'prepared_by' => 'Duty officer', 'reviewed_by' => 'Reviewer', 'period_start' => 'Oct 17, 2026 8:00 AM', 'period_end' => 'Oct 17, 2026 6:00 PM'],
            'weather' => ['condition' => 'Cloudy', 'wind' => '2.30 km/h N', 'rainfall' => '0.00 mm', 'temperature' => '25.4 C'],
            'household' => ['total' => 3, 'reported' => 2, 'affected' => 1, 'safe_total' => 1, 'unsafe' => 1, 'evacuated' => 0, 'unchecked' => 1,
                'puroks' => [['sitio' => 'Sitio A', 'purok' => 'Purok 1', 'total' => 3, 'persons' => 9, 'affected' => 1, 'affected_persons' => 3, 'safe' => 1, 'evacuated' => 0, 'unsafe' => 1, 'unchecked' => 1]]],
            'casualties' => ['deaths' => null, 'missing' => 0, 'injured' => 1, 'rescued' => 2],
            'dispatch' => ['timeline' => [['title' => 'Dispatch assignment', 'actor' => 'Team A', 'time' => 'Oct 17, 2026 9:00 AM', 'detail' => 'Purok 1', 'status' => 'Completed']],
                'rows' => [['team' => 'Team A', 'area' => 'Purok 1', 'deployed_at' => 'Oct 17, 2026 9:00 AM', 'outcome_counts' => ['safe_count' => 1, 'evacuated_count' => 0, 'unsafe_count' => 1, 'injured_count' => 1, 'missing_count' => 0]]]],
            'actions_text' => "=HYPERLINK(\"unsafe\")\nSecond line", 'resources' => ['rows' => [['item' => 'Drinking water', 'quantity' => '100 packs', 'source' => 'Relief desk', 'status' => 'Needs Validation']]],
        ];
    }

    private function bytes(string $format, array $sections): string
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        $file = app(ReportFileService::class)->generateSituationSpreadsheet($this->summary($sections), $format);

        return Storage::disk('local')->get('reports/'.$file['file_name']);
    }

    private function zip(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'sitrep-test-');
        file_put_contents($path, $bytes);
        $zip = new \ZipArchive;
        $zip->open($path);
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($path);

        return $parts;
    }

    public function test_workbook_has_sample_summary_and_typed_filtered_detail_sheets(): void
    {
        $parts = $this->zip($this->bytes('xlsx', ['I', 'II', 'III', 'V', 'VI', 'VII', 'VIII']));
        $this->assertStringContainsString('name="Summary"', $parts['xl/workbook.xml']);
        foreach (['Population', 'Timeline', 'Team Outcomes', 'Resources'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $parts['xl/workbook.xml']);
        }
        $this->assertStringNotContainsString('Evacuation Centers', $parts['xl/workbook.xml']);
        $summary = $parts['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('SITUATION REPORT', $summary);
        $this->assertStringContainsString('<mergeCell ref="B6:E6"/>', $summary);
        $this->assertMatchesRegularExpression('/r="C13"[^>]*t="n"/', $summary);
        $this->assertStringContainsString('<f>IF(', $summary);
        $this->assertStringContainsString('fitToWidth="1"', $summary);
        $this->assertStringContainsString('FF14304F', $parts['xl/styles.xml']);
        $population = $parts['xl/worksheets/sheet2.xml'];
        $this->assertStringContainsString('state="frozen"', $population);
        $this->assertStringContainsString('<autoFilter', $population);
        $this->assertStringContainsString('Affected persons', $population);
        $this->assertStringContainsString('Sitio A', $population);
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK', $summary);
        foreach ($parts as $name => $xml) {
            $doc = new \DOMDocument;
            $this->assertTrue($doc->loadXML($xml), 'Valid OOXML part: '.$name);
        }
    }

    public function test_population_only_workbook_excludes_other_sections_and_sheets(): void
    {
        $parts = $this->zip($this->bytes('xlsx', ['II']));
        $workbook = $parts['xl/workbook.xml'];
        $summary = $parts['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('Population', $workbook);
        foreach (['Timeline', 'Team Outcomes', 'Resources'] as $name) {
            $this->assertStringNotContainsString('name="'.$name.'"', $workbook);
        }
        $this->assertStringNotContainsString('CASUALTIES', $summary);
        $this->assertStringNotContainsString('HYPERLINK', $summary);
    }

    public function test_csv_contains_rectangular_sectioned_tables_and_safe_text(): void
    {
        $csv = $this->bytes('csv', ['I', 'II', 'V', 'VII', 'VIII']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        $this->assertCount(1, array_unique(array_map('count', $rows)));
        $this->assertContains('Timeline', array_column($rows, 0));
        $this->assertContains('Team Outcomes', array_column($rows, 0));
        $this->assertStringContainsString('67%', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Not recorded', $csv);
    }

    public function test_pdf_uses_sample_header_and_only_selected_sections(): void
    {
        $html = view('reports.situation', ['summary' => $this->summary(['II'])])->render();
        $this->assertStringContainsString('banner-title', $html);
        $this->assertStringContainsString('Affected Population', $html);
        $this->assertStringContainsString('Sitio A', $html);
        $this->assertStringContainsString('Purok 1', $html);
        $this->assertStringNotContainsString('Casualties and Immediate Needs',$html);
        $this->assertStringNotContainsString('Evacuation Centers',$html);
        $this->assertStringNotContainsString('HYPERLINK',$html);
        $this->assertStringContainsString('Duty officer',$html);
    }
}
