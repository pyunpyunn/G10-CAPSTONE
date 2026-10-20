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
        $this->assertFalse($policy->allows($operationsAdmin->fresh(), 'response_operations'));
        $this->assertFalse($policy->allows($operationsAdmin->fresh(), 'resources_requests', 'approve'));
    }
}
