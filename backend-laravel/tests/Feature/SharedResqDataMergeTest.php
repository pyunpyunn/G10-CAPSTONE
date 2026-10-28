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
}
