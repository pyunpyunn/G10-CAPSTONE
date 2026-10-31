<?php

namespace Tests\Feature;

use App\Models\DispatchTrip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedResqModuleUpgradeTest extends TestCase
{
    public function test_catalog_repair_preserves_existing_shared_labels_and_codes(): void
    {
        Schema::create('property_damage_levels', function (Blueprint $t): void {
            $t->string('damage_key')->primary(); $t->string('label'); $t->integer('sort_order'); $t->boolean('is_active');
        });
        DB::table('property_damage_levels')->insert(['damage_key' => 'minor_damage', 'label' => 'Shared damage label', 'sort_order' => 4, 'is_active' => false]);
        DB::table('dispatch_statuses')->where('status_key', 'dispatched')->update(['label' => 'Shared dispatch label', 'status_code' => 'SHARED-001']);
        DB::table('dispatch_statuses')->where('status_key', '!=', 'dispatched')->delete();
        $repair = require database_path('migrations/2026_10_31_000002_reconcile_resq_owned_lookup_catalogs.php');
        $repair->up(); $repair->up();
        $this->assertDatabaseHas('dispatch_statuses', ['status_key' => 'dispatched', 'label' => 'Shared dispatch label', 'status_code' => 'SHARED-001']);
        $this->assertDatabaseHas('property_damage_levels', ['damage_key' => 'minor_damage', 'label' => 'Shared damage label', 'is_active' => false]);
        $this->assertDatabaseCount('property_damage_levels', 5);
        $this->assertDatabaseCount('dispatch_statuses', count(json_decode(file_get_contents(database_path('data/dispatch_statuses.json')), true)));
    }

    public function test_missing_modules_are_added_idempotently_without_retired_duplicate_tables(): void
    {
        Schema::create('resource_requests', fn (Blueprint $t) => $t->string('request_id')->primary());
        Schema::create('situation_reports', fn (Blueprint $t) => $t->integer('sit_rep_id')->primary());
        Schema::create('rescue_priority_settings', fn (Blueprint $t) => $t->id('version'));
        Schema::create('disaster_events', function (Blueprint $t): void {
            $t->string('event_id')->primary();
            $t->timestamp('ended_at')->nullable();
        });
        DB::table('resource_requests')->insert(['request_id' => 'existing-request']);
        $upgrade = require database_path('migrations/2026_10_31_000001_finalize_resq_shared_modules.php');
        $upgrade->up();
        $upgrade->up();
        $this->assertDatabaseHas('resource_requests', ['request_id' => 'existing-request', 'urgency_id' => null]);
        $this->assertDatabaseCount('urgency_levels', 4);
        $this->assertTrue(Schema::hasColumn('rescue_criteria_snapshots', 'definition_version'));
        $this->assertTrue(Schema::hasColumn('external_report_requests', 'sitio_id'));
        $this->assertTrue(Schema::hasTable('resq_orphan_references'));
        foreach (['devices', 'team_bases', 'household_login_accounts', 'household_device_owners', 'household_password_recovery_otps', 'official_advisory_notifications'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' must remain consolidated');
        }
    }

    public function test_trip_device_eager_loading_uses_the_actual_shared_device_identity(): void
    {
        Schema::create('device_tokens', function (Blueprint $t): void { $t->bigInteger('id')->primary(); $t->string('device_uuid'); });
        Schema::create('dispatch_trips', function (Blueprint $t): void { $t->id(); $t->bigInteger('device_id'); });
        DB::table('device_tokens')->insert(['id' => 77, 'device_uuid' => 'registered-device']);
        DB::table('dispatch_trips')->insert(['device_id' => 77]);
        $this->assertSame('registered-device', DispatchTrip::with('device')->sole()->device->device_uuid);
    }
}
