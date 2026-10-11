<?php

namespace App\Presenters;

class SituationReportExportPresenter
{
    public function rows(array $summary): array
    {
        $rows = [];
        $add = function (string $section, string $label, mixed $value) use (&$rows): void {
            $rows[] = [$section, $label, $value === null || $value === '' ? 'Not recorded' : (string) $value];
        };
        $add('Report details', 'Barangay', $summary['barangay_profile']['name'] ?? null);
        foreach (['name' => 'Disaster name', 'type' => 'Disaster type', 'declared_at' => 'Date declared', 'finished_at' => 'Date finished', 'status' => 'Event status'] as $key => $label) {
            $add('Report details', $label, $summary['event'][$key] ?? null);
        }
        foreach (['report_number' => 'Report number', 'period_start' => 'Period start', 'period_end' => 'Period end', 'prepared_by' => 'Prepared by', 'reviewed_by' => 'Reviewed by', 'generated_at' => 'Generated at'] as $key => $label) {
            $add('Report details', $label, $summary['report'][$key] ?? null);
        }
        foreach ($summary['included_sections'] ?? [] as $section) {
            $fields = match ($section) {
                'I' => ['weather', ['condition' => 'Condition', 'wind' => 'Wind', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'source' => 'Source', 'advisory' => 'Advisory']],
                'II' => ['household', ['total' => 'Households in scope', 'reported' => 'Reported households', 'safe_total' => 'Safe households', 'evacuated' => 'Evacuated households', 'unsafe' => 'Unsafe households', 'unchecked' => 'Unchecked households']],
                'III' => ['casualties', ['deaths' => 'Deaths', 'missing' => 'Missing', 'injured' => 'Injured', 'rescued' => 'Rescued']],
                'VI' => ['damage', ['partial' => 'Partially damaged houses', 'total' => 'Totally damaged houses', 'cost' => 'Estimated damage cost']],
                default => null,
            };
            if ($fields) {
                foreach ($fields[1] as $key => $label) $add($section, $label, $summary[$fields[0]][$key] ?? null);
            }
            if ($section === 'II') {
                foreach ($summary['household']['puroks'] ?? [] as $row) {
                    foreach (['total' => 'Households', 'safe' => 'Safe', 'evacuated' => 'Evacuated', 'unsafe' => 'Unsafe', 'unchecked' => 'Unchecked'] as $key => $label) $add($section, ($row['purok'] ?? '').' — '.$label, $row[$key] ?? null);
                }
            }
            if ($section === 'IV') {
                foreach ($summary['evacuation'] ?? [] as $row) {
                    foreach (['type' => 'Type', 'status' => 'Status', 'capacity_status' => 'Capacity'] as $key => $label) $add($section, ($row['name'] ?? '').' — '.$label, $row[$key] ?? null);
                }
            }
            if ($section === 'V') {
                foreach ($summary['dispatch']['timeline'] ?? [] as $row) $add($section, trim(($row['title'] ?? '').' — '.($row['actor'] ?? '')), $row['detail'] ?? $row['status'] ?? null);
                foreach ($summary['dispatch']['rows'] ?? [] as $row) $add($section, trim(($row['team'] ?? '').' — '.($row['area'] ?? '')), $row['outcomes'] ?? null);
            }
            if ($section === 'VII') {
                foreach ($summary['resources']['rows'] ?? [] as $row) $add($section, $row['item'] ?? 'Resource request', implode(' — ', array_filter([$row['quantity'] ?? null, $row['source'] ?? null, $row['status'] ?? null])));
            }
            if ($section === 'VIII') $add($section, 'Actions taken and recommendations', $summary['actions_text'] ?? null);
        }
        return $rows;
    }
}
