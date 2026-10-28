<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestoreResponderUserAccountsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary();
            $table->string('role_key');
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary();
            $table->integer('leader_responder_id')->nullable();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->integer('responder_id')->primary();
            $table->string('user_id');
            $table->string('username');
            $table->string('password_hash');
            $table->string('full_name');
            $table->string('contact_number')->nullable();
            $table->integer('team_id');
            $table->boolean('is_validated')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->integer('role_id');
            $table->string('contact_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['role_id' => 5, 'role_key' => 'tm'],
            ['role_id' => 25, 'role_key' => 'tl'],
        ]);
        DB::table('rescue_teams')->insert([
            ['team_id' => 1, 'leader_responder_id' => 10],
            ['team_id' => 2, 'leader_responder_id' => null],
        ]);
        DB::table('users')->insert([
            'user_id' => 'existing-user',
            'username' => 'existing.rescuer',
            'password' => Hash::make('existing-password'),
            'role_id' => 5,
        ]);
        DB::table('responders')->insert([
            ['responder_id' => 10, 'user_id' => 'team-lead', 'username' => 'team.lead', 'password_hash' => Hash::make('lead-password'),
                'full_name' => 'Riley Santos', 'team_id' => 1, 'is_validated' => true],
            ['responder_id' => 11, 'user_id' => 'team-member', 'username' => 'team.member', 'password_hash' => Hash::make('member-password'),
                'full_name' => 'Jordan Reyes', 'team_id' => 2, 'is_validated' => true],
            ['responder_id' => 12, 'user_id' => 'existing-user', 'username' => 'existing.rescuer', 'password_hash' => Hash::make('different-password'),
                'full_name' => 'Existing Rescuer', 'team_id' => 2, 'is_validated' => true],
        ]);
    }

    public function test_migration_adds_missing_login_rows_without_replacing_existing_credentials(): void
    {
        $migration = require database_path('migrations/2026_10_28_000006_restore_responder_user_accounts.php');

        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('users', 3);
        $this->assertSame(25, DB::table('users')->where('user_id', 'team-lead')->value('role_id'));
        $this->assertSame(5, DB::table('users')->where('user_id', 'team-member')->value('role_id'));
        $this->assertTrue(Hash::check('lead-password', DB::table('users')->where('user_id', 'team-lead')->value('password')));
        $this->assertTrue(Hash::check('member-password', DB::table('users')->where('user_id', 'team-member')->value('password')));
        $this->assertTrue(Hash::check('existing-password', DB::table('users')->where('user_id', 'existing-user')->value('password')));
    }
}
