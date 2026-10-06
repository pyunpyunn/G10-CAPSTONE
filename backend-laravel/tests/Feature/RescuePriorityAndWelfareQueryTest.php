<?php

namespace Tests\Feature;

use App\Queries\RescuePriorityQuery;
use App\Queries\RescueCriteriaQuery;
use App\Queries\RescueCriteriaHistoryQuery;
use App\Queries\SitioPriorityQuery;
use App\Queries\HouseholdStatusQuery;
use App\Queries\WelfareCheckQuery;
use App\Services\Shared\BarangayProfileService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RescuePriorityAndWelfareQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->string('purok_sitio');
            $table->integer('barangay_id')->nullable();
            $table->integer('purok_id')->nullable();
            $table->integer('sitio_id')->nullable();
            $table->string('full_address')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('sitios', function (Blueprint $table): void {
            $table->integer('sitio_id')->primary();
            $table->integer('barangay_id');
            $table->string('sitio_name');
        });
        Schema::create('puroks', function (Blueprint $table): void {
            $table->integer('purok_id')->primary();
            $table->integer('sitio_id')->nullable();
            $table->string('purok_name');
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->integer('address_id');
            $table->string('household_code')->nullable();
            $table->string('household_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('member_count')->default(0);
            $table->timestamp('created_at')->default('2020-01-01 00:00:00');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->integer('household_disaster_id')->primary();
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('current_status_id')->nullable();
            $table->boolean('needs_dispatch')->default(false);
            $table->timestamp('last_reported_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status_key');
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_senior')->default(false);
            $table->boolean('is_pregnant')->default(false);
            $table->timestamp('created_at')->default('2020-01-01 00:00:00');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('member_vulnerable_groups', function (Blueprint $table): void {
            $table->string('member_id');
            $table->integer('vulnerable_group_id');
        });
        Schema::create('member_disaster_statuses', function (Blueprint $table): void {
            $table->string('member_id');
            $table->string('disaster_id');
            $table->integer('status_id')->nullable();
        });
        Schema::create('member_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status_key');
        });
        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->increments('status_log_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->integer('status_id');
            $table->timestamp('submitted_at');
        });
        Schema::create('member_status_logs', function (Blueprint $table): void {
            $table->increments('member_status_log_id');
            $table->string('disaster_id');
            $table->string('member_id');
            $table->integer('to_status_id');
            $table->timestamp('reported_at');
        });
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->string('household_id');
            $table->boolean('is_active');
        });
        Schema::create('trusted_households', function (Blueprint $table): void {
            $table->string('requesting_household_id');
            $table->string('trusted_household_id');
            $table->string('validation_status');
        });
        Schema::create('geotagged_locations', function (Blueprint $table): void {
            $table->integer('location_id')->primary();
            $table->string('household_id');
        });
        Schema::create('rescue_priority_settings', function (Blueprint $table): void {
            $table->increments('version');
            $table->integer('impact_weight');
            $table->integer('vulnerability_weight');
            $table->integer('unreported_weight');
            $table->integer('no_contact_weight');
        });
        Schema::create('rescue_criteria_snapshots', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('event_id');
            $table->timestamp('observed_at');
            $table->integer('definition_version');
            $table->decimal('impact', 5, 1);
            $table->decimal('special_needs', 5, 1);
            $table->decimal('unreported', 5, 1);
            $table->decimal('no_contact', 5, 1);
            $table->integer('purok_count');
        });
        Schema::create('rescue_criteria_line_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
            $table->unsignedInteger('bucket_hour');
            $table->timestamp('observed_at');
            foreach (['impact_open', 'household_total', 'special_open', 'vulnerable_total',
                'unreported_open', 'member_total', 'no_contact_open', 'no_contact_total'] as $column) {
                $table->unsignedInteger($column);
            }
            $table->timestamps();
            $table->unique(['event_id', 'bucket_hour']);
        });
        Schema::create('rescue_criteria_contact_baselines', function (Blueprint $table): void {
            $table->string('event_id');
            $table->string('household_id');
            $table->boolean('no_contact');
            $table->timestamp('captured_at');
            $table->primary(['event_id', 'household_id']);
        });
        DB::table('rescue_priority_settings')->insert([
            'impact_weight' => 30, 'vulnerability_weight' => 30,
            'unreported_weight' => 25, 'no_contact_weight' => 15,
        ]);
        DB::table('addresses')->insert([
            ['address_id' => 1, 'purok_sitio' => 'Purok A', 'barangay_id' => 1, 'full_address' => 'Purok A'],
            ['address_id' => 2, 'purok_sitio' => 'Purok B', 'barangay_id' => 2, 'full_address' => 'Purok B'],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-1', 'address_id' => 1, 'household_name' => 'First', 'member_count' => 1, 'contact_number' => null],
            ['household_id' => 'HH-2', 'address_id' => 2, 'household_name' => 'Second', 'member_count' => 1, 'contact_number' => '09170000000'],
        ]);
        DB::table('household_members')->insert([
            ['member_id' => 'M-1', 'household_id' => 'HH-1', 'is_pwd' => 1],
            ['member_id' => 'M-2', 'household_id' => 'HH-2', 'is_pwd' => 0],
        ]);
        DB::table('household_statuses')->insert(['status_id' => 1, 'status_key' => 'needs_help']);
        DB::table('household_disasters')->insert(['household_disaster_id' => 1, 'household_id' => 'HH-2', 'disaster_id' => 'EVT-1', 'current_status_id' => 1, 'needs_dispatch' => 1]);
    }

    public function test_welfare_list_is_derived_from_contact_and_active_event_reports(): void
    {
        $query = app(WelfareCheckQuery::class);
        $this->assertTrue($query->qualifies('HH-1', 'EVT-1'));
        $this->assertFalse($query->qualifies('HH-2', 'EVT-1'));
        $this->assertSame(['HH-1'], $query->households('EVT-1')->getCollection()->pluck('household_id')->all());
    }

    public function test_sitio_priority_groups_linked_addresses_and_keeps_empty_catalogued_sitios(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('sitios')->insert([
            ['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'],
            ['sitio_id' => 11, 'barangay_id' => 1, 'sitio_name' => 'Sitio B'],
        ]);
        DB::table('addresses')->where('address_id', 1)->update(['sitio_id' => 10]);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A1']);
        DB::table('addresses')->where('address_id', 1)->update(['purok_id' => 31]);

        $rows = app(SitioPriorityQuery::class)->ranked('EVT-1');

        $this->assertSame(['Sitio A', 'Sitio B'], array_column($rows, 'sitio'));
        $this->assertSame(1, $rows[0]['households']);
        $this->assertSame(1, $rows[0]['unreported_members']);
        $this->assertSame(70.0, $rows[0]['priority_score']);
        $this->assertSame(0, $rows[1]['households']);
        $this->assertSame('Purok A1', $rows[0]['puroks'][0]['purok_name']);
        $this->assertSame(1, $rows[0]['puroks'][0]['household_count']);
    }

    public function test_household_list_accepts_an_exact_purok_and_sitio_label_when_ids_are_missing(): void
    {
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A1']);
        DB::table('addresses')->where('address_id', 1)->update(['purok_sitio' => 'Purok A1, Sitio A']);
        $query = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id');
        $request = \Illuminate\Http\Request::create('/households', 'GET', ['sitio_id' => 10, 'purok_id' => 31]);

        app(HouseholdStatusQuery::class)->applyListFilters($query, $request);

        $this->assertSame(['HH-1'], $query->pluck('h.household_id')->all());
    }

    public function test_urgent_reports_rank_ahead_of_area_and_contact_score(): void
    {
        $rows = app(RescuePriorityQuery::class)->ranked('EVT-1')
            ->getCollection();

        $this->assertSame(['HH-2', 'HH-1'], $rows->pluck('household_id')->all());
        $this->assertSame(1, (int) $rows->first()->urgent_tier);
        $this->assertSame(1, (int) $rows->last()->no_contact_channel);
    }

    public function test_purok_ranking_and_timeline_use_the_configured_barangay_and_current_definition(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('rescue_criteria_snapshots')->insert([
            'event_id' => 'EVT-1', 'observed_at' => now()->subHour(),
            'definition_version' => 2, 'impact' => 99, 'special_needs' => 99,
            'unreported' => 99, 'no_contact' => 99, 'purok_count' => 2,
        ]);

        $query = app(RescueCriteriaQuery::class);
        $startedAt = now()->subHours(12);
        $ranking = $query->puroks('EVT-1');

        $this->assertCount(1, $ranking);
        $this->assertSame('Purok A', $ranking[0]['purok']);
        $this->assertSame(1, $ranking[0]['households']);
        $this->assertSame(100.0, $ranking[0]['factors']['special_needs']);
        $this->assertSame(70.0, $ranking[0]['priority_score']);
        $this->assertSame(70.0, round(array_sum($ranking[0]['contributions']), 1));
        $this->assertCount(1, $query->timeline('EVT-1', $startedAt));
    }

    public function test_purok_score_uses_member_weighted_unreported_rate_and_excludes_confirmed_safe_vulnerable_members(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('households')->insert([
            'household_id' => 'HH-3', 'address_id' => 1,
            'household_name' => 'Third', 'member_count' => 3,
            'contact_number' => '09171111111',
        ]);
        DB::table('household_members')->insert([
            ['member_id' => 'M-3', 'household_id' => 'HH-3'],
            ['member_id' => 'M-4', 'household_id' => 'HH-3'],
            ['member_id' => 'M-5', 'household_id' => 'HH-3'],
        ]);
        DB::table('member_statuses')->insert(['status_id' => 2, 'status_key' => 'safe']);
        DB::table('member_disaster_statuses')->insert([
            ['member_id' => 'M-3', 'disaster_id' => 'EVT-1', 'status_id' => 2],
            ['member_id' => 'M-4', 'disaster_id' => 'EVT-1', 'status_id' => 2],
            ['member_id' => 'M-5', 'disaster_id' => 'EVT-1', 'status_id' => 2],
        ]);

        $query = app(RescueCriteriaQuery::class);
        $ranking = $query->puroks('EVT-1');

        $this->assertSame(2, $ranking[0]['households']);
        $this->assertSame(25.0, $ranking[0]['factors']['unreported']);
        $this->assertSame(43.8, $ranking[0]['priority_score']);

        DB::table('member_disaster_statuses')->insert(['member_id' => 'M-1', 'disaster_id' => 'EVT-1', 'status_id' => 2]);
        $ranking = $query->puroks('EVT-1');
        $this->assertSame(0.0, $ranking[0]['factors']['special_needs']);
        $this->assertSame(0.0, $ranking[0]['factors']['unreported']);
        $this->assertSame(50.0, $ranking[0]['factors']['no_contact']);
        $this->assertSame(7.5, $ranking[0]['priority_score']);
    }

    public function test_timeline_changes_at_recorded_report_times_and_extends_to_current_time(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        $startedAt = now()->subHours(4)->startOfHour();
        DB::table('household_statuses')->insert(['status_id' => 2, 'status_key' => 'unsafe']);
        DB::table('member_statuses')->insert(['status_id' => 2, 'status_key' => 'safe']);
        DB::table('household_status_logs')->insert([
            'disaster_id' => 'EVT-1', 'household_id' => 'HH-1', 'status_id' => 2,
            'submitted_at' => $startedAt->copy()->addHour(),
        ]);
        DB::table('member_status_logs')->insert([
            'disaster_id' => 'EVT-1', 'member_id' => 'M-1', 'to_status_id' => 2,
            'reported_at' => $startedAt->copy()->addHours(2),
        ]);
        DB::table('household_disasters')->insert([
            'household_disaster_id' => 2, 'household_id' => 'HH-1',
            'disaster_id' => 'EVT-1', 'current_status_id' => 2, 'needs_dispatch' => 1,
        ]);
        DB::table('member_disaster_statuses')->insert(['member_id' => 'M-1', 'disaster_id' => 'EVT-1', 'status_id' => 2]);

        Schema::drop('rescue_priority_settings'); // The four unweighted graph lines must not depend on ranking settings.
        $history = app(RescueCriteriaHistoryQuery::class);
        $history->refresh('EVT-1', $startedAt);
        $points = $history->forEvent('EVT-1', $startedAt);

        $this->assertCount(2, $points);
        $this->assertSame([0, 3], array_column($points, 'hours_since_alert'));
        $this->assertSame(0.0, $points[0]['impact']);
        $this->assertSame(100.0, $points[1]['impact']);
        $this->assertSame(100.0, $points[0]['unreported']);
        $this->assertSame(0.0, $points[1]['unreported']);
        $this->assertSame(0.0, $points[1]['special_needs']);
        $this->assertSame(0.0, $points[1]['no_contact']);
        $this->assertSame(100.0, $points[0]['no_contact']);

        DB::table('households')->where('household_id', 'HH-1')->update(['contact_number' => '09170000001']);
        $history->refresh('EVT-1', $startedAt);
        $this->assertSame(100.0, $history->forEvent('EVT-1', $startedAt)[0]['no_contact']);
    }
}
