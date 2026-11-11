<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Web\HeadquartersTeamSupport;
use App\Services\Web\WebAccessPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HeadquartersPersonnelAccessTest extends TestCase
{
    public function test_confirmed_hq_accounts_receive_their_exact_roles_without_changing_credentials(): void
    {
        $chair = $this->account('CHAIR-0001', 1);
        $ops = $this->account('OPS-0001', 2);
        $chair->update(['username' => 'BDRRM-HQCC-11000']);
        $ops->update(['username' => 'BDRRM-HQCC-11001']);
        foreach ([$chair, $ops] as $index => $user) {
            DB::table('responders')->insert(['responder_id' => $index + 1,
                'user_id' => $user->user_id, 'username' => $user->username, 'team_id' => 9]);
        }
        $passwords = [$chair->password, $ops->password];
        $migration = require database_path('migrations/2026_11_11_000002_correct_confirmed_hq_account_roles.php');
        $migration->up();
        $migration->up();
        $this->assertSame('chair', $chair->fresh()->roleKey());
        $this->assertSame('operations_admin', $ops->fresh()->roleKey());
        $this->assertSame($passwords, [$chair->fresh()->password, $ops->fresh()->password]);
        $this->assertSame('BDRRM-HQCC-11000', $chair->fresh()->username);
        $this->assertSame('BDRRM-HQCC-11001', $ops->fresh()->username);
        $this->assertSame(2, DB::table('responders')->where('team_id', 9)->count());
        foreach ([$chair, $ops] as $user) {
            \Laravel\Sanctum\Sanctum::actingAs($user->fresh());
            $this->getJson('/api/v1/auth/me')->assertOk()
                ->assertJsonPath('data.permissions.can_manage_personnel', $user->user_id === $ops->user_id);
        }
    }

    public function test_field_web_access_preserves_hq_boundaries_and_rejects_inactive_users(): void
    {
        foreach (['tl', 'tm'] as $index => $key) {
            $roleId = 80 + $index;
            DB::table('roles')->insert(['role_id' => $roleId, 'role_key' => $key,
                'role_name' => $key, 'base_role_key' => 'rescuer', 'web_modules' => '[]']);
            $user = $this->account('FIELD-'.$key, $roleId);
            $policy = app(WebAccessPolicy::class);
            $this->assertSame(['field_assignments', 'profile', 'notifications'], $policy->modulesFor($user));
            \Laravel\Sanctum\Sanctum::actingAs($user);
            $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.role.assigned_role_key', $key);
            $this->getJson('/api/v1/rescuer/assignments')->assertOk()->assertJsonPath('data.assignments', []);
            foreach (['dashboard', 'map/overview', 'rescuers', 'resource-requests', 'situation-reports'] as $path) {
                $this->getJson('/api/v1/'.$path)->assertForbidden();
            }
            $this->postJson('/api/v1/dispatches', [])->assertForbidden();
            $this->postJson('/api/v1/disaster-events', [])->assertForbidden();
            $this->postJson('/api/v1/resource-requests/1/validate', [])->assertForbidden();
            $user->update(['is_active' => false]);
            $this->getJson('/api/v1/rescuer/assignments')->assertForbidden();
            $this->assertSame([], $policy->modulesFor($user->fresh()));
        }
    }

    public function test_subcommittees_are_created_idempotently_without_reassigning_field_users(): void
    {
        $user = $this->account('UNCHANGED', 3);
        $migration = require database_path('migrations/2026_11_11_000001_enable_field_web_access_and_hq_subcommittees.php');
        $migration->up();
        $migration->up();
        $this->assertSame(4, DB::table('rescue_teams')->where('team_type', 'HQ')->count());
        $this->assertSame(3, $user->fresh()->role_id);
        $this->assertSame(0, DB::table('responders')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary();
            $table->string('role_key');
            $table->string('role_name');
            $table->string('base_role_key')->nullable();
            $table->json('web_modules')->nullable();
            $table->boolean('can_manage_personnel')->default(false);
            $table->boolean('can_approve_requests')->default(false);
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('username')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->integer('role_id');
            $table->string('contact_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary();
            $table->string('team_code')->unique();
            $table->string('team_name');
            $table->string('team_type');
            $table->string('duty_status');
            $table->integer('assigned_purok_id')->nullable();
            $table->integer('leader_responder_id')->nullable();
            $table->timestamps();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->integer('responder_id')->primary();
            $table->string('user_id');
            $table->integer('team_id')->nullable();
            foreach (['username', 'responder_code', 'password_hash', 'full_name', 'title', 'contact_number'] as $column) {
                $table->string($column)->nullable();
            }
            $table->boolean('is_validated')->default(true);
            $table->boolean('is_deployed')->default(false);
            $table->string('duty_status')->default('available');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::dropIfExists('operational_sequences');
        Schema::create('operational_sequences', function (Blueprint $table): void {
            $table->string('sequence_key')->primary();
            $table->string('sequence_prefix')->nullable();
            $table->unsignedBigInteger('next_value');
        });

        DB::table('roles')->insert([
            ['role_id' => 1, 'role_key' => 'operations_admin', 'role_name' => 'Operations/Admin', 'base_role_key' => 'operations_admin', 'web_modules' => json_encode(['personnel_access', 'profile', 'notifications', 'inquiries']), 'can_manage_personnel' => true, 'can_approve_requests' => false],
            ['role_id' => 2, 'role_key' => 'chair', 'role_name' => 'Chair', 'base_role_key' => 'chair', 'web_modules' => json_encode(['dashboard', 'response_operations', 'resources_requests', 'reports_documentation']), 'can_manage_personnel' => false, 'can_approve_requests' => true],
            ['role_id' => 3, 'role_key' => 'response', 'role_name' => 'Response Sub-committee', 'base_role_key' => 'response', 'web_modules' => json_encode(['dashboard', 'response_operations', 'resources_requests', 'reports_documentation']), 'can_manage_personnel' => false, 'can_approve_requests' => false],
        ]);
    }

    public function test_all_web_roles_can_view_profiles_but_non_managers_cannot_modify_personnel(): void
    {
        $target = $this->account('TARGET', 1);
        DB::table('responders')->insert([
            'responder_id' => 91, 'user_id' => $target->user_id,
            'full_name' => 'Directory Target', 'password_hash' => 'must-not-be-disclosed',
        ]);
        foreach (WebAccessPolicy::MODULES as $key => $modules) {
            if ($modules === []) continue;
            $roleId = 20 + array_search($key, array_keys(WebAccessPolicy::MODULES), true);
            DB::table('roles')->insert([
                'role_id' => $roleId, 'role_key' => $key, 'base_role_key' => $key,
                'role_name' => $key, 'web_modules' => json_encode($modules),
                'can_manage_personnel' => $key === 'operations_admin',
            ]);
            $viewer = $this->account('VIEWER-'.$key, $roleId);
            \Laravel\Sanctum\Sanctum::actingAs($viewer);
            $profile = $this->getJson('/api/v1/personnel-profiles/91')->assertOk()
                ->assertJsonPath('data.profile.full_name', 'Directory Target')->json('data.profile');
            $this->assertArrayNotHasKey('password_hash', $profile);
            $this->assertArrayNotHasKey('emergency_contact_number', $profile);
            $this->assertArrayNotHasKey('address', $profile);
            $this->patchJson('/api/v1/personnel-profiles/91', ['full_name' => 'Changed'])->assertStatus(405);
            if ($key !== 'operations_admin') {
                $this->patchJson('/api/v1/rescuers/91', ['full_name' => 'Changed'])->assertForbidden();
                $this->deleteJson('/api/v1/rescuers/91')->assertForbidden();
                $this->postJson('/api/v1/rescuers/91/deactivate')->assertForbidden();
                $this->getJson('/api/v1/rescuers')->assertForbidden();
            }
        }
        $this->assertSame('Directory Target', DB::table('responders')->where('responder_id', 91)->value('full_name'));
    }

    public function test_profile_requires_web_access_and_hides_deleted_personnel(): void
    {
        $this->getJson('/api/v1/personnel-profiles/91')->assertUnauthorized();
        DB::table('roles')->insert(['role_id' => 90, 'role_key' => 'rescuer', 'role_name' => 'Rescuer']);
        \Laravel\Sanctum\Sanctum::actingAs($this->account('MOBILE', 90));
        $this->getJson('/api/v1/personnel-profiles/91')->assertNotFound();
        $web = $this->account('WEB', 2);
        \Laravel\Sanctum\Sanctum::actingAs($web);
        $this->getJson('/api/v1/personnel-profiles/99999')->assertNotFound();
        DB::table('responders')->insert(['responder_id' => 91, 'user_id' => $web->user_id, 'deleted_at' => now()]);
        $this->getJson('/api/v1/personnel-profiles/91')->assertNotFound();
    }

    private function account(string $id, int $roleId): User
    {
        return User::query()->create([
            'user_id' => 'USR-'.$id,
            'username' => $id,
            'first_name' => 'Test',
            'last_name' => $id,
            'name' => 'Test '.$id,
            'role_id' => $roleId,
            'password' => Hash::make('test-password'),
            'is_active' => true,
            'contact_number' => '09123456789',
        ]);
    }

    public function test_committee_roles_link_accounts_without_rewriting_login_password_or_role(): void
    {
        $operationsAdmin = $this->account('2024035500', 1);
        $chair = $this->account('CHAIR-0001', 2);
        $response = $this->account('RSP-0001', 3);
        $originalPassword = $operationsAdmin->password;

        app(HeadquartersTeamSupport::class)->backfill();

        $this->assertSame(1, DB::table('rescue_teams')->where('team_code', 'HQCC')->count());
        $this->assertSame(3, DB::table('responders')->count());
        $this->assertSame('2024035500', $operationsAdmin->fresh()->username);
        $this->assertSame($originalPassword, $operationsAdmin->fresh()->password);
        $this->assertSame('operations_admin', $operationsAdmin->fresh()->roleKey());
        $this->assertSame('chair', $chair->fresh()->roleKey());
        $this->assertSame('response', $response->fresh()->roleKey());
        $this->assertSame('Operations/Admin', DB::table('responders')->where('user_id', $operationsAdmin->user_id)->value('title'));
        $this->assertNull(DB::table('rescue_teams')->where('team_code', 'HQCC')->value('leader_responder_id'));
    }

    public function test_hq_team_leadership_does_not_grant_or_remove_web_permissions(): void
    {
        $operationsAdmin = $this->account('2024035500', 1);
        $chair = $this->account('CHAIR-0001', 2);
        app(HeadquartersTeamSupport::class)->backfill();

        $teamId = (int) DB::table('rescue_teams')->where('team_code', 'HQCC')->value('team_id');
        $chairResponderId = (int) DB::table('responders')->where('user_id', $chair->user_id)->value('responder_id');
        DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => $chairResponderId]);
        app(HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, $chairResponderId);

        $policy = app(WebAccessPolicy::class);
        $this->assertSame('chair', $chair->fresh()->roleKey());
        $this->assertTrue($policy->allows($chair->fresh(), 'resources_requests', 'approve'));
        $this->assertSame('operations_admin', $operationsAdmin->fresh()->roleKey());
        $this->assertTrue($operationsAdmin->fresh()->canManagePersonnel());
        $this->assertTrue($policy->allows($operationsAdmin->fresh(), 'response_operations'));
        $this->assertTrue($policy->allows($operationsAdmin->fresh(), 'resources_requests', 'approve'));
    }
}
