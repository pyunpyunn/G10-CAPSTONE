<?php

namespace Tests\Feature;

use App\Presenters\SituationReportDocument;
use App\Services\Web\ReportFileService;
use App\Support\SituationReportSections;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SituationV3Test extends TestCase
{
    private function summary(): array
    {
        return ['schema_version'=>'4.0','included_sections'=>array_keys(SituationReportSections::LABELS),
            'event'=>['event_id'=>'EV1','name'=>'Flood','type'=>'Flood'],
            'report'=>['report_number'=>'3','report_type'=>'final','prepared_by'=>'Records Officer','reviewed_by'=>'Operations Chief'],
            'operator_inputs'=>['overview_text'=>'Recorded flood findings','resources_text'=>'Available water stock']];
    }

    public function test_only_six_findings_render_in_all_three_formats(): void
    {
        Storage::fake('local'); config(['reports.disk'=>'local']); $s=$this->summary();
        $files=app(ReportFileService::class);
        $texts=[view('reports.situation',['summary'=>$s])->render()];
        $pdf=$files->generateSituationPdf($s);
        $this->assertStringStartsWith('%PDF',Storage::disk('local')->get('reports/'.$pdf['file_name']));
        foreach (['csv','xlsx'] as $format) {
            $file=$files->generateSituationSpreadsheet($s,$format); $bytes=Storage::disk('local')->get('reports/'.$file['file_name']);
            if ($format==='xlsx') {
                $path=tempnam(sys_get_temp_dir(),'findings-test');file_put_contents($path,$bytes);$zip=new \ZipArchive;$zip->open($path);
                $this->assertStringNotContainsString('Annex',$zip->getFromName('xl/workbook.xml'));
                $bytes='';for($i=0;$i<$zip->numFiles;$i++)$bytes.=$zip->getFromIndex($i);$zip->close();unlink($path);
            }
            $texts[]=$bytes;
        }
        foreach ($texts as $text) {
            foreach (SituationReportSections::LABELS as $label) $this->assertStringContainsString($label,$text);
            foreach (['Status of Displaced Population','Inside Evacuation Centers','Outside Evacuation Centers','Total Displaced Population','Casualties','Status of Lifelines','Cost of Humanitarian Assistance','Barangay Addendum','Annex ','Next Actions','Issues and Concerns','Received by'] as $removed) $this->assertStringNotContainsString($removed,$text);
            $this->assertStringContainsString('Recorded flood findings',$text);
            $this->assertStringContainsString('Not recorded',$text);
        }
    }

    public function test_recorded_zero_and_missing_damage_remain_distinct(): void
    {
        $s=$this->summary();$s['household']['details']=[['household'=>'H1','sitio'=>'Alaska','purok'=>'P1','damage'=>'minor_damage']];
        $r=app(SituationReportDocument::class)->data($s);
        $this->assertSame(1,$r['sections']['IV']['tables'][0]['rows'][0][1]);
        $this->assertSame(0,$r['sections']['IV']['tables'][0]['rows'][1][1]);
        unset($s['household']);
        $r=app(SituationReportDocument::class)->data($s);
        $this->assertNull($r['sections']['IV']['tables'][0]['rows'][0][1]);
    }

    public function test_order_and_selection_are_canonical_without_annexes(): void
    {
        $s=$this->summary();$s['included_sections']=['VII','III','II'];
        $r=app(SituationReportDocument::class)->data($s);
        $this->assertSame(['II','VII'],array_keys($r['sections']));
        $this->assertSame([],$r['annexes']);
    }

    public function test_historical_snapshots_project_only_supported_findings_without_being_rewritten(): void
    {
        $s=['schema_version'=>'3.0','included_sections'=>['IV','V','VII','IX'],'casualties'=>['injured'=>9]];
        $r=app(SituationReportDocument::class)->data($s);
        $this->assertSame(['IV','V','VII'],array_keys($r['sections']));
        $this->assertSame('4.0',$r['schema_version']);
        $this->assertSame('3.0',$s['schema_version']);
        $this->assertStringNotContainsString('Casualties',view('reports.situation',['summary'=>$s])->render());
    }
}
