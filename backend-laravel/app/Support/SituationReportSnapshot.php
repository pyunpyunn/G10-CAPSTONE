<?php

namespace App\Support;

/** Read-only projection; stored historical snapshots are never rewritten. */
class SituationReportSnapshot
{
    public static function forRendering(array $s): array
    {
        $version=$s['schema_version'] ?? '2.0';
        if ($version===SituationReportSections::VERSION) return $s;
        $mapping=$version==='3.0'
            ? ['I'=>['I'],'II'=>['II'],'III'=>['III','VI'],'V'=>['IV'],'VII'=>['V'],'IX'=>['VII']]
            : ['I'=>['I'],'II'=>['II'],'IV'=>['III','VI'],'V'=>['V'],'VI'=>['IV'],'VII'=>['VII']];
        $old=$s['included_sections'] ?? array_keys($mapping); $selected=[];
        foreach ($old as $key) array_push($selected,...($mapping[$key] ?? []));
        $s['source_schema_version']=$version;
        $s['schema_version']=SituationReportSections::VERSION;
        $s['included_sections']=array_values(array_intersect(array_keys(SituationReportSections::LABELS),$selected));
        $s['report']['report_type']=$s['report']['report_type'] ?? null;
        return $s;
    }
}
