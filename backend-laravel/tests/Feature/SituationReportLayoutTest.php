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
            'schema_version'=>'4.0', 'included_sections' => $sections, 'barangay_profile' => ['name' => 'Barangay Sample'],
            'event' => ['name' => 'Flood response', 'type' => 'Flood', 'declared_at' => 'Oct 17, 2026 8:00 AM', 'finished_at' => 'Ongoing', 'severity' => 'High', 'sitio_name' => 'Sitio A', 'coverage' => 'Sitio A · 3 households'],
            'report' => ['report_number' => 'SITREP-TEST', 'prepared_by' => 'Duty officer', 'reviewed_by' => 'Reviewer', 'period_start' => 'Oct 17, 2026 8:00 AM', 'period_end' => 'Oct 17, 2026 6:00 PM'],
            'weather' => ['condition' => 'Cloudy', 'wind' => '2.30 km/h N', 'rainfall' => '0.00 mm', 'temperature' => '25.4 C'],
            'household' => ['total' => 3, 'reported' => 2, 'affected' => 1, 'safe_total' => 1, 'unsafe' => 1, 'evacuated' => 0, 'unchecked' => 1,
                'puroks' => [['sitio' => 'Sitio A', 'purok' => 'Purok 1', 'total' => 3, 'persons' => 9, 'affected' => 1, 'affected_persons' => 3, 'safe' => 1, 'evacuated' => 0, 'unsafe' => 1, 'unchecked' => 1]]],
            'casualties' => ['deaths' => null, 'missing' => 0, 'injured' => 1, 'rescued' => 2],
            'dispatch' => ['timeline' => [['title' => 'Dispatch assignment', 'actor' => 'Team A', 'time' => 'Oct 17, 2026 9:00 AM', 'detail' => 'Purok 1', 'status' => 'Completed']],
                'rows' => [['team' => 'Team A', 'area' => 'Purok 1', 'deployed_at' => 'Oct 17, 2026 9:00 AM', 'outcome_counts' => ['safe_count' => 1, 'evacuated_count' => 0, 'unsafe_count' => 1, 'injured_count' => 1, 'missing_count' => 0]]]],
            'operator_inputs'=>['overview_text'=>"=HYPERLINK(\"unsafe\")\nSecond line"], 'resources' => ['rows' => [['item' => 'Drinking water', 'quantity' => '100 packs', 'source' => 'Relief desk', 'status' => 'Needs Validation']]],
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

    public function test_workbook_has_shared_summary_annex_sheets_and_literal_recorded_text(): void
    {
        $parts=$this->zip($this->bytes('xlsx',array_keys(\App\Support\SituationReportSections::LABELS)));
        foreach (['Summary'] as $name) $this->assertStringContainsString('name="'.$name.'"',$parts['xl/workbook.xml']);
        $summary=$parts['xl/worksheets/sheet1.xml'];
        foreach (['BDRRMC Situation Report','Persons','Sitio A','Team A','Not recorded','=HYPERLINK','Page &amp;P of &amp;N'] as $text) $this->assertStringContainsString($text,$summary);
        $this->assertStringContainsString('fitToWidth="1"',$summary);
        $this->assertStringContainsString('<v>3</v>',$summary);
        $this->assertStringNotContainsString('<f>',$summary);
        foreach ($parts as $name=>$xml) {
            if (!str_ends_with($name,'.xml') && !str_ends_with($name,'.rels')) continue;
            $doc=new \DOMDocument; $this->assertTrue($doc->loadXML($xml),'Valid OOXML: '.$name);
        }
    }

    public function test_population_only_workbook_excludes_other_sections_and_annexes(): void
    {
        $parts=$this->zip($this->bytes('xlsx',['II']));
        $workbook=$parts['xl/workbook.xml']; $summary=$parts['xl/worksheets/sheet1.xml'];
        $this->assertStringNotContainsString('Annex',$workbook);
        foreach (['Annex B','Annex C','Annex D','Annex E','Annex F','Annex G','Annex H','Annex I'] as $name) $this->assertStringNotContainsString('name="'.$name.'"',$workbook);
        $this->assertStringContainsString('Status of Affected Areas and Population',$summary);
        $this->assertStringNotContainsString('HYPERLINK',$summary);
        $this->assertStringNotContainsString('Casualties',$summary);
    }

    public function test_csv_contains_rectangular_labeled_blocks_and_safe_text(): void
    {
        $csv=$this->bytes('csv',['I','II','V','VII']);
        $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
        $stream=fopen('php://memory','r+'); fwrite($stream,substr($csv,3)); rewind($stream);
        $rows=[]; while (($row=fgetcsv($stream,null,',','"','')) !== false) $rows[]=$row; fclose($stream);
        $this->assertCount(1,array_unique(array_map('count',$rows)));
        foreach (['V. Search, Rescue and Retrieval Operations','VI. Resources Status','Dispatch log'] as $text) $this->assertStringContainsString($text,$csv);
        $this->assertStringContainsString("'=HYPERLINK",$csv);
        $this->assertStringContainsString('Not recorded',$csv);
    }

    public function test_pdf_has_barangay_letterhead_and_only_selected_sections(): void
    {
        $html=view('reports.situation',['summary'=>$this->summary(['II'])])->render();
        foreach (['BDRRMC Situation Report','Barangay Mambaling','Status of Affected Areas and Population','Sitio A','Purok 1','Duty officer','Signature / date'] as $text) $this->assertStringContainsString($text,$html);
        foreach (['Casualties and Immediate Needs','Status of Displaced Population','HYPERLINK'] as $text) $this->assertStringNotContainsString($text,$html);
    }
}
