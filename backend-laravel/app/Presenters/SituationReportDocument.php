<?php

namespace App\Presenters;

use App\Support\SituationReportSections as Sections;
use App\Support\SituationReportSnapshot;

class SituationReportDocument extends SituationReportData
{
    public const BLANK_CELL = "\0__SITREP_BLANK__";

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
        $r['supersedes']=$s['report']['supersedes']['report_number'] ?? 'None (not a correction)';
        $asOf=$r['period_end'] instanceof \DateTimeInterface ? $r['period_end']->format('d F Y, g:i A') : ($r['period_end'] ?: 'Not recorded');
        $r['title']='BDRRMC Situation Report No. '.$r['report_number'].' on the '.$r['disaster_name'].' as of '.$asOf;
        $population=$s['population_tables'] ?? app(SituationReportPopulation::class)->data($s);
        $r['population_tables']=$population;
        $table=fn ($name,$headings,$rows)=>['name'=>$name,'headers'=>$headings,'rows'=>$rows ?: [array_fill(0,count($headings),null)]];
        $sectionIITables = function () use ($s, $table): array {
            $roster = collect($s['population_roster'] ?? []);
            $populationRows = $roster->groupBy(fn ($member) => ($member['sitio_id'] ?? '').':'.($member['purok_id'] ?? ''))->map(function ($members): array {
                $first = $members->first();
                return [
                    $first['sitio'] ?? null, $first['purok'] ?? null,
                    $members->pluck('household_id')->filter()->unique()->count(),
                    $members->pluck('member_id')->filter()->unique()->count(),
                ];
            })->values()->all();
            if ($populationRows === []) {
                $populationRows = array_map(fn (array $area): array => [
                    $area['sitio'] ?? null, $area['purok'] ?? null,
                    $area['total'] ?? null, $area['persons'] ?? null,
                ], $s['household']['puroks'] ?? []);
            }
            $statusRows = collect($s['broadcast_status_by_purok'] ?? []);
            $statuses = collect($s['broadcast_statuses'] ?? [])
                ->filter(fn ($status) => is_array($status) && !empty($status['key']) && !empty($status['label']))
                ->unique('key')->values();
            if ($statuses->isEmpty()) {
                $statuses = $statusRows->map(fn ($row) => [
                    'key' => $row['status_key'] ?? null, 'label' => $row['label'] ?? null,
                ])->filter(fn ($status) => $status['key'] && $status['label'])->unique('key')->values();
            }
            $areas = collect($s['household_scope']['areas'] ?? [])
                ->merge($s['household']['puroks'] ?? [])
                ->merge($roster->map(fn ($row) => [
                    'sitio_id' => $row['sitio_id'] ?? null, 'sitio' => $row['sitio'] ?? null,
                    'purok_id' => $row['purok_id'] ?? null, 'purok' => $row['purok'] ?? null,
                ]))
                ->merge($statusRows->map(fn ($row) => [
                    'sitio_id' => $row['sitio_id'] ?? null, 'sitio' => $row['sitio'] ?? null,
                    'purok_id' => $row['purok_id'] ?? null, 'purok' => $row['purok'] ?? null,
                ]))
                ->filter(fn ($area) => !empty($area['sitio']) && !empty($area['purok']))
                ->unique(fn ($area) => ($area['sitio_id'] ?? $area['sitio']).':'.($area['purok_id'] ?? $area['purok']))
                ->values();
            $affectedRows = $areas->map(function ($area) use ($statusRows, $statuses): array {
                $counts = $statusRows->filter(fn ($row) =>
                    (string) ($row['sitio_id'] ?? '') === (string) ($area['sitio_id'] ?? '')
                    && (string) ($row['purok_id'] ?? '') === (string) ($area['purok_id'] ?? ''))
                    ->keyBy('status_key');
                return [
                    $area['sitio'] ?? null, $area['purok'] ?? null,
                    ...$statuses->map(fn ($status) => (int) ($counts->get($status['key'])['households'] ?? 0))->all(),
                ];
            })->all();
            $affectedHeaders = ['Sitio', 'Purok', ...$statuses->pluck('label')->all()];
            $familyGroups = ($s['include_households'] ?? true)
                ? $roster->sortBy([
                    ['sitio', 'asc'], ['purok', 'asc'], ['household', 'asc'], ['member_name', 'asc'],
                ])->groupBy(fn ($row) => $row['sitio_id'] ?? $row['sitio'] ?? 'unknown-sitio')
                    ->map(function ($sitioRows): array {
                        $sitio = $sitioRows->first();
                        $puroks = $sitioRows->groupBy(fn ($row) => $row['purok_id'] ?? $row['purok'] ?? 'unknown-purok')
                            ->map(function ($purokRows): array {
                                $purok = $purokRows->first();
                                $households = $purokRows->groupBy(fn ($row) => $row['household_id'] ?? $row['household'] ?? 'unknown-household')
                                    ->map(function ($members): array {
                                        $household = $members->first();
                                        $memberRows = $members->values();
                                        return [
                                            'household' => $household['household'] ?? $household['household_id'] ?? null,
                                            'members' => $memberRows->map(fn (array $row, int $index): array => [
                                                'name' => ($row['member_name'] ?? '') !== '' ? $row['member_name'] : 'No registered member',
                                                'status' => !empty($row['status_key']) ? ucwords(str_replace('_', ' ', $row['status_key'])) : 'No report',
                                                'household_first' => $index === 0,
                                                'last' => $index === $memberRows->count() - 1,
                                            ])->all(),
                                        ];
                                    })->values()->all();
                                return ['name' => $purok['purok'] ?? null, 'households' => $households];
                            })->values()->all();
                        return ['name' => $sitio['sitio'] ?? null, 'puroks' => $puroks];
                    })->values()->all()
                : [];
            $familyRows = collect($familyGroups)->flatMap(fn ($sitio) => collect([
                ['Sitio: '.$sitio['name'], self::BLANK_CELL, self::BLANK_CELL],
                ...collect($sitio['puroks'])->flatMap(fn ($purok) => [
                    ['Purok: '.$purok['name'], self::BLANK_CELL, self::BLANK_CELL],
                    ...collect($purok['households'])->flatMap(fn ($household) => collect($household['members'])
                        ->map(fn ($member) => [
                            $member['household_first'] ? $household['household'] : self::BLANK_CELL,
                            $member['name'],
                            $member['status'],
                        ]))->all(),
                ])->all(),
            ]))->all();
            if (!($s['include_households'] ?? true)) {
                $familyRows = [['Not included by export selection', null, null]];
            }
            $familyTable = ($s['include_households'] ?? true)
                ? $table('Part 3 — Families and members grouped by sitio, purok and household', ['Household', 'Member', 'Latest member status'], $familyRows)
                : $table('Part 3 — Families and members grouped by sitio, purok and household', ['Household', 'Member', 'Latest member status'], [['Not included by export selection', null, null]]);
            $familyTable['groups'] = $familyGroups;

            return [
                $table('Part 1 — Population by selected sitio and purok', ['Sitio','Purok','Households','Members'], $populationRows),
                $table('Part 2 — Affected areas by broadcast status (this event)', $affectedHeaders, $affectedRows),
                $familyTable,
            ];
        };
        $damageCategories=['minor_damage'=>'Minor damage','major_damage'=>'Major damage','completely_destroyed'=>'Completely destroyed'];
        $damageRows=array_map(fn ($code,$label)=>[$label,array_filter($r['household_details'] ?? [],fn ($h)=>in_array($h['damage'] ?? null,['minor_damage','major_damage','completely_destroyed','no_damage'],true)) ? count(array_filter($r['household_details'],fn ($h)=>($h['damage'] ?? null)===$code)) : null],array_keys($damageCategories),array_values($damageCategories));
        $r['sections']=[];
        foreach ($r['included_sections'] as $key) {
            $tables=match ($key) {
                'I'=>[$table('', ['Finding','Recorded value'],[
                    ['Event',$r['disaster_name']],['Hazard',$r['disaster_type']],['Alert level',$r['alert_level']],
                    ['Area coverage',$s['event']['coverage'] ?? null],['Weather',$r['weather']['condition']],['Advisory',$r['weather']['advisory']],
                ])],
                'II'=>$sectionIITables(),
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
