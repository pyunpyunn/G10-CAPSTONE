<?php

namespace Tests\Feature;

use App\Presenters\SituationReportPresenter;
use Tests\TestCase;

class SituationReportPresenterTest extends TestCase
{
    public function test_summary_keeps_report_sections_and_household_breakdown(): void
    {
        $event = (object) [
            'event_id' => 'EV-1', 'name' => 'Flood', 'type_name' => 'Flood', 'severity_label' => 'High',
            'severity_key' => 'high', 'started_at' => '2026-09-30 08:00:00', 'ended_at' => null,
        ];
        $sources = [
            'household_total' => 4, 'household_reported' => 3,
            'household_counts' => collect(['safe' => 1, 'evacuated' => 1, 'unsafe' => 1]),
            'purok_rows' => collect([
                (object) ['purok' => 'Sitio Uno', 'total' => 2, 'safe' => 1, 'evacuated' => 0, 'unsafe' => 1, 'reported' => 2],
                (object) ['purok' => 'Unassigned', 'total' => 1, 'safe' => 0, 'evacuated' => 0, 'unsafe' => 0, 'reported' => 0],
            ]),
            'weather' => null, 'evacuation' => collect(), 'assignments' => collect(),
            'assignment_outcomes' => collect([
                (object) ['status' => 'dispatched', 'outcome_notes' => '{"safe_count":2,"injured_count":1}'],
                (object) ['status' => 'completed', 'outcome_notes' => '{"evacuated_count":3,"missing_count":1}'],
            ]), 'broadcasts' => collect(), 'requests' => collect(),
        ];

        $summary = app(SituationReportPresenter::class)->buildSummary($event, $sources, ['name' => 'Barangay Uno']);

        $this->assertSame('EV-1', $summary['event']['event_id']);
        $this->assertSame(4, $summary['household']['total']);
        $this->assertSame(3, $summary['household']['reported']);
        $this->assertSame(1, $summary['household']['unchecked']);
        $this->assertSame(50, $summary['household']['safe_percent']);
        $this->assertSame('Sitio Uno', $summary['household']['puroks'][0]['purok']);
        $this->assertSame('No weather snapshot yet', $summary['weather']['condition']);
        $this->assertSame([], $summary['dispatch']['rows']);
        $this->assertSame(2, $summary['dispatch']['total']);
        $this->assertSame(1, $summary['dispatch']['dispatched']);
        $this->assertSame(5, $summary['casualties']['rescued']);
        $this->assertSame(1, $summary['casualties']['missing']);
        $this->assertSame(['name' => 'Barangay Uno'], $summary['barangay_profile']);
    }

    public function test_saved_report_projection_keeps_optional_summary_and_status_label(): void
    {
        $presenter = app(SituationReportPresenter::class);
        $row = (object) [
            'sit_rep_id' => 7, 'report_number' => 'SITREP-2026-001', 'disaster_id' => 'EV-1',
            'event_name' => 'Flood', 'report_status' => 'generated', 'generated_at' => '2026-09-30 08:00:00',
            'is_archived' => 0, 'summary' => '{"event":{"name":"Flood"}}',
        ];
        $this->assertArrayNotHasKey('summary', $presenter->formatSavedReport($row));
        $report = $presenter->formatSavedReport($row, true);
        $this->assertSame('Generated', $report['report_status']);
        $this->assertSame('Flood', $report['summary']['event']['name']);
    }
}


