<?php

namespace Tests\Feature;

use App\Presenters\RescuerAccountPresenter;
use Tests\TestCase;

class RescuerAccountPresenterTest extends TestCase
{
    public function test_responder_payload_keeps_status_name_training_and_detail_fields(): void
    {
        $row = (object) [
            'responder_id' => 12, 'user_id' => 'USR-12', 'username' => 'BDRRM-SAR-012', 'user_username' => 'alex.responder',
            'responder_code' => 'BDRRM-SAR-012', 'full_name' => 'Alex M. Responder', 'user_first_name' => 'Alex',
            'user_last_name' => 'Responder', 'user_full_name' => 'Alex M. Responder', 'title' => 'Medic', 'team_id' => 2,
            'team_name' => 'Medical', 'team_code' => 'MED', 'team_type' => 'Medical', 'contact_number' => '555',
            'emergency_contact_name' => null, 'emergency_contact_number' => null, 'address' => 'Sitio Uno', 'blood_type' => null,
            'skills' => 'First aid', 'training_notes' => 'Refresh due', 'certification_reference' => null, 'equipment_notes' => null,
            'duty_status' => 'on_scene', 'is_deployed' => 1, 'is_validated' => 1, 'is_active' => 1,
            'last_active_at' => null, 'created_at' => '2026-09-30 10:00:00', 'email' => 'alex@example.test',
            'date_of_birth' => '1990-01-01', 'gender' => 'female', 'must_change_password' => 0,
        ];

        $payload = app(RescuerAccountPresenter::class)->formatResponder($row, true);

        $this->assertSame(['key' => 'on_scene', 'label' => 'On-scene', 'tone' => 'green'], $payload['duty_status']);
        $this->assertSame('M.', $payload['middle_initial']);
        $this->assertTrue($payload['training_due']);
        $this->assertSame('alex@example.test', $payload['email']);
        $this->assertFalse($payload['must_change_password']);
    }

    public function test_disabled_status_overrides_deployment_status_and_middle_name_is_normalized(): void
    {
        $presenter = app(RescuerAccountPresenter::class);
        $this->assertSame('disabled', $presenter->formatStatus('on_scene', false)['key']);
        $this->assertSame('J.', $presenter->formatMiddleInitial('james.'));
        $this->assertFalse($presenter->hasTrainingDue((object) ['training_notes' => 'Current', 'certification_reference' => 'Valid']));
    }
}


