<?php

namespace Tests\Feature;

use App\Queries\DisasterBroadcastQuery;
use App\Services\Web\DisasterBroadcastRequestValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DisasterSeverityCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('disaster_types', function (Blueprint $table): void {
            $table->integer('type_id')->primary();
            $table->string('type_code')->unique();
            $table->string('type_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('severity_levels', function (Blueprint $table): void {
            $table->integer('severity_id')->primary();
            $table->string('severity_key')->unique();
            $table->string('severity_label');
        });

        DB::table('disaster_types')->insert([
            'type_id' => 1,
            'type_code' => 'SUPER_TYPHOON',
            'type_name' => 'Super Typhoon',
            'is_active' => true,
        ]);
    }

    public function test_catalog_migration_is_idempotent_and_associates_type_specific_levels(): void
    {
        $migration = require database_path('migrations/2026_11_01_000001_create_disaster_type_severity_catalog.php');
        $migration->up();
        $migration->up();

        DB::table('severity_levels')->where('severity_key', 'tcws_5_critical')->update(['severity_key' => 'tcws_5']);
        $rankMigration = require database_path('migrations/2026_11_01_000002_rank_disaster_severity_keys.php');
        $rankMigration->up();
        DB::table('severity_levels')->where('severity_key', 'landslide_medium')->update(['severity_key' => 'landslide_medium_medium']);
        $normalizeMigration = require database_path('migrations/2026_11_01_000003_normalize_disaster_severity_keys.php');
        $normalizeMigration->up();

        $this->assertSame(6, DB::table('disaster_types')->count());
        $this->assertDatabaseHas('disaster_types', [
            'type_id' => 1,
            'type_code' => 'TYPHOON',
            'type_name' => 'Typhoon',
        ]);
        $this->assertSame(30, DB::table('severity_levels')->count());
        $this->assertSame(5, DB::table('disaster_type_severity_levels')
            ->where('type_id', 1)
            ->count());
        $this->assertDatabaseHas('severity_levels', [
            'severity_key' => 'tcws_5_critical',
            'severity_label' => 'TCWS 5 - 185 km/h or higher winds',
        ]);
        $this->assertDatabaseHas('severity_levels', ['severity_key' => 'landslide_medium']);

        $volcanoId = DB::table('disaster_types')->where('type_code', 'VOLCANIC_ERUPTION')->value('type_id');
        $this->assertSame(6, DB::table('disaster_type_severity_levels')->where('type_id', $volcanoId)->count());

        $types = app(DisasterBroadcastQuery::class)->getDisasterTypes();
        $typhoon = collect($types)->firstWhere('type_code', 'TYPHOON');
        $this->assertCount(5, $typhoon['severity_ids']);
    }

    public function test_event_and_broadcast_accept_missing_severity_but_reject_another_types_level(): void
    {
        $migration = require database_path('migrations/2026_11_01_000001_create_disaster_type_severity_catalog.php');
        $migration->up();
        $types = DB::table('disaster_types')->pluck('type_id', 'type_code');
        $validator = app(DisasterBroadcastRequestValidator::class);

        $event = $validator->validateEvent(Request::create('/events', 'POST', [
            'name' => 'Typhoon response',
            'type_id' => $types['TYPHOON'],
        ]));
        $this->assertArrayNotHasKey('severity_level_id', $event);

        $statusKeys = collect(app(\App\Presenters\DisasterBroadcastPresenter::class)->statusOptions())
            ->take(4)
            ->pluck('key')
            ->all();
        $broadcast = $validator->validateBroadcast(Request::create('/broadcasts', 'POST', [
            'broadcast_title' => 'Safety advisory',
            'message' => 'Follow official instructions.',
            'scope_type' => 'barangay_wide',
            'estimated_duration' => '12 hours',
            'allowed_statuses' => $statusKeys,
        ]), (int) $types['TYPHOON']);
        $this->assertArrayNotHasKey('severity_id', $broadcast);

        $earthquakeSeverityId = DB::table('severity_levels')->where('severity_key', 'peis_i_low')->value('severity_id');
        $this->expectException(ValidationException::class);
        $validator->validateEvent(Request::create('/events', 'POST', [
            'name' => 'Typhoon response',
            'type_id' => $types['TYPHOON'],
            'severity_level_id' => $earthquakeSeverityId,
        ]));
    }
}
