<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class OperationalQueryIndexesTest extends TestCase
{
    public function test_indexes_are_idempotent_skip_missing_legacy_columns_and_preserve_existing_indexes(): void
    {
        Schema::create('responder_location_logs', function (Blueprint $table) {
            $table->integer('log_id'); $table->integer('responder_id'); $table->timestamp('logged_at');
            $table->index(['responder_id','logged_at'], 'existing_capture_index');
        });
        Schema::create('households', fn (Blueprint $table) => $table->string('household_id'));
        $migration = require database_path('migrations/2026_10_24_000002_add_operational_query_indexes.php');
        $migration->up(); $migration->up();
        $this->assertTrue(Schema::hasIndex('responder_location_logs','op_gps_responder_latest'));
        $this->assertFalse(Schema::hasIndex('responder_location_logs','op_gps_capture_latest'));
        $this->assertFalse(Schema::hasIndex('households','op_household_address'));
        $migration->down();
        $this->assertTrue(Schema::hasIndex('responder_location_logs','existing_capture_index'));
        $this->assertFalse(Schema::hasIndex('responder_location_logs','op_gps_responder_latest'));
    }
}
