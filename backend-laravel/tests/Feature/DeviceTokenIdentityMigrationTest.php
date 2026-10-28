<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeviceTokenIdentityMigrationTest extends TestCase
{
    public function test_migration_adds_device_identity_fields_without_dropping_existing_device_rows(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('device_uuid', 150);
        });
        DB::table('device_tokens')->insert(['id' => 7, 'device_uuid' => 'existing-phone']);

        $migration = require database_path('migrations/2026_10_28_000002_add_device_token_identity_fields.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('device_tokens', 'household_id'));
        $this->assertTrue(Schema::hasColumn('device_tokens', 'user_id'));
        $this->assertTrue(Schema::hasColumn('device_tokens', 'player_id'));
        $this->assertDatabaseHas('device_tokens', [
            'id' => 7,
            'device_uuid' => 'existing-phone',
            'household_id' => null,
            'user_id' => null,
            'player_id' => null,
        ]);
    }
}
