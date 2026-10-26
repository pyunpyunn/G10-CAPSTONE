<?php

namespace App\Presenters;

/** Snapshot-only population tables: one registered household is the provisional family unit. */
class SituationReportPopulation
{
    public static function sum(array $values): ?int
    {
        return !$values || in_array(null,$values,true) ? null : array_sum($values);
    }

    public function data(array $s): array
    {
        $areas=[];
        foreach ($s['household']['puroks'] ?? [] as $p) {
            $key=$this->key($p);
            $areas[$key]=['sitio'=>$p['sitio'] ?? null,'purok'=>$p['purok'] ?? null,
                'families'=>$p['affected_families'] ?? $p['affected'] ?? null,'persons'=>$p['affected_persons'] ?? null];
        }
        $members=$s['evacuation']['members'] ?? [];
        $families=[];
        foreach ($s['evacuation']['households'] ?? [] as $h) {
            $id=$h['household_id'] ?? $h['household'] ?? null;
            if ($id === null) continue;
            $people=array_values(array_filter($members,fn ($m)=>(string)($m['household_id'] ?? $m['household'] ?? '') === (string)$id));
            $locations=array_values(array_unique(array_filter(array_column($people,'displacement_location'))));
            $unknown=(bool)array_filter($people,fn ($m)=>!isset($m['displacement_location']));
            $families[]=[...$h,'family_key'=>(string)$id,'persons'=>$h['evacuated_members'] ?? ($people ? count($people) : null),
                'locations'=>$h['displacement_location'] ?? (!$unknown && count($locations)===1 ? $locations[0] : null),
                'member_locations'=>$people];
        }
        // Preserve member-only historical snapshots without counting the same family twice.
        foreach ($members as $m) {
            $id=$m['household_id'] ?? $m['household'] ?? null;
            if ($id===null || array_filter($families,fn ($h)=>$h['family_key']===(string)$id)) continue;
            $people=array_values(array_filter($members,fn ($p)=>(string)($p['household_id'] ?? $p['household'] ?? '')===(string)$id));
            $locations=array_values(array_unique(array_filter(array_column($people,'displacement_location'))));
            $unknown=(bool)array_filter($people,fn ($p)=>!isset($p['displacement_location']));
            $families[]=[...$m,'family_key'=>(string)$id,'persons'=>count($people),
                'locations'=>!$unknown && count($locations)===1 ? $locations[0] : null,'member_locations'=>$people];
        }
        foreach ($families as $h) {
            $key=$this->key($h);
            $areas[$key] ??= ['sitio'=>$h['sitio'] ?? null,'purok'=>$h['purok'] ?? null,'families'=>null,'persons'=>null];
        }
        $displaced=['inside'=>[],'outside'=>[],'total'=>[]];
        foreach ($areas as $key=>$area) {
            $rows=array_values(array_filter($families,fn ($h)=>$this->key($h)===$key));
            foreach (array_keys($displaced) as $location) {
                $unknown=!$rows;
                $familyIds=[]; $persons=[];
                foreach ($rows as $h) {
                    if ($location==='total') { $familyIds[]=$h['family_key']; $persons[]=$h['persons']; continue; }
                    $people=$h['member_locations'];
                    if ($people) {
                        if (array_filter($people,fn ($m)=>!in_array($m['displacement_location'] ?? null,['inside','outside'],true))) $unknown=true;
                        $selected=array_filter($people,fn ($m)=>($m['displacement_location'] ?? null)===$location);
                        if ($selected) { $familyIds[]=$h['family_key']; $persons[]=count($selected); }
                    } elseif (!in_array($h['locations'],['inside','outside'],true)) $unknown=true;
                    elseif ($h['locations']===$location) { $familyIds[]=$h['family_key']; $persons[]=$h['persons']; }
                }
                $displaced[$location][]=['sitio'=>$area['sitio'],'purok'=>$area['purok'],
                    'families'=>$unknown ? null : count(array_unique($familyIds)),
                    'persons'=>$unknown ? null : ($persons ? self::sum($persons) : 0)];
            }
        }
        return ['affected'=>array_values($areas),'displaced'=>$displaced,
            'inside_demographics'=>$this->demographics(array_values(array_filter($members,fn ($m)=>($m['displacement_location'] ?? null)==='inside')))];
    }

    public function demographics(array $members): array
    {
        $count=function ($field,$test) use ($members) {
            if (!$members || array_filter($members,fn ($m)=>!isset($m[$field]))) return null;
            return count(array_filter($members,fn ($m)=>$test($m[$field])));
        };
        return [
            ['Male',$count('sex',fn ($v)=>in_array(strtolower($v),['male','m'],true))],
            ['Female',$count('sex',fn ($v)=>in_array(strtolower($v),['female','f'],true))],
            ['Infants (under 1)',$count('age',fn ($v)=>$v<1)],
            ['Children (1–17)',$count('age',fn ($v)=>$v>=1 && $v<18)],
            ['Elderly (60+)',$count('age',fn ($v)=>$v>=60)],
            ['PWD',$count('is_pwd',fn ($v)=>(bool)$v)],['Pregnant',$count('is_pregnant',fn ($v)=>(bool)$v)],
            ['Lactating',$count('is_lactating',fn ($v)=>(bool)$v)],
        ];
    }

    private function key(array $row): string
    {
        // Both names and IDs can be absent in older snapshots; never merge named areas with another location.
        return json_encode([$row['sitio'] ?? null,$row['purok'] ?? null]);
    }
}
