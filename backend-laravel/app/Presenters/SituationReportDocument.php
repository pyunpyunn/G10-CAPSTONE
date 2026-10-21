<?php

namespace App\Presenters;

use App\Support\SituationReportSections as Sections;
use App\Support\SituationReportSnapshot;

class SituationReportDocument extends SituationReportData
{
    public function data(array $s): array
    {
        $s=SituationReportSnapshot::forRendering($s);
        $r=parent::data($s);
        $r['schema_version']=Sections::VERSION;
        $r['included_sections']=array_values(array_intersect(array_keys(Sections::LABELS),$s['included_sections'] ?? array_keys(Sections::LABELS)));
        $r['letterhead']=Sections::LETTERHEAD; $r['notice']=Sections::NOTICE;
        $r['approved_by']=$s['report']['approved_by'] ?? null;
        $r['report_type']=$s['report']['report_type'] ?? null;
        $r['event_id']=$s['event']['event_id'] ?? null;
        $r['supersedes']=$s['report']['supersedes']['report_number'] ?? null;
        $asOf=$r['period_end'] instanceof \DateTimeInterface ? $r['period_end']->format('d F Y, g:i A') : ($r['period_end'] ?: 'Not recorded');
        $r['title']='BDRRMC Situation Report No. '.$r['report_number'].' on the '.$r['disaster_name'].' as of '.$asOf;
        $population=$s['population_tables'] ?? app(SituationReportPopulation::class)->data($s);
        $r['population_tables']=$population;
        $table=fn ($name,$headings,$rows)=>['name'=>$name,'headers'=>$headings,'rows'=>$rows ?: [array_fill(0,count($headings),null)]];
        $areaRows=fn ($rows)=>array_map(fn ($p)=>[$p['sitio'],$p['purok'],$p['families'],$p['persons']],$rows);
        $damageCategories=['minor_damage'=>'Minor damage','major_damage'=>'Major damage','completely_destroyed'=>'Completely destroyed'];
        $damageRows=array_map(fn ($code,$label)=>[$label,array_filter($r['household_details'] ?? [],fn ($h)=>in_array($h['damage'] ?? null,['minor_damage','major_damage','completely_destroyed','no_damage'],true)) ? count(array_filter($r['household_details'],fn ($h)=>($h['damage'] ?? null)===$code)) : null],array_keys($damageCategories),array_values($damageCategories));
        $r['sections']=[];
        foreach ($r['included_sections'] as $key) {
            $tables=match ($key) {
                'I'=>[$table('', ['Finding','Recorded value'],[
                    ['Event',$r['disaster_name']],['Hazard',$r['disaster_type']],['Alert level',$r['alert_level']],
                    ['Area coverage',$s['event']['coverage'] ?? null],['Weather',$r['weather']['condition']],['Advisory',$r['weather']['advisory']],
                ])],
                'II'=>[$table('Affected families and persons',['Sitio','Purok','Families','Persons'],$areaRows($population['affected']))],
                'IV'=>[$table('Recorded damage assessments',['Recorded category','Recorded assessments'],$damageRows)],
                'V'=>[$table('Dispatch activities',['Team','Assigned area','Deployed at','Results'],array_map(fn ($x)=>[$x['team'],$x['area'],$x['deployed_at'],$x['outcomes'] ?? null],$s['dispatch']['rows'] ?? [])),
                    $table('Dispatch log',['Date/time','Team / responder','Assigned area','Status'],array_values(array_map(fn ($x)=>[$x['logged_at'],$x['actor'],$x['detail'],$x['status']],array_filter($r['timeline'],fn ($x)=>$x['category']==='Dispatch assignment'))))],
                'VI'=>[$table('Evacuation centers',['Center','Status','Capacity (persons)','Occupancy (persons)','Vacancy (persons)'],array_map(fn ($x)=>[$x['name'],$x['status'],$x['capacity'],$x['occupancy'],$x['vacancy']],$r['evacuation']['sites'] ?? []))],
                'VII'=>[$table('Resources',['Resource','Available quantity','Unit','Status'],array_map(fn ($x)=>[$x['item'] ?? null,$x['quantity'] ?? null,$x['unit'] ?? null,$x['status'] ?? null],$s['inventory']['rows'] ?? [])),
                    $table('Resource requests',['Item','Quantity','Source','Status'],array_map(fn ($x)=>[$x['item'],$x['quantity'],$x['source'],$x['status']],$r['resources']))],
            };
            $field=['I'=>'overview_text','IV'=>'damage_text','V'=>'response_text','VII'=>'resources_text'][$key] ?? null;
            if ($key==='IV' && $r['include_households']) $tables[]=$table('Recorded house assessments',['Family reference','Sitio','Purok','Damage'],
                array_values(array_map(fn ($h)=>[$h['household'],$h['sitio'],$h['purok'],ucwords(str_replace('_',' ',$h['damage']))],array_filter($r['household_details'],fn ($h)=>!empty($h['damage']) && !in_array($h['damage'],['no_damage','not_yet_assessed'],true)))));
            $r['sections'][$key]=['number'=>Sections::NUMBERS[$key],'title'=>Sections::LABELS[$key],'group'=>null,'tables'=>$tables,'text'=>$field ? ($s['operator_inputs'][$field] ?? null) : null];
        }
        $r['annexes']=[];
        return $r;
    }

    public function tables(array $r): array { return []; }
}
