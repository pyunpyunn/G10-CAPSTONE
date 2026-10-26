<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Presenters\UserRolePresenter;
use App\Services\Web\WebAccessPolicy;
use App\Services\Mobile\RescuerAccountSupport;
use Tests\TestCase;

class AnnexHRoleAccessTest extends TestCase
{
    private function userFor(string $key, string $base, array $modules, bool $manage = false, bool $approve = false): User
    {
        $role = new Role();
        $role->setRawAttributes([
            'role_id' => 1,
            'role_key' => $key,
            'role_name' => $key,
            'base_role_key' => $base,
            'web_modules' => json_encode($modules),
            'can_manage_personnel' => $manage,
            'can_approve_requests' => $approve,
        ]);

        $user = new User();
        $user->setRawAttributes(['user_id' => 'TEST-USER', 'role_id' => 1, 'is_active' => true]);
        $user->setRelation('role', $role);

        return $user;
    }

    public function test_operations_admin_has_technical_access_without_incident_command_permissions(): void
    {
        $user = $this->userFor('operations_admin', 'operations_admin', [
            'personnel_access', 'profile', 'notifications', 'inquiries',
        ], manage: true);
        $policy = app(WebAccessPolicy::class);

        $this->assertSame(['personnel_access', 'profile', 'notifications', 'inquiries'], $policy->modulesFor($user));
        $this->assertTrue($user->canManagePersonnel());
        $this->assertFalse($policy->allows($user, 'response_operations'));
        $this->assertFalse($policy->allows($user, 'geospatial_analytics', 'manage_hazards'));
        $this->assertFalse($policy->allows($user, 'resources_requests', 'approve'));
    }

    public function test_chair_can_approve_and_publish_official_actions(): void
    {
        $user = $this->userFor('chair', 'chair', [
            'dashboard', 'response_operations', 'resources_requests', 'profile',
        ], approve: true);
        $policy = app(WebAccessPolicy::class);

        $this->assertTrue($policy->allows($user, 'resources_requests', 'approve'));
        $this->assertTrue($policy->allows($user, 'response_operations', 'publish_alert'));
        $this->assertTrue($policy->allows($user, 'response_operations', 'create_dispatch'));
    }

    public function test_subcommittee_roles_do_not_inherit_chair_approval(): void
    {
        foreach (['mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation'] as $key) {
            $user = $this->userFor($key, $key, ['dashboard', 'resources_requests'], approve: false);
            $this->assertFalse(app(WebAccessPolicy::class)->allows($user, 'resources_requests', 'approve'));
        }
    }

    public function test_field_leader_and_member_keys_remain_distinct_from_the_rescuer_base_role(): void
    {
        $presenter = app(UserRolePresenter::class);

        foreach (['tl', 'tm'] as $key) {
            $role = (object) ['role_key' => $key, 'base_role_key' => 'rescuer'];
            $this->assertSame($key, $presenter->key($role, ['role_id' => 1]));
        }
    }

    public function test_only_annex_h_field_units_allow_new_mobile_rescuer_accounts(): void
    {
        $support = app(RescuerAccountSupport::class);

        foreach (['SRR', 'ECM', 'HFAPS', 'BHERT', 'SST', 'FMT', 'RDT', 'TRT', 'CLT', 'RDANA', 'PDANA', 'SAR', 'MED', 'EVC', 'FIR', 'LOG', 'DANA', 'SEC'] as $code) {
            $this->assertTrue($support->isSupportedMobileTeamCode($code), $code);
        }
        foreach (['CWT', 'RPT', 'LHT', 'ISH', 'COM'] as $code) {
            $this->assertFalse($support->isSupportedMobileTeamCode($code), $code);
        }
    }
}
