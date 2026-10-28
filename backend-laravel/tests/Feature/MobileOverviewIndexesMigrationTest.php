<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileOverviewIndexesMigrationTest extends TestCase
{
    public function test_mobile_overview_indexes_are_added_idempotently_to_matching_tables(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('household_id');
            $table->timestamp('last_seen_at')->nullable();
        });
        Schema::create('trusted_households', function (Blueprint $table): void {
            $table->string('connection_id')->primary();
            $table->string('requesting_household_id');
            $table->string('trusted_household_id');
            $table->string('validation_status');
            $table->timestamp('created_at')->nullable();
        });

        $migration = require database_path('migrations/2026_10_29_000001_add_mobile_overview_indexes.php');
        $migration->up();
        $migration->up();

        $deviceIndexNames = array_column(Schema::getIndexes('device_tokens'), 'name');
        $trustedIndexNames = array_column(Schema::getIndexes('trusted_households'), 'name');

        $this->assertContains('mobile_device_household_seen', $deviceIndexNames);
        $this->assertContains('mobile_trusted_request_created', $trustedIndexNames);
        $this->assertContains('mobile_trusted_pending_created', $trustedIndexNames);
        $this->assertCount(2, Schema::getIndexes('device_tokens'));
        $this->assertCount(3, Schema::getIndexes('trusted_households'));
    }
}
