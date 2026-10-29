<?php

namespace Tests\Feature;

use App\Models\User;
use App\Presenters\ProfilePresenter;
use Tests\TestCase;

class ProfilePresenterTest extends TestCase
{
    public function test_profile_identity_and_permission_contract_is_preserved(): void
    {
        $user = new User([
            'username' => 'personnel.one', 'user_id' => 'USR-1', 'first_name' => 'Personnel',
            'last_name' => 'One', 'name' => 'Personnel One', 'is_active' => true, 'role' => 'response',
        ]);
        $presenter = app(ProfilePresenter::class);
        $this->assertSame('personnel.one', $presenter->identity($user)['account_id']);
        $this->assertSame('No email recorded', $presenter->identity($user)['email']);
        $permissions = collect($presenter->permissions($user))->keyBy('key');
        $this->assertSame('Enabled', $permissions['dashboard']['status']);
        $this->assertSame('Enabled', $permissions['profile']['status']);
        $this->assertSame('Enabled', $permissions['notifications']['status']);
        $this->assertSame('Not granted', $permissions['resource_request_approval']['status']);
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


