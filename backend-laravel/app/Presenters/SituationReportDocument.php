<?php

namespace App\Presenters;

use App\Support\SituationReportSections;
use Carbon\Carbon;

/** Shared data contract for the sample-based PDF, workbook and sectioned CSV. */
class SituationReportDocument
{
    public function data(array $s): array
    {
        $e = $s['event'] ?? [];
        $m = $s['report'] ?? [];
        $h = $s['household'] ?? [];
        $w = $s['weather'] ?? [];
        $number = fn ($value) => preg_match('/^-?\d+(?:\.\d+)?/', trim((string) $value), $match) ? (float) $match[0] : null;
        $sections = array_values(array_intersect($s['included_sections'] ?? array_keys(SituationReportSections::LABELS), array_keys(SituationReportSections::LABELS)));
        $document = [
            'included_sections' => $sections, 'barangay' => $s['barangay_profile']['name'] ?? 'Not recorded',
            'disaster_name' => $e['name'] ?? 'Not recorded', 'disaster_type' => $e['type'] ?? 'Not recorded',
            'status' => $e['status'] ?? 'Not recorded', 'alert_level' => $e['severity'] ?? 'Not recorded',
            'situation_status' => $e['situation_status'] ?? 'Not recorded', 'coverage' => $e['coverage'] ?? (isset($e['sitio_name']) ? $e['sitio_name'].' · ' : '').(($h['total'] ?? 0).' households in scope'),
            'scope_note' => $s['scope_note'] ?? '', 'report_number' => $m['report_number'] ?? 'Not recorded',
            'prepared_by' => $m['prepared_by'] ?? null, 'reviewed_by' => $m['reviewed_by'] ?? null,
            'declared_at' => $this->date($e['declared_at'] ?? null), 'finished_at' => $this->date($e['finished_at'] ?? null),
            'period_start' => $this->date($m['period_start'] ?? null), 'period_end' => $this->date($m['period_end'] ?? null),
            'generated_at' => $this->date($m['generated_at'] ?? null),
            'weather' => [
                'condition' => $w['condition'] ?? null, 'wind_kmh' => $number($w['wind'] ?? null),
                'wind_dir' => preg_replace('/^-?[\d.]+\s*km\/h\s*/i', '', $w['wind'] ?? ''),
                'rain_mm' => $number($w['rainfall'] ?? null), 'temp_c' => $number($w['temperature'] ?? null),
                'wind_display' => $w['wind'] ?? 'Not recorded', 'rain_display' => $w['rainfall'] ?? 'Not recorded',
                'temp_display' => $w['temperature'] ?? 'Not recorded', 'source' => $w['source'] ?? null, 'advisory' => $w['advisory'] ?? null,
            ],
            'population' => ['in_scope' => $h['total'] ?? null, 'reported' => $h['reported'] ?? null, 'affected' => $h['affected'] ?? (isset($h['unsafe'],$h['evacuated']) ? $h['unsafe'] + $h['evacuated'] : null),
                'safe' => $h['safe_total'] ?? null, 'evacuated' => $h['evacuated'] ?? null, 'unsafe' => $h['unsafe'] ?? null, 'unchecked' => $h['unchecked'] ?? null],
            'casualties' => array_replace(['deaths' => null, 'missing' => null, 'injured' => null, 'rescued' => null], $s['casualties'] ?? []),
            'damage' => array_replace(['partial' => null, 'total' => null, 'cost' => null], $s['damage'] ?? []),
            'actions' => $s['actions_text'] ?? null,
            'household_details' => $h['details'] ?? [],
            'include_households' => $s['include_households'] ?? true,
            'household_scope' => $s['household_scope']['label'] ?? (isset($e['sitio_name']) ? $e['sitio_name'].' · all puroks' : 'All sitios · all puroks'),
            'puroks' => array_map(fn ($row) => ['name' => $row['purok'] ?? 'Not recorded', 'sitio' => $row['sitio'] ?? 'Not recorded',
                'households' => $row['total'] ?? null, 'persons' => $row['persons'] ?? null, 'affected' => $row['affected'] ?? null,
                'affected_persons' => $row['affected_persons'] ?? null, 'safe' => $row['safe'] ?? null, 'evacuated' => $row['evacuated'] ?? null,
                'unsafe' => $row['unsafe'] ?? null, 'unchecked' => $row['unchecked'] ?? null], $h['puroks'] ?? []),
            'timeline' => array_map(fn ($row) => ['category' => $row['title'] ?? '', 'title' => $row['actor'] ?? '',
                'actor' => $row['actor'] ?? '', 'detail' => $row['detail'] ?? '', 'logged_at' => $this->date($row['time'] ?? null), 'status' => $row['status'] ?? ''], $s['dispatch']['timeline'] ?? []),
            'teams' => array_map(function ($row) {
                $c = $row['outcome_counts'] ?? [];

                return ['team' => $row['team'] ?? '', 'area' => $row['area'] ?? '', 'deployed_at' => $this->date($row['deployed_at'] ?? null),
                    'safe' => $c['safe_count'] ?? null, 'evacuated' => $c['evacuated_count'] ?? null, 'unsafe' => $c['unsafe_count'] ?? null,
                    'injured' => $c['injured_count'] ?? null, 'missing' => $c['missing_count'] ?? null];
            }, $s['dispatch']['rows'] ?? []),
            'resources' => array_map(fn ($row) => ['item' => $row['item'] ?? null, 'quantity' => $row['quantity'] ?? null,
                'source' => $row['source'] ?? null, 'status' => $row['status'] ?? null], $s['resources']['rows'] ?? []),
        ];
        foreach (['timeline' => 'logged_at', 'teams' => 'deployed_at'] as $key => $dateKey) {
            usort($document[$key], fn ($a, $b) => ($a[$dateKey] instanceof \DateTimeInterface ? $a[$dateKey]->getTimestamp() : PHP_INT_MAX)
                <=> ($b[$dateKey] instanceof \DateTimeInterface ? $b[$dateKey]->getTimestamp() : PHP_INT_MAX));
        }

        return $document;
    }

    private function date(mixed $value): mixed
    {
        if (! $value || $value instanceof \DateTimeInterface) {
            return $value;
        }
        if (! preg_match('/\b\d{4}\b/', (string) $value)) {
            return $value;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function tables(array $r): array
    {
        $tables = [];
        if (in_array('II', $r['included_sections'])) {
            $tables[] = [
                'Population', ['Sitio', 'Purok', 'Households', 'Persons', 'Affected households', 'Affected persons', 'Safe', 'Evacuated', 'Unsafe', 'Unchecked'],
                array_map(fn ($p) => [$p['sitio'], $p['name'], $p['households'], $p['persons'], $p['affected'], $p['affected_persons'], $p['safe'], $p['evacuated'], $p['unsafe'], $p['unchecked']], $r['puroks']),
                [24, 24, 14, 14, 20, 20, 12, 14, 12, 14],
            ];
        }
        if (in_array('V', $r['included_sections'])) {
            $tables[] = ['Timeline', ['Category', 'Title / Team', 'Detail', 'Logged at'],
                array_map(fn ($t) => [$t['category'], $t['title'], $t['detail'], $t['logged_at']], $r['timeline']), [24, 24, 40, 24]];
            $tables[] = ['Team Outcomes', ['Team', 'Area', 'Safe', 'Evacuated', 'Unsafe', 'Injured', 'Missing'],
                array_map(fn ($t) => [$t['team'], $t['area'], $t['safe'], $t['evacuated'], $t['unsafe'], $t['injured'], $t['missing']], $r['teams']), [20, 30, 11, 13, 11, 11, 11]];
        }
        if (in_array('VII', $r['included_sections'])) {
            $tables[] = ['Resources', ['Item', 'Quantity', 'Source', 'Status'],
                array_map(fn ($x) => [$x['item'], $x['quantity'], $x['source'], $x['status']], $r['resources']), [26, 18, 20, 22]];
        }

        if ($r['include_households'] ?? true) {
            $households = $r['household_details'] ?? [];
            if (in_array('II', $r['included_sections'])) {
                $tables[] = ['Affected Households', ['Household', 'Sitio', 'Purok', 'Registered members', 'Affected members', 'Status'],
                    array_map(fn ($h) => [$h['household'], $h['sitio'], $h['purok'], $h['registered_members'], $h['affected_members'], $h['status']], $households), [24, 24, 24, 20, 20, 18]];
                $members = [];
                foreach ($households as $h) foreach ($h['members'] ?? [] as $m) {
                    $members[] = [$h['household'], $h['sitio'], $h['purok'], $m['name'], $m['age'], $m['sex'], $m['status']];
                }
                $tables[] = ['Affected Members', ['Household', 'Sitio', 'Purok', 'Member name', 'Age', 'Sex', 'Status'], $members, [24, 24, 24, 32, 10, 14, 18]];
            }
            if (in_array('III', $r['included_sections'])) {
                $tables[] = ['Household Casualties', ['Household', 'Sitio', 'Purok', 'Affected members', 'Missing', 'Injured', 'Rescued', 'Immediate needs'],
                    array_map(fn ($h) => [$h['household'], $h['sitio'], $h['purok'], $h['affected_members'], $h['missing'], $h['injured'], $h['rescued'], is_array($h['immediate_needs']) ? implode(', ', $h['immediate_needs']) : $h['immediate_needs']], $households), [24, 24, 24, 20, 12, 12, 12, 45]];
            }
            if (in_array('VI', $r['included_sections'])) {
                $damaged = array_filter($households, fn ($h) => !empty($h['damage']) && !in_array($h['damage'], ['no_damage', 'not_yet_assessed'], true));
                $tables[] = ['Household Damage', ['Household', 'Sitio', 'Purok', 'Initial damage', 'Affected members'],
                    array_values(array_map(fn ($h) => [$h['household'], $h['sitio'], $h['purok'], ucwords(str_replace('_', ' ', $h['damage'])), $h['affected_members']], $damaged)), [24, 24, 24, 28, 20]];
            }
        }
        return $tables;
    }
}
