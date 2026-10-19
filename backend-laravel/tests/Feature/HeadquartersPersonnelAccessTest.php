<?php

namespace Tests\Feature;

use App\Models\User;
use App\Queries\RescuerAccountQuery;
use App\Services\Web\HeadquartersTeamSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeadquartersPersonnelAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary(); $table->string('role_key'); $table->string('role_name');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            foreach (['first_name', 'last_name', 'name', 'username', 'email', 'password', 'contact_number'] as $column) $table->string($column)->nullable();
            $table->integer('role_id'); $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary(); $table->string('team_code')->unique();
            $table->string('team_name'); $table->string('team_type'); $table->string('duty_status');
            $table->integer('assigned_purok_id')->nullable(); $table->integer('leader_responder_id')->nullable(); $table->timestamps();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->integer('responder_id')->primary(); $table->string('user_id'); $table->integer('team_id')->nullable();
            foreach (['username', 'responder_code', 'password_hash', 'full_name', 'title', 'contact_number', 'emergency_contact_name', 'emergency_contact_number', 'date_of_birth', 'gender', 'blood_type', 'address', 'skills', 'training_notes', 'certification_reference', 'equipment_notes', 'last_active_at'] as $column) $table->string($column)->nullable();
            $table->string('duty_status')->default('available'); $table->boolean('is_validated')->default(true);
            $table->boolean('is_deployed')->default(false); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary(); $table->string('purok_sitio')->nullable(); $table->softDeletes();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('team_id'); $table->integer('responder_id')->nullable(); $table->string('status');
        });
        DB::table('roles')->insert([
            ['role_id' => 1, 'role_key' => 'super_admin', 'role_name' => 'Super Admin'],
            ['role_id' => 2, 'role_key' => 'admin', 'role_name' => 'Admin'],
            ['role_id' => 3, 'role_key' => 'rescuer', 'role_name' => 'Rescuer'],
            ['role_id' => 7, 'role_key' => 'hq_personnel', 'role_name' => 'HQ Personnel'],
        ]);
    }

    private function account(string $id, int $role = 2): User
    {
        return User::query()->create([
            'user_id' => str_starts_with($id, 'BDRRM-HQCC-') ? 'USR-HQCC-'.$id : 'USR-'.$id,
            'username' => $id, 'first_name' => 'Test', 'last_name' => $id,
            'name' => 'Test '.$id, 'role_id' => $role, 'password' => Hash::make('test-password'),
            'is_active' => true, 'contact_number' => '09123456789',
        ]);
    }

    private function headquarters(): array
    {
        $this->account('BDRRM-HQCC-001');
        $leader = $this->account('BDRRM-HQCC-002', 7);
        $member = $this->account('BDRRM-HQCC-003', 7);
        app(HeadquartersTeamSupport::class)->backfill();
        $teamId = (int) DB::table('rescue_teams')->where('team_code', 'HQCC')->value('team_id');
        $leaderId = (int) DB::table('responders')->where('user_id', $leader->user_id)->value('responder_id');
        DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => $leaderId]);
        app(HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, $leaderId);
        return [$leader, $member, $teamId];
    }

    public function test_existing_hq_accounts_are_linked_and_explicit_leader_gets_leader_role(): void
    {
        [$leader, $member, $teamId] = $this->headquarters();
        $password = $leader->password;
        app(HeadquartersTeamSupport::class)->backfill();
        $this->assertSame(1, DB::table('rescue_teams')->count());
        $this->assertSame(3, DB::table('responders')->count());
        $this->assertContains('BDRRM-HQCC-001', DB::table('responders')->orderBy('responder_id')->pluck('username')->all());
        $this->assertSame($password, $leader->fresh()->password);
        $this->assertSame('hq_leader', $leader->fresh()->roleKey());
        $this->assertSame('hq_personnel', DB::table('users as u')->join('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.username', 'BDRRM-HQCC-001')->value('r.role_key'));
        $this->assertSame($teamId, (int) DB::table('responders')->where('user_id', $member->user_id)->value('team_id'));
        $this->assertTrue($leader->fresh()->isHqccLeader());
    }

    public function test_team_leadership_persists_hq_leader_role_and_demotes_previous_leader(): void
    {
        [$leader, $member, $teamId] = $this->headquarters();
        $leaderRoleId = DB::table('roles')->where('role_key', 'hq_leader')->value('role_id');
        $personnelRoleId = DB::table('roles')->where('role_key', 'hq_personnel')->value('role_id');
        $memberId = (int) DB::table('responders')->where('user_id', $member->user_id)->value('responder_id');

        $this->assertSame($leaderRoleId, (int) DB::table('users')->where('user_id', $leader->user_id)->value('role_id'));
        app(HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, $memberId);

        $this->assertSame($personnelRoleId, (int) DB::table('users')->where('user_id', $leader->user_id)->value('role_id'));
        $this->assertSame($leaderRoleId, (int) DB::table('users')->where('user_id', $member->user_id)->value('role_id'));
        $this->assertSame('hq_leader', $member->fresh()->roleKey());
    }

    public function test_hq_account_backfill_does_not_assign_a_leader_by_account_number(): void
    {
        $personnel = $this->account('BDRRM-HQCC-002', 7);
        app(HeadquartersTeamSupport::class)->backfill();

        $this->assertNull(DB::table('rescue_teams')->where('team_code', 'HQCC')->value('leader_responder_id'));
        $this->assertSame('hq_personnel', $personnel->fresh()->roleKey());
        $this->assertFalse($personnel->fresh()->canManagePersonnel());
    }

    public function test_deleting_hqcc_team_demotes_leader_and_personnel_access(): void
    {
        [$leader, , $teamId] = $this->headquarters();
        Sanctum::actingAs($this->account('SUPER', 1));

        $this->deleteJson('/api/v1/rescuers/team-config/'.$teamId)->assertOk();

        $leader = $leader->fresh();
        $this->assertSame('hq_personnel', $leader->roleKey());
        $this->assertFalse($leader->canManagePersonnel());
    }

    public function test_web_role_module_matrix_and_request_approval_are_strict(): void
    {
        [$leader, $personnel] = $this->headquarters();
        $superAdmin = $this->account('SUPER', 1);
        $rescuer = $this->account('BDRRM-SAR-001', 3);
        $policy = app(\App\Services\Web\WebAccessPolicy::class);

        $this->assertSame([
            'dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests',
            'reports_documentation', 'personnel_access', 'profile', 'notifications',
        ], $policy->modulesFor($leader->fresh()));
        $this->assertSame([
            'dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests',
            'reports_documentation', 'profile', 'notifications',
        ], $policy->modulesFor($personnel));
        $this->assertSame(['dashboard', 'geospatial_analytics', 'profile', 'notifications'], $policy->modulesFor($rescuer));
        $this->assertContains('inquiries', $policy->modulesFor($superAdmin));
        $this->assertTrue($policy->allows($leader->fresh(), 'resources_requests', 'approve'));
        $this->assertFalse($policy->allows($personnel, 'resources_requests', 'approve'));
        $this->assertFalse($policy->allows($leader, 'inquiries'));
        $this->assertFalse($policy->allows($rescuer, 'response_operations'));
        $approvalRequest = \Illuminate\Http\Request::create('/api/v1/resource-requests/RQ-1/validate', 'POST');
        $this->assertSame('resources_requests', $policy->moduleForRequest($approvalRequest));
        $this->assertSame('approve', $policy->actionForRequest($approvalRequest));
    }

    public function test_superadmin_and_saved_hq_leader_can_open_personnel_but_personnel_cannot(): void
    {
        [$leader, $member] = $this->headquarters();
        foreach ([$this->account('SUPER', 1), $leader] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/rescuers/team-config')->assertOk()
                ->assertJsonPath('data.teams.0.team_code', 'HQCC')->assertJsonPath('data.teams.0.member_count', 3);
            $this->getJson('/api/v1/headquarters-accounts')->assertOk();
            $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.permissions.can_manage_personnel', true);
        }
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->assertTrue(app(\App\Services\Web\WebAccessPolicy::class)->allows($member->fresh(), 'profile'));
        $this->postJson('/api/v1/resource-requests/RQ-1/validate', [])->assertForbidden();
        $this->getJson('/api/v1/rescuers/team-config')->assertForbidden();
        $this->postJson('/api/v1/headquarters-accounts', [])->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.permissions.can_manage_personnel', false);
        Sanctum::actingAs($this->account('LEGACY-ADMIN'));
        $this->getJson('/api/v1/rescuers/team-config')->assertForbidden();
        Sanctum::actingAs($leader);
        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/inquiries')->assertForbidden();
    }

    public function test_leader_access_is_revoked_immediately_after_leadership_changes(): void
    {
        [$leader, $member, $teamId] = $this->headquarters();
        Sanctum::actingAs($leader);
        $this->getJson('/api/v1/rescuers/team-config')->assertOk();
        $newLeaderId = DB::table('responders')->where('user_id', $member->user_id)->value('responder_id');
        DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => $newLeaderId]);
        app(HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, (int) $newLeaderId);
        $this->getJson('/api/v1/rescuers/team-config')->assertForbidden();
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/rescuers/team-config')->assertOk();
        DB::table('users')->where('user_id', $member->user_id)->update(['is_active' => false]);
        Sanctum::actingAs($member->fresh());
        $this->getJson('/api/v1/rescuers/team-config')->assertForbidden();
    }

    public function test_a_leader_title_or_leadership_of_a_rescue_team_does_not_grant_personnel_access(): void
    {
        $user = $this->account('BDRRM-SAR-001', 3);
        DB::table('rescue_teams')->insert(['team_id' => 10, 'team_code' => 'SAR', 'team_name' => 'Search', 'team_type' => 'Rescue', 'duty_status' => 'available', 'leader_responder_id' => 10]);
        DB::table('responders')->insert(['responder_id' => 10, 'user_id' => $user->user_id, 'team_id' => 10, 'username' => 'BDRRM-SAR-001', 'title' => 'Team Lead']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/rescuers/team-config')->assertForbidden();
        $this->getJson('/api/v1/rescuers')->assertForbidden();
    }

    public function test_editing_an_account_cannot_bypass_hq_team_membership_rules(): void
    {
        [$leader] = $this->headquarters();
        Sanctum::actingAs($this->account('ADMIN', 1));
        DB::table('rescue_teams')->insert(['team_id' => 10, 'team_code' => 'SAR', 'team_name' => 'Search', 'team_type' => 'Rescue', 'duty_status' => 'available']);
        $id = DB::table('responders')->where('user_id', $leader->user_id)->value('responder_id');
        $this->patchJson('/api/v1/rescuers/'.$id, [
            'first_name' => 'HQ', 'last_name' => 'Leader', 'contact_number' => '09123456789',
            'title' => 'HQ Leader', 'duty_status' => 'on_duty', 'account_status' => 'active',
            'team_id' => 10, 'team_name' => 'Search', 'team_code' => 'SAR',
        ])->assertUnprocessable()->assertJsonValidationErrors('team_name');
    }

    public function test_hq_personnel_creation_links_members_and_increments_ids(): void
    {
        Sanctum::actingAs($this->account('ADMIN', 1));
        $body = ['first_name' => 'HQ', 'last_name' => 'Personnel', 'password' => 'test-password'];
        $this->postJson('/api/v1/headquarters-accounts', ['account_type' => 'command_center', ...$body])
            ->assertCreated()->assertJsonPath('data.account.account_id', 'BDRRM-HQCC-001');
        $this->assertSame(1, DB::table('responders')->count());
        $this->assertSame('HQCC', DB::table('rescue_teams')->value('team_code'));
        $this->postJson('/api/v1/headquarters-accounts', ['account_type' => 'command_center', ...$body])
            ->assertCreated()->assertJsonPath('data.account.account_id', 'BDRRM-HQCC-002');
        $this->assertSame(2, DB::table('users')->where('username', 'like', 'BDRRM-HQCC-%')->count());
        $this->assertSame(2, DB::table('responders')->count());
    }

    public function test_hq_membership_and_leader_changes_persist_and_cross_type_members_are_rejected(): void
    {
        [$leader, $member, $teamId] = $this->headquarters();
        $admin = $this->account('ADMIN', 1);
        Sanctum::actingAs($admin);
        $memberId = (int) DB::table('responders')->where('user_id', $member->user_id)->value('responder_id');
        $body = ['team_code' => 'HQCC', 'team_name' => 'HQ Command Center', 'team_type' => 'HQCC', 'duty_status' => 'off_duty', 'member_ids' => [$memberId], 'leader_responder_id' => $memberId];
        $this->patchJson('/api/v1/rescuers/team-config/'.$teamId, $body)->assertOk()
            ->assertJsonPath('data.teams.0.member_count', 1)->assertJsonPath('data.teams.0.leader_responder_id', $memberId);
        $this->assertSame('hq_leader', DB::table('users as u')->join('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.user_id', $member->user_id)->value('r.role_key'));
        Sanctum::actingAs($member->fresh());
        $this->getJson('/api/v1/dashboard')->assertOk();
        Sanctum::actingAs($admin);
        $this->assertNull(DB::table('responders')->where('user_id', $leader->user_id)->value('team_id'));
        $this->postJson('/api/v1/rescuers/team-config', [...$body, 'team_code' => 'SAR', 'team_name' => 'Rescue', 'team_type' => 'Rescue'])->assertUnprocessable()->assertJsonValidationErrors('member_ids');
        $this->deleteJson('/api/v1/rescuers/team-config/'.$teamId)->assertOk();
        $this->assertSame(3, DB::table('responders')->count());
        $this->assertSame(4, DB::table('users')->count());
    }

    public function test_rescue_roster_filter_excludes_hq_and_removed_profiles_are_not_restored(): void
    {
        [$leader] = $this->headquarters();
        $filters = ['search' => '', 'team' => 'all', 'duty_status' => 'all', 'purok' => 'all', 'account_type' => 'rescuer'];
        $this->assertSame(0, app(RescuerAccountQuery::class)->paginateResponders($filters, 8)->total());
        $filters['account_type'] = 'command_center';
        $this->assertSame(3, app(RescuerAccountQuery::class)->paginateResponders($filters, 8)->total());
        DB::table('responders')->where('user_id', $leader->user_id)->update(['deleted_at' => now()]);
        app(HeadquartersTeamSupport::class)->backfill();
        $this->assertFalse($leader->isHqccLeader());
        $this->assertSame(3, DB::table('responders')->count());
    }
    private function installRolePermissions(): void
    {
        (require database_path('migrations/2026_10_18_000010_add_personnel_role_permissions.php'))->up();
        Schema::table('responders', fn (Blueprint $table) => $table->string('created_by_admin_id')->nullable());
    }

    private function rolePayload(string $name, string $base = 'rescuer'): array
    {
        return ['role_name' => $name, 'base_role_key' => $base, 'web_modules' => ['dashboard', 'reports_documentation', 'profile'],
            'can_manage_personnel' => false, 'can_approve_requests' => false];
    }

    public function test_role_crud_synchronizes_personnel_titles_and_live_access(): void
    {
        $this->installRolePermissions(); $super = $this->account('SUPER', 1); Sanctum::actingAs($super);
        $result = $this->postJson('/api/v1/rescuers/roles', $this->rolePayload('Boat Medic'))->assertCreated()->json('data');
        $role = collect($result['roles'])->firstWhere('role_name', 'Boat Medic');
        $user = $this->account('BDRRM-SAR-001', 3);
        DB::table('responders')->insert(['responder_id' => 1, 'user_id' => $user->user_id, 'username' => 'BDRRM-SAR-001', 'full_name' => 'Test Medic', 'title' => 'Responder']);
        $body = ['first_name' => 'Test', 'last_name' => 'Medic', 'contact_number' => '09123456789', 'account_status' => 'active', 'title' => 'Boat Medic', 'duty_status' => 'available', 'role_id' => $role['role_id']];
        $this->patchJson('/api/v1/rescuers/1', $body)->assertOk()->assertJsonPath('data.rescuer.title', 'Boat Medic')->assertJsonPath('data.rescuer.role_id', $role['role_id']);
        $this->assertSame('rescuer', $user->fresh()->roleKey());
        $this->assertTrue(app(\App\Services\Web\WebAccessPolicy::class)->allows($user->fresh(), 'reports_documentation'));
        $edited = $this->rolePayload('Senior Boat Medic'); $edited['web_modules'] = ['dashboard', 'profile'];
        $this->patchJson('/api/v1/rescuers/roles/'.$role['role_id'], $edited)->assertOk();
        $this->assertSame('Senior Boat Medic', DB::table('responders')->where('responder_id', 1)->value('title'));
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.role.role_name', 'Senior Boat Medic')->assertJsonPath('data.permissions.web_modules', ['dashboard', 'profile']);
        $this->getJson('/api/v1/situation-reports')->assertForbidden();
        $this->postJson('/api/v1/rescuers/roles', $this->rolePayload('Unauthorized'))->assertForbidden();
        Sanctum::actingAs($super);
        $this->deleteJson('/api/v1/rescuers/roles/'.$role['role_id'])->assertUnprocessable()->assertJsonValidationErrors('replacement_role_id');
        $this->deleteJson('/api/v1/rescuers/roles/'.$role['role_id'], ['replacement_role_id' => 3])->assertOk();
        $this->assertSame(3, (int) $user->fresh()->role_id);
        $this->assertSame('Rescuer', DB::table('responders')->where('responder_id', 1)->value('title'));
        $this->assertFalse(DB::table('roles')->where('role_id', $role['role_id'])->exists());
        $this->deleteJson('/api/v1/rescuers/roles/1')->assertStatus(422);
        $this->postJson('/api/v1/rescuers/roles', $this->rolePayload('Medic'))->assertUnprocessable()->assertJsonValidationErrors('role_name');
    }

    public function test_custom_hq_leadership_role_creation_and_demotion_update_access_and_team(): void
    {
        $this->installRolePermissions(); Sanctum::actingAs($this->account('SUPER', 1));
        $payload = $this->rolePayload('Operations Lead', 'hq_leader'); $payload['web_modules'][] = 'personnel_access'; $payload['can_manage_personnel'] = true;
        $roles = $this->postJson('/api/v1/rescuers/roles', $payload)->assertCreated()->json('data.roles');
        $role = collect($roles)->firstWhere('role_name', 'Operations Lead');
        $account = $this->postJson('/api/v1/headquarters-accounts', ['account_type' => 'command_center', 'first_name' => 'Mara', 'last_name' => 'Lead', 'contact_number' => '09123456789', 'password' => 'temporary-password', 'role_id' => $role['role_id'], 'gender' => 'Female', 'address' => 'Cebu'])->assertCreated()->json('data.account');
        $user = User::query()->find($account['user_id']);
        $this->assertSame('hq_leader', $user->roleKey());
        $this->assertTrue($user->canManagePersonnel());
        $this->assertSame($account['responder_id'], (int) DB::table('rescue_teams')->where('team_code', 'HQCC')->value('leader_responder_id'));
        $this->assertSame('Operations Lead', DB::table('responders')->where('responder_id', $account['responder_id'])->value('title'));
        $team = DB::table('rescue_teams')->where('team_code', 'HQCC')->first();
        $this->patchJson('/api/v1/rescuers/'.$account['responder_id'], ['first_name' => 'Mara', 'last_name' => 'Lead', 'contact_number' => '09123456789', 'title' => 'HQ Personnel', 'duty_status' => 'on_duty', 'account_status' => 'active', 'team_id' => $team->team_id, 'team_name' => $team->team_name, 'role_id' => 7])->assertOk();
        $this->assertSame('hq_personnel', $user->fresh()->roleKey());
        $this->assertFalse($user->fresh()->canManagePersonnel());
        $this->assertNull(DB::table('rescue_teams')->where('team_id', $team->team_id)->value('leader_responder_id'));
    }

    public function test_rescuer_account_create_activate_deactivate_and_delete_keep_role_and_login_in_sync(): void
    {
        $this->installRolePermissions(); Sanctum::actingAs($this->account('SUPER', 1));
        DB::table('rescue_teams')->insert(['team_id' => 1, 'team_code' => 'SAR', 'team_name' => 'Search', 'team_type' => 'Rescue', 'duty_status' => 'available']);
        $roleId = DB::table('roles')->where('role_name', 'Medic')->value('role_id');
        $body = ['account_id' => 'BDRRM-SAR-001', 'first_name' => 'Ana', 'last_name' => 'Medic', 'contact_number' => '09123456789', 'password' => 'temporary-password', 'title' => 'Medic', 'role_id' => $roleId, 'team_id' => 1, 'team_name' => 'Search', 'duty_status' => 'available', 'account_status' => 'disabled'];
        $profile = $this->postJson('/api/v1/rescuers', $body)->assertCreated()->assertJsonPath('data.rescuer.is_active', false)->json('data.rescuer');
        $this->assertSame((int) $roleId, (int) DB::table('users')->where('user_id', $profile['user_id'])->value('role_id'));
        $this->assertTrue(Hash::check('temporary-password', DB::table('users')->where('user_id', $profile['user_id'])->value('password')));
        $body['account_status'] = 'active'; unset($body['password']);
        $this->patchJson('/api/v1/rescuers/'.$profile['responder_id'], $body)->assertOk()->assertJsonPath('data.rescuer.is_active', true)->assertJsonPath('data.rescuer.title', 'Medic');
        $this->postJson('/api/v1/rescuers/'.$profile['responder_id'].'/deactivate')->assertOk();
        $this->assertSame(0, (int) DB::table('users')->where('user_id', $profile['user_id'])->value('is_active'));
        $this->deleteJson('/api/v1/rescuers/'.$profile['responder_id'])->assertOk();
        $this->assertNotNull(DB::table('responders')->where('responder_id', $profile['responder_id'])->value('deleted_at'));
        $this->getJson('/api/v1/rescuers/'.$profile['responder_id'])->assertNotFound();
    }

    public function test_rescue_team_crud_keeps_leader_role_and_membership_synchronized(): void
    {
        $this->installRolePermissions(); Sanctum::actingAs($this->account('SUPER', 1));
        $person = $this->account('BDRRM-SAR-001', 3);
        DB::table('responders')->insert(['responder_id' => 1, 'user_id' => $person->user_id, 'username' => 'BDRRM-SAR-001', 'title' => 'Responder', 'full_name' => 'Alex Rescue']);
        $body = ['team_name' => 'Alpha', 'team_type' => 'Rescue', 'duty_status' => 'available', 'member_ids' => [1], 'leader_responder_id' => 1];
        $created = $this->postJson('/api/v1/rescuers/team-config', $body)->assertCreated()->json('data');
        $teamId = $created['selected_team_id'];
        $leaderRole = DB::table('roles')->where('role_name', 'Team Leader')->value('role_id');
        $this->assertSame((int) $leaderRole, (int) $person->fresh()->role_id);
        $this->assertSame('Team Leader', DB::table('responders')->where('responder_id', 1)->value('title'));
        $this->patchJson('/api/v1/rescuers/roles/'.$leaderRole, $this->rolePayload('Field Leader'))->assertOk();
        $this->patchJson('/api/v1/rescuers/1', ['first_name' => 'Alex', 'last_name' => 'Rescue', 'contact_number' => '09123456789', 'title' => 'Field Leader', 'role_id' => $leaderRole, 'team_id' => $teamId, 'team_name' => 'Alpha', 'duty_status' => 'available', 'account_status' => 'active'])->assertOk();
        $this->assertSame(1, (int) DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id'));
        $this->assertSame('Field Leader', DB::table('responders')->where('responder_id', 1)->value('title'));
        $body['team_name'] = 'Alpha Response'; $body['leader_responder_id'] = null;
        $this->patchJson('/api/v1/rescuers/team-config/'.$teamId, $body)->assertOk();
        $this->assertSame('Alpha Response', DB::table('rescue_teams')->where('team_id', $teamId)->value('team_name'));
        $this->assertSame(3, (int) $person->fresh()->role_id);
        $this->assertSame('Responder', DB::table('responders')->where('responder_id', 1)->value('title'));
        $this->deleteJson('/api/v1/rescuers/team-config/'.$teamId)->assertOk();
        $this->assertNull(DB::table('responders')->where('responder_id', 1)->value('team_id'));
        $this->assertNotNull($person->fresh());
    }

}
