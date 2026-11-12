<?php

namespace Tests\Feature;

use App\Services\Shared\SharedResqDataMerge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedResqDataMergeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.merge_source' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        foreach ([DB::connection(), DB::connection('merge_source')] as $db) {
            $schema = $db->getSchemaBuilder();
            $schema->create('users', function (Blueprint $t): void {
                $t->string('user_id')->primary(); $t->string('household_id')->nullable(); $t->string('member_id')->nullable();
                $t->integer('role_id'); $t->string('password');
            });
            $schema->create('household_members', function (Blueprint $t): void { $t->string('member_id')->primary(); $t->string('household_id'); });
            $schema->create('device_tokens', function (Blueprint $t): void {
                $t->bigInteger('id')->primary();
                foreach (['device_uuid','user_id','household_id','member_id','player_id'] as $c) $t->string($c)->nullable();
                $t->bigInteger('selection_token_id')->nullable(); $t->timestamp('owner_selected_at')->nullable();
            });
            $schema->create('household_statuses', function (Blueprint $t): void { $t->integer('status_id')->primary(); $t->string('status_key'); $t->string('safety_basis')->nullable(); });
            $schema->create('member_statuses', function (Blueprint $t): void {
                $t->integer('status_id')->primary(); $t->string('status_key'); $t->boolean('counts_as_death')->nullable(); $t->boolean('counts_as_injury')->nullable();
            });
            $schema->create('property_damage_levels', function (Blueprint $t): void { $t->string('damage_key')->primary(); $t->string('label'); });
            $schema->create('rescue_teams', function (Blueprint $t): void {
                $t->integer('team_id')->primary(); $t->string('team_code')->nullable()->unique();
            });
            $schema->create('responders', function (Blueprint $t): void {
                $t->integer('responder_id')->primary(); $t->string('user_id')->nullable();
                $t->string('responder_code')->nullable()->unique(); $t->integer('team_id')->nullable();
                $t->string('username')->nullable(); $t->string('password_hash')->nullable();
                $t->string('full_name')->nullable();
            });
            if (! $schema->hasTable('operational_sequences')) {
                $schema->create('operational_sequences', function (Blueprint $t): void {
                    $t->string('sequence_key')->primary(); $t->integer('next_value');
                });
            }
            $schema->create('households', function (Blueprint $t): void {
                $t->string('household_id')->primary(); $t->string('household_name');
            });
        }
        DB::table('users')->insert(['user_id' => 'shared-user', 'household_id' => 'HH-1', 'member_id' => 'original-member', 'role_id' => 6, 'password' => 'shared-hash']);
        DB::connection('merge_source')->table('users')->insert(['user_id' => 'shared-user', 'household_id' => 'HH-1', 'member_id' => 'local-member', 'role_id' => 6, 'password' => 'different-local-hash']);
    }

    public function test_merge_preserves_shared_credentials_and_device_values_and_is_repeatable(): void
    {
        $source = DB::connection('merge_source');
        DB::table('device_tokens')->insert(['id' => 40, 'device_uuid' => 'existing-phone', 'user_id' => 'shared-user', 'player_id' => 'shared-subscription']);
        $source->table('device_tokens')->insert([
            ['id' => 1, 'device_uuid' => 'existing-phone', 'user_id' => 'shared-user', 'player_id' => 'different-local-subscription'],
            ['id' => 2, 'device_uuid' => 'new-phone', 'user_id' => 'shared-user', 'player_id' => 'new-subscription'],
            ['id' => 3, 'device_uuid' => 'unmatched-phone', 'user_id' => 'unknown-user', 'player_id' => 'unmatched-subscription'],
        ]);
        DB::table('property_damage_levels')->insert(['damage_key' => 'minor_damage', 'label' => 'Shared label']);
        $source->table('property_damage_levels')->insert(['damage_key' => 'minor_damage', 'label' => 'Local label']);
        $dry = (new SharedResqDataMerge)->run($source);
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame(1, $dry['deferred']['device_tokens:unmatched_account']);
        (new SharedResqDataMerge)->run($source, true);
        (new SharedResqDataMerge)->run($source, true);
        $this->assertDatabaseCount('device_tokens', 2);
        $this->assertDatabaseHas('users', ['user_id' => 'shared-user', 'member_id' => 'original-member', 'password' => 'shared-hash']);
        $this->assertDatabaseHas('device_tokens', ['id' => 40, 'player_id' => 'shared-subscription']);
        $this->assertDatabaseHas('device_tokens', ['device_uuid' => 'new-phone', 'member_id' => 'original-member', 'player_id' => 'new-subscription']);
        $this->assertDatabaseHas('property_damage_levels', ['damage_key' => 'minor_damage', 'label' => 'Shared label']);
    }

    public function test_merge_adds_responders_and_household_statuses_without_replacing_shared_or_copying_demographics(): void
    {
        $source = DB::connection('merge_source');
        DB::table('rescue_teams')->insert(['team_id' => 10, 'team_code' => 'TEAM-1']);
        $source->table('rescue_teams')->insert(['team_id' => 7, 'team_code' => 'TEAM-1']);
        DB::table('responders')->insert([
            'responder_id' => 1, 'user_id' => 'shared-user', 'responder_code' => 'SHARED-1',
            'team_id' => 10, 'full_name' => 'Shared responder',
        ]);
        $source->table('responders')->insert([
            ['responder_id' => 1, 'user_id' => 'shared-user', 'responder_code' => 'LOCAL-1', 'team_id' => 7, 'full_name' => 'Local responder'],
            ['responder_id' => 2, 'user_id' => 'shared-user', 'responder_code' => 'LOCAL-2', 'team_id' => 7, 'full_name' => 'Another local responder'],
            ['responder_id' => 3, 'user_id' => 'shared-user', 'responder_code' => 'SHARED-1', 'team_id' => 7, 'full_name' => 'Duplicate responder'],
        ]);

        DB::table('household_statuses')->insert(['status_id' => 1, 'status_key' => 'safe', 'safety_basis' => 'shared']);
        $source->table('household_statuses')->insert([
            ['status_id' => 4, 'status_key' => 'safe', 'safety_basis' => 'local'],
            ['status_id' => 5, 'status_key' => 'evacuated', 'safety_basis' => 'unsafe'],
        ]);
        $source->table('households')->insert(['household_id' => 'LOCAL-HH', 'household_name' => 'Local household']);

        $dry = (new SharedResqDataMerge)->run($source);
        $this->assertSame(2, $dry['inserted']['responders']);
        $this->assertDatabaseCount('responders', 1);
        $this->assertDatabaseCount('households', 0);

        (new SharedResqDataMerge)->run($source, true);
        (new SharedResqDataMerge)->run($source, true);

        $this->assertDatabaseCount('responders', 3);
        $this->assertDatabaseHas('responders', [
            'responder_id' => 1, 'responder_code' => 'SHARED-1', 'full_name' => 'Shared responder',
        ]);
        $this->assertDatabaseHas('responders', [
            'responder_id' => 2, 'responder_code' => 'LOCAL-1', 'team_id' => 10,
        ]);
        $this->assertDatabaseHas('responders', [
            'responder_id' => 3, 'responder_code' => 'LOCAL-2', 'team_id' => 10,
        ]);
        $this->assertDatabaseHas('household_statuses', ['status_id' => 1, 'status_key' => 'safe', 'safety_basis' => 'shared']);
        $this->assertDatabaseHas('household_statuses', ['status_id' => 2, 'status_key' => 'evacuated', 'safety_basis' => 'unsafe']);
        $this->assertDatabaseCount('households', 0);
    }
}
