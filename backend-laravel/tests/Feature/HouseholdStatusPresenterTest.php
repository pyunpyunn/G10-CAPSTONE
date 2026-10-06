<?php

namespace Tests\Feature;

use App\Presenters\HouseholdStatusPresenter;
use Tests\TestCase;

class HouseholdStatusPresenterTest extends TestCase
{
    public function test_household_status_normalizes_safe_unsafe_and_mobile_note_statuses(): void
    {
        $presenter = new HouseholdStatusPresenter();

        $this->assertSame('safe', $presenter->formatStatus('returned', 'Returned')['key']);
        $this->assertSame('needs-help', $presenter->formatStatus('injured', 'Injured')['key']);
        $this->assertSame('evacuated', $presenter->formatStatus('safe', 'Safe', [
            'mobile_status_key' => 'evacuated',
            'mobile_status_label' => 'Evacuated',
        ])['key']);
    }

    public function test_device_risk_and_status_log_keep_display_labels(): void
    {
        $presenter = new HouseholdStatusPresenter();
        $this->assertSame('critical', $presenter->deviceRisk(1, 1, 10, null, 'Sitio Uno')['key']);
        $log = $presenter->formatStatusLog((object) [
            'status_log_id' => 5, 'status_key' => 'safe', 'status_label' => 'Safe', 'notes' => '{"user_notes":"Checked in"}',
            'source' => 'household_mobile', 'submitter_name' => 'Alex Responder', 'location_label' => 'Sitio Uno',
            'location_accuracy_m' => 10, 'battery_level' => 80, 'signal_strength' => 4,
            'submitted_at' => '2026-09-30 10:00:00', 'created_at' => null, 'submitted_by_user_id' => 'USR-1',
        ]);
        $this->assertSame('Household mobile', $log['source']);
        $this->assertSame('Alex Responder', $log['submitted_by']);
        $this->assertSame('Checked in', $log['notes']);
    }
}


