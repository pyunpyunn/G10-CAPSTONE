<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationSchemaTest extends TestCase
{
    public function test_navigation_migrations_create_indexed_tables_with_foreign_keys(): void
    {
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->integer('team_id')->primary();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
        });
        Schema::create('evacuation_centers', function (Blueprint $table): void {
            $table->string('evacuation_center_id')->primary();
        });

        Schema::create('device_tokens', function (Blueprint $table): void { $table->bigInteger('id')->primary(); });
        $parentIndexes = require database_path('migrations/2026_10_18_000030_add_navigation_parent_indexes.php');
        $parentIndexes->up();
        $tables = require database_path('migrations/2026_10_18_000031_create_navigation_tables.php');
        $tables->up();
        (require database_path('migrations/2026_10_18_000032_add_navigation_sync_results.php'))->up();
        (require database_path('migrations/2026_10_18_000033_add_offline_pack_format.php'))->up();
        $this->assertTrue(Schema::hasColumn('sync_events', 'result_json'));
        $this->assertTrue(Schema::hasColumn('offline_packs', 'pack_format'));

        foreach (['rescue_teams', 'cached_routes', 'device_tokens', 'dispatch_trips', 'trip_points', 'sync_events', 'offline_packs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to be created.");
        }

        $this->assertTrue(collect(Schema::getIndexes('rescue_teams'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['team_id'],
        ));
        $this->assertTrue(Schema::hasIndex('responder_assignments', 'navigation_assignments_assignment_id_unique'));
        $this->assertTrue(Schema::hasIndex('trip_points', 'trip_points_trip_recorded_at_index'));

        DB::table('rescue_teams')->insert(['team_id' => 1]);
        DB::table('households')->insert(['household_id' => 'HH-1']);
        DB::table('cached_routes')->insert([
            'team_id' => 1,
            'household_id' => 'HH-1',
            'target_key' => 'household:HH-1',
            'profile' => 'auto',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('cached_routes')->insert([
            'team_id' => 1,
            'household_id' => 'HH-1',
            'target_key' => 'household:HH-1',
            'profile' => 'auto',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
