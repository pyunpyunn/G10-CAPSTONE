<?php

namespace Tests\Feature;

use App\Presenters\ProfilePresenter;
use Tests\TestCase;

class ProfilePresenterTest extends TestCase
{
    public function test_profile_identity_and_permission_contract_is_preserved(): void
    {
        $user = new class {
            public string $username = 'admin.one'; public string $user_id = 'USR-1';
            public string $first_name = 'Admin'; public string $last_name = 'One'; public string $name = 'Admin One';
            public ?string $email = null; public ?string $contact_number = null; public bool $is_active = true;
            public ?string $assigned_center_id = null;
            public function roleName(): string { return 'Administrator'; }
            public function roleKey(): string { return 'admin'; }
        };
        $presenter = app(ProfilePresenter::class);
        $this->assertSame('admin.one', $presenter->identity($user)['account_id']);
        $this->assertSame('No email recorded', $presenter->identity($user)['email']);
        $this->assertSame('Enabled', $presenter->permissions('admin')[0]['status']);
        $this->assertSame('Limited', $presenter->permissions('rescuer')[0]['status']);
    }

    public function test_activity_projection_keeps_labels_and_recent_date_display(): void
    {
        $items = app(ProfilePresenter::class)->activity(collect([(object) [
            'audit_log_id' => 9, 'module' => 'household_status', 'action' => 'create_report',
            'reference_table' => 'household_status_logs', 'reference_id' => '18', 'created_at' => now(),
        ]]));
        $this->assertSame('Household Status Create Report', $items[0]['title']);
        $this->assertSame('Household Status Logs #18', $items[0]['description']);
        $this->assertSame('Today', $items[0]['date_label']);
    }
}


