<?php

namespace Tests\Feature;

use App\Queries\SituationEvacuationQuery;
use App\Presenters\SituationReportDocument;
use App\Services\Web\ReportFileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SituationEvacuationTest extends TestCase
{
    private function evacuation(): array
    {
        Schema::create('households', fn ($t) => $t->string('household_code'));
        Schema::create('household_members', function ($t) { $t->integer('member_id'); $t->string('household_id'); $t->string('first_name'); $t->string('last_name'); $t->date('birth_date')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('member_statuses', function ($t) { $t->integer('status_id'); $t->string('status_key'); });
        Schema::create('member_disaster_statuses', function ($t) { $t->integer('member_status_id'); $t->string('disaster_id'); $t->string('household_id'); $t->integer('member_id'); $t->integer('status_id'); $t->string('evacuation_center_id')->nullable(); });
        Schema::create('evacuation_records', function ($t) { $t->integer('evacuation_id'); $t->string('event_id'); $t->string('household_id'); $t->string('center_id'); $t->integer('evacuated_count'); $t->timestamp('verified_at'); });
        Schema::create('evacuation_centers', function ($t) { $t->string('evacuation_center_id'); $t->string('name'); $t->string('current_event_id'); $t->string('status'); $t->integer('capacity')->nullable(); $t->integer('current_occupancy')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        DB::table('household_members')->insert([
            ['member_id'=>1,'household_id'=>'H1','first_name'=>'Ana','last_name'=>'Evacuee','birth_date'=>'2000-01-01','deleted_at'=>null],
            ['member_id'=>2,'household_id'=>'H1','first_name'=>'Other','last_name'=>'Event','birth_date'=>null,'deleted_at'=>null],
            ['member_id'=>3,'household_id'=>'H1','first_name'=>'Deleted','last_name'=>'Person','birth_date'=>null,'deleted_at'=>now()],
        ]);
        DB::table('member_statuses')->insert([['status_id'=>1,'status_key'=>'evacuated'],['status_id'=>2,'status_key'=>'safe']]);
        DB::table('member_disaster_statuses')->insert([
            ['member_status_id'=>1,'disaster_id'=>'EV1','household_id'=>'H1','member_id'=>1,'status_id'=>1,'evacuation_center_id'=>'OLD'],
            ['member_status_id'=>2,'disaster_id'=>'EV1','household_id'=>'H1','member_id'=>1,'status_id'=>1,'evacuation_center_id'=>'C1'],
            ['member_status_id'=>3,'disaster_id'=>'OTHER','household_id'=>'H1','member_id'=>2,'status_id'=>1,'evacuation_center_id'=>'OTHER'],
            ['member_status_id'=>4,'disaster_id'=>'EV1','household_id'=>'H1','member_id'=>3,'status_id'=>1,'evacuation_center_id'=>'C1'],
        ]);
        foreach ([['C1','School Shelter','EV1',10,8],['C2','Full Shelter','EV1',5,7],['C3','Unknown Capacity','EV1',null,null],['OLD','Old Site','OTHER',10,1],['OTHER','Other Event Site','OTHER',10,1]] as [$id,$name,$event,$capacity,$occupancy]) {
            DB::table('evacuation_centers')->insert(['evacuation_center_id'=>$id,'name'=>$name,'current_event_id'=>$event,'status'=>'Open','capacity'=>$capacity,'current_occupancy'=>$occupancy]);
        }
        foreach ([[1,'H2',1],[2,'H2',2],[3,'H3',3]] as [$id,$h,$count]) DB::table('evacuation_records')->insert(['evacuation_id'=>$id,'household_id'=>$h,'event_id'=>'EV1','center_id'=>'C1','evacuated_count'=>$count,'verified_at'=>'2026-10-18 10:00:00']);
        $base = \Mockery::mock();
        $rows = [];
        foreach ([['H1','unsafe'],['H2','evacuated'],['H3','safe']] as [$id,$status]) $rows[] = (object) ['household_id'=>$id,'household_code'=>$id,'sitio_id'=>'S1','sitio_name'=>'Upper Sitio','purok_id'=>'P1','purok_name'=>'Purok One','status_key'=>$status];
        $base->shouldReceive('get')->andReturn(collect($rows));
        return app(SituationEvacuationQuery::class)->summary($base, 'EV1');
    }

    public function test_event_members_households_and_recorded_site_vacancies_are_accurate(): void
    {
        $ev = $this->evacuation();
        $this->assertCount(1, $ev['members']);
        $this->assertSame('Ana Evacuee', $ev['members'][0]['name']);
        $this->assertSame('School Shelter', $ev['members'][0]['site']);
        $this->assertCount(2, $ev['households']);
        $this->assertSame(2, $ev['households'][1]['evacuated_members']);
        $sites = collect($ev['sites'])->keyBy('site_id');
        $this->assertCount(3, $sites);
        $this->assertSame(2, $sites['C1']['vacancy']);
        $this->assertSame(0, $sites['C2']['vacancy']);
        $this->assertNull($sites['C3']['vacancy']);
        $this->assertSame(1, $sites['C1']['assigned_members']);
    }

    public function test_new_section_exports_pdf_csv_and_excel_and_honors_inclusion(): void
    {
        Storage::fake('local'); config(['reports.disk'=>'local']);
        $summary = ['schema_version'=>'4.0', 'included_sections'=>['III','VI'], 'evacuation'=>$this->evacuation()];
        $document = app(SituationReportDocument::class);
        $r = $document->data($summary);
        $this->assertSame([], array_column($document->tables($r),0));
        $html = view('reports.situation',['summary'=>$summary])->render();
        foreach (['Status of Evacuation Centers','School Shelter','Vacancy'] as $text) $this->assertStringContainsString($text,$html);
        $this->assertStringNotContainsString('Status of Displaced Population',$html);
        $this->assertStringNotContainsString('Upper Sitio',$html);
        $files = app(ReportFileService::class);
        $pdf = $files->generateSituationPdf($summary);
        $this->assertStringStartsWith('%PDF',Storage::disk('local')->get('reports/'.$pdf['file_name']));
        foreach (['csv','xlsx'] as $format) {
            $result = $files->generateSituationSpreadsheet($summary,$format);
            $bytes = Storage::disk('local')->get('reports/'.$result['file_name']);
            if ($format==='xlsx') {
                $path=tempnam(sys_get_temp_dir(),'evac-test'); file_put_contents($path,$bytes); $zip=new \ZipArchive; $zip->open($path); $bytes='';
                for($i=0;$i<$zip->numFiles;$i++) $bytes.=$zip->getFromIndex($i); $zip->close(); unlink($path);
            }
            foreach (['Status of Evacuation Centers','School Shelter','Vacancy'] as $text) $this->assertStringContainsString($text,$bytes);
        }
        $summary['include_households']=false;
        $this->assertSame([],array_column($document->tables($document->data($summary)),0));
        $summary['included_sections']=['I'];
        $this->assertSame([], $document->tables($document->data($summary)));
    }
}
