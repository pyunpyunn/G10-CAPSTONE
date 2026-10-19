<?php

namespace Tests\Feature;

use App\Presenters\SituationReportDocument;
use App\Presenters\SituationReportPopulation;
use App\Support\SituationReportSections;
use Tests\TestCase;

class SituationNamingTest extends TestCase
{
    public function test_displacement_deduplicates_total_families_and_limits_demographics_to_inside_ecs(): void
    {
        $members=[
            ['household_id'=>'H1','sitio'=>'Alaska','purok'=>'P1','displacement_location'=>'inside','age'=>8,'sex'=>'Female','is_pwd'=>false,'is_pregnant'=>false,'is_lactating'=>false],
            ['household_id'=>'H1','sitio'=>'Alaska','purok'=>'P1','displacement_location'=>'outside','age'=>65,'sex'=>'Male','is_pwd'=>true],
        ];
        $s=['evacuation'=>['members'=>$members,'households'=>[]]];
        $data=app(SituationReportPopulation::class)->data($s);
        foreach (['inside','outside','total'] as $location) $this->assertSame(1,$data['displaced'][$location][0]['families']);
        $this->assertSame(1,$data['displaced']['inside'][0]['persons']);
        $this->assertSame(1,$data['displaced']['outside'][0]['persons']);
        $this->assertSame(2,$data['displaced']['total'][0]['persons']);
        $demographics=array_column($data['inside_demographics'],1,0);
        $this->assertSame(1,$demographics['Female']);
        $this->assertSame(0,$demographics['Male']);
        $this->assertSame(0,$demographics['Elderly (60+)']);
        $this->assertSame(0,$demographics['PWD']);
    }

    public function test_unknown_displacement_and_costs_are_not_assigned_zero(): void
    {
        $s=['schema_version'=>'4.0','included_sections'=>['III','VII'],
            'evacuation'=>['households'=>[['household_id'=>'H1','sitio'=>'Alaska','purok'=>'P1','evacuated_members'=>null]],'members'=>[],'sites'=>[]]];
        $data=app(SituationReportDocument::class)->data($s);
        foreach (['inside','outside'] as $location) {
            $this->assertNull($data['population_tables']['displaced'][$location][0]['families']);
            $this->assertNull($data['population_tables']['displaced'][$location][0]['persons']);
        }
        $this->assertSame(1,$data['population_tables']['displaced']['total'][0]['families']);
        $this->assertNull($data['population_tables']['displaced']['total'][0]['persons']);
        $this->assertNull($data['sections']['VII']['tables'][0]['rows'][0][1]);
    }

    public function test_title_and_exact_six_section_names(): void
    {
        $s=['schema_version'=>'4.0','included_sections'=>array_keys(SituationReportSections::LABELS),
            'event'=>['name'=>'Flood'],'report'=>['report_number'=>'2','period_end'=>'2026-10-19 12:00:00']];
        $r=app(SituationReportDocument::class)->data($s);
        $this->assertSame('BDRRMC Situation Report No. 2 on the Flood as of 19 October 2026, 12:00 PM',$r['title']);
        $this->assertSame(['I','II','IV','V','VI','VII'],array_keys($r['sections']));
        $this->assertSame([], $r['annexes']);
        foreach ($r['sections'] as $section) $this->assertNull($section['group']);
    }
}
