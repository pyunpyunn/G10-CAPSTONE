<?php

namespace Tests\Feature;

use App\Presenters\RescueDispatchPresenter;
use Tests\TestCase;

class RescueDispatchPresenterTest extends TestCase
{
    public function test_dispatch_payload_keeps_status_route_outcome_and_coverage_fields(): void
    {
        $dispatch = app(RescueDispatchPresenter::class)->dispatch((object) [
            'assignment_id' => 8, 'assignment_code' => 'DSP-20260930-0008', 'team_id' => 2,
            'team_name' => 'Team A', 'team_code' => 'A', 'team_type' => 'Search', 'responder_id' => 7,
            'responder_name' => 'Alex Responder', 'responder_contact' => '555', 'household_id' => 'HH-1',
            'assigned_area' => 'Sitio Uno', 'priority_level' => 'high', 'status' => 'en_route',
            'dispatch_notes' => null, 'route_notes' => json_encode(['route_notes' => 'North', 'households_to_cover' => 4]),
            'outcome_notes' => json_encode(['safe_count' => 2, 'evacuated_count' => 1]),
            'assigned_at' => '2026-09-30 10:00:00', 'accepted_at' => null, 'en_route_at' => null,
            'arrived_at' => null, 'completed_at' => null,
        ]);

        $this->assertSame('en_route', $dispatch['status']['key']);
        $this->assertSame('North', $dispatch['route_notes']);
        $this->assertSame(4, $dispatch['households_to_cover']);
        $this->assertSame(2, $dispatch['outcomes']['safe']);
        $this->assertSame(75, $dispatch['coverage_percent']);
    }

    public function test_dispatch_presenter_handles_null_dispatch_and_status_aliases(): void
    {
        $presenter = app(RescueDispatchPresenter::class);
        $this->assertSame([], $presenter->dispatch(null));
        $this->assertSame('on_scene', $presenter->status('onscene')['key']);
        $this->assertSame('Stand-by', $presenter->status(null)['label']);
    }
}


