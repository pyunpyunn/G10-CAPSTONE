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
            $table->date('birth_date')->nullable();
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
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
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
            $table->decimal('impact', 5, 1)->nullable();
            $table->decimal('special_needs', 5, 1)->nullable();
            $table->decimal('unreported', 5, 1)->nullable();
            $table->decimal('no_contact', 5, 1)->nullable();
            $table->integer('purok_count');
            $table->unique(['event_id', 'observed_at']);
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

    public function test_status_stack_uses_latest_valid_event_reports_and_matches_purok_totals(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A1']);
        DB::table('addresses')->where('address_id', 1)->update(['sitio_id' => 10, 'purok_id' => 31]);
        // A different barangay must never inflate this sitio's status stack.
        DB::table('addresses')->where('address_id', 2)->update(['sitio_id' => 10]);
        DB::table('household_statuses')->insert([
            ['status_id' => 2, 'status_key' => 'safe'],
            ['status_id' => 3, 'status_key' => 'evacuated'],
            ['status_id' => 4, 'status_key' => 'unchecked'],
        ]);
        foreach (['HH-3', 'HH-4', 'HH-5', 'HH-deleted'] as $id) {
            DB::table('households')->insert(['household_id' => $id, 'address_id' => 1,
                'deleted_at' => $id === 'HH-deleted' ? '2026-10-01 00:00:00' : null]);
        }
        foreach ([
            ['HH-1', 'EVT-1', 1, '2026-10-14 08:00:00'],
            ['HH-1', 'EVT-1', 2, '2026-10-14 09:00:00'],
            ['HH-1', 'EVT-1', 3, '2026-10-14 09:00:00'],
            ['HH-1', 'EVT-1', 4, '2026-10-14 10:00:00'],
            ['HH-3', 'EVT-1', 2, '2026-10-14 09:00:00'],
            ['HH-4', 'EVT-1', 1, '2026-10-14 09:00:00'],
            ['HH-5', 'EVT-old', 2, '2026-10-14 11:00:00'],
            ['HH-deleted', 'EVT-1', 2, '2026-10-14 09:00:00'],
            ['HH-2', 'EVT-1', 2, '2026-10-14 09:00:00'],
        ] as [$id, $event, $status, $time]) {
            DB::table('household_status_logs')->insert(['household_id' => $id,
                'disaster_id' => $event, 'status_id' => $status, 'submitted_at' => $time]);
        }
        $row = app(SitioPriorityQuery::class)->ranked('EVT-1')[0];
        $this->assertSame(4, $row['households']);
        $expected = ['unsafe' => 1, 'unchecked' => 1, 'evacuated' => 1, 'safe' => 1];
        $this->assertSame($expected, $row['status_counts']);
        $this->assertSame($expected, $row['puroks'][0]['status_counts']);
        $reports = \App\Queries\HouseholdEventStatusQuery::latestReports('EVT-1')->get()->keyBy('household_id');
        $this->assertSame(3, (int) $reports['HH-1']->status_id);
        $this->assertFalse($reports->has('HH-5'));
        $this->assertTrue(\App\Queries\HouseholdEventStatusQuery::latestReports(null)->get()->isEmpty());
    }

    public function test_household_list_filters_the_latest_valid_report_in_the_current_barangay(): void
    {
        $this->registerFixturePuroks();
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        Schema::table('households', function (Blueprint $table): void {
            $table->string('household_number')->nullable();
            $table->string('emergency_contact')->nullable();
        });
        Schema::table('household_statuses', fn (Blueprint $table) => $table->string('status_label')->nullable());
        Schema::table('household_disasters', fn (Blueprint $table) => $table->string('priority_level')->nullable());
        Schema::table('household_status_logs', function (Blueprint $table): void {
            foreach (['source', 'notes', 'submitted_by_user_id'] as $column) $table->string($column)->nullable();
            foreach (['latitude', 'longitude', 'battery_level'] as $column) $table->float($column)->nullable();
        });
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->integer('battery_level')->nullable();
            foreach (['last_seen_at', 'logged_at', 'updated_at', 'created_at'] as $column) $table->timestamp($column)->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            foreach (['name', 'first_name', 'last_name'] as $column) $table->string($column)->nullable();
        });
        DB::table('household_statuses')->insert(['status_id' => 2, 'status_key' => 'safe']);
        DB::table('household_status_logs')->insert([
            ['household_id' => 'HH-1', 'disaster_id' => 'EVT-1', 'status_id' => 1, 'submitted_at' => '2026-10-14 08:00:00'],
            ['household_id' => 'HH-1', 'disaster_id' => 'EVT-1', 'status_id' => 2, 'submitted_at' => '2026-10-14 09:00:00'],
            ['household_id' => 'HH-1', 'disaster_id' => 'EVT-old', 'status_id' => 1, 'submitted_at' => '2026-10-14 10:00:00'],
        ]);
        $service = app(HouseholdStatusQuery::class);
        $query = $service->householdListQuery('EVT-1');
        $service->applyListFilters($query, \Illuminate\Http\Request::create('/households', 'GET', ['status' => 'safe_only']));
        $rows = $query->get();
        $this->assertSame(['HH-1'], $rows->pluck('household_id')->all());
        $this->assertSame('safe', $rows[0]->status_key);
        $this->assertSame('2026-10-14 09:00:00', $rows[0]->last_reported_at);
        $query = $service->householdListQuery('EVT-1');
        $service->applyListFilters($query, \Illuminate\Http\Request::create('/households', 'GET', ['status' => 'unsafe']));
        $this->assertTrue($query->get()->isEmpty());
        $this->assertNull($service->householdListQuery(null)->first()->current_status_id);
    }

    public function test_standby_has_no_status_history_or_latest_event_logs(): void
    {
        $query = app(HouseholdStatusQuery::class);
        $this->assertTrue($query->statusLogs('HH-1', null)->isEmpty());
        $this->assertTrue($query->getLatestLogs(collect(['HH-1']), null)->isEmpty());
    }

    public function test_sitio_filter_excludes_addresses_without_registered_sitio_or_purok_ids(): void
    {
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A1']);
        DB::table('addresses')->where('address_id', 1)->update(['purok_sitio' => 'Purok A1, Sitio A']);
        $query = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id');
        app(HouseholdStatusQuery::class)->applyListFilters($query,
            \Illuminate\Http\Request::create('/households', 'GET', ['sitio_id' => 10]));
        $this->assertSame([], $query->pluck('h.household_id')->all());
    }

    public function test_sitio_and_purok_ids_filter_only_their_registered_households(): void
    {
        DB::table('sitios')->insert([
            ['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'],
            ['sitio_id' => 11, 'barangay_id' => 1, 'sitio_name' => 'Sitio B'],
        ]);
        DB::table('puroks')->insert([
            ['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok 1'],
            ['purok_id' => 32, 'sitio_id' => 11, 'purok_name' => 'Purok 1'],
            ['purok_id' => 33, 'sitio_id' => 10, 'purok_name' => 'Purok 2'],
        ]);
        DB::table('addresses')->where('address_id', 1)->update(['sitio_id' => 10, 'purok_id' => 31]);
        DB::table('addresses')->where('address_id', 2)->update(['sitio_id' => 11, 'purok_id' => 32, 'barangay_id' => 1]);
        DB::table('addresses')->insert([
            ['address_id' => 3, 'sitio_id' => 10, 'purok_id' => 33, 'barangay_id' => 1, 'purok_sitio' => 'Purok 2'],
            ['address_id' => 4, 'sitio_id' => null, 'purok_id' => null, 'barangay_id' => 1, 'purok_sitio' => 'Purok 1, Sitio A'],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-3', 'address_id' => 3],
            ['household_id' => 'HH-4', 'address_id' => 4],
        ]);
        foreach ([
            [['sitio_id' => 10], ['HH-1', 'HH-3']],
            [['sitio_id' => 10, 'purok_id' => 31], ['HH-1']],
            [['sitio_id' => 10, 'purok_id' => 33], ['HH-3']],
            [['sitio_id' => 10, 'purok_id' => 32], []],
            [['purok_id' => 32], ['HH-2']],
            [['sitio_id' => 'invalid'], []],
        ] as [$filters, $expected]) {
            $query = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id');
            app(HouseholdStatusQuery::class)->applyListFilters($query, \Illuminate\Http\Request::create('/', 'GET', $filters));
            $this->assertSame($expected, $query->orderBy('h.household_id')->pluck('h.household_id')->all());
        }
    }

    public function test_list_scope_returns_registry_names_and_puroks_without_an_active_event(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Registered Sitio']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Registered Purok']);
        $scope = app(HouseholdStatusQuery::class)->listScope(\Illuminate\Http\Request::create('/', 'GET', [
            'sitio_id' => 10, 'purok_id' => 31, 'sitio' => 'Incorrect URL title',
        ]));
        $this->assertSame(['sitio_id' => 10, 'sitio_name' => 'Registered Sitio'], $scope['sitio']);
        $this->assertSame(['purok_id' => 31, 'purok_name' => 'Registered Purok'], $scope['purok']);
        $this->assertSame([$scope['purok']], $scope['puroks']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(HouseholdStatusQuery::class)->listScope(\Illuminate\Http\Request::create('/', 'GET', ['sitio_id' => 11, 'purok_id' => 31]));
    }

    public function test_dispatch_areas_keep_same_named_puroks_separate_and_use_the_current_barangay(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        Schema::table('addresses', fn (Blueprint $table) => $table->string('house_number')->nullable());
        Schema::table('household_statuses', fn (Blueprint $table) => $table->string('status_label')->nullable());
        Schema::table('household_disasters', function (Blueprint $table): void {
            $table->string('priority_level')->nullable();
            $table->integer('last_battery_level')->nullable();
        });
        Schema::table('geotagged_locations', function (Blueprint $table): void {
            $table->string('location_label')->nullable();
            $table->timestamps();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            foreach (['disaster_id', 'status', 'household_id', 'route_notes'] as $column) $table->string($column)->nullable();
        });
        DB::table('sitios')->insert([
            ['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'],
            ['sitio_id' => 11, 'barangay_id' => 1, 'sitio_name' => 'Sitio B'],
        ]);
        DB::table('puroks')->insert([
            ['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok 1'],
            ['purok_id' => 32, 'sitio_id' => 11, 'purok_name' => 'Purok 1'],
        ]);
        DB::table('addresses')->where('address_id', 1)->update(['purok_id' => 31, 'sitio_id' => 10]);
        DB::table('addresses')->insert(['address_id' => 3, 'barangay_id' => 1, 'purok_id' => 32, 'sitio_id' => 11, 'purok_sitio' => 'Purok 1']);
        DB::table('households')->insert(['household_id' => 'HH-3', 'address_id' => 3]);
        DB::table('household_status_logs')->insert(['household_id' => 'HH-1', 'disaster_id' => 'EVT-1', 'status_id' => 1, 'submitted_at' => '2026-10-14 09:00:00']);
        $areas = app(\App\Queries\RescueDispatchQuery::class)->riskAreas('EVT-1')->keyBy('purok_id');
        $this->assertCount(2, $areas);
        $this->assertSame('Purok 1, Sitio A', $areas[31]['area_name']);
        $this->assertSame('Purok 1, Sitio B', $areas[32]['area_name']);
        $this->assertSame(['HH-1'], $areas[31]['households']->pluck('household_id')->all());
        $this->assertSame(['HH-3'], $areas[32]['households']->pluck('household_id')->all());
        $this->assertSame(1, $areas[31]['unsafe_households']);
        $this->assertNotEquals($areas[31]['id'], $areas[32]['id']);
    }

    public function test_welfare_list_is_derived_from_contact_and_active_event_reports(): void
    {
        $this->registerFixturePuroks();
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

    public function test_sitio_priority_uses_weighted_population_rates_and_requires_both_mobile_and_geotag(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('rescue_priority_settings')->update([
            'impact_weight' => 10, 'vulnerability_weight' => 50,
            'unreported_weight' => 25, 'no_contact_weight' => 15,
        ]);
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A']);
        DB::table('addresses')->where('address_id', 1)->update(['sitio_id' => 10, 'purok_id' => 31]);
        DB::table('households')->insert([
            'household_id' => 'HH-3', 'address_id' => 1, 'household_name' => 'Third', 'member_count' => 3,
        ]);
        DB::table('households')->insert([
            'household_id' => 'HH-4', 'address_id' => 1, 'household_name' => 'Fourth', 'member_count' => 0,
        ]);
        DB::table('household_members')->insert([
            ['member_id' => 'M-3', 'household_id' => 'HH-3', 'is_pwd' => 1],
            ['member_id' => 'M-4', 'household_id' => 'HH-3', 'is_pwd' => 0],
            ['member_id' => 'M-5', 'household_id' => 'HH-3', 'is_pwd' => 0],
        ]);
        DB::table('household_disasters')->insert([
            'household_disaster_id' => 2, 'household_id' => 'HH-1', 'disaster_id' => 'EVT-1',
            'current_status_id' => 1,
        ]);
        DB::table('member_statuses')->insert(['status_id' => 2, 'status_key' => 'safe']);
        DB::table('member_disaster_statuses')->insert([
            ['member_id' => 'M-3', 'disaster_id' => 'EVT-1', 'status_id' => 2],
            ['member_id' => 'M-4', 'disaster_id' => 'EVT-1', 'status_id' => 2],
        ]);
        DB::table('device_tokens')->insert(['household_id' => 'HH-3', 'is_active' => 1]);
        DB::table('geotagged_locations')->insert([
            'location_id' => 1, 'household_id' => 'HH-4', 'latitude' => 10.29, 'longitude' => 123.88,
        ]);

        $row = app(SitioPriorityQuery::class)->ranked('EVT-1')[0];

        $this->assertSame(3, $row['households']);
        $this->assertSame(2, $row['special_needs_members']);
        $this->assertSame(2, $row['unchecked_members']);
        $this->assertSame(1, $row['no_contact_households']);
        $this->assertSame(42.5, $row['priority_score']);
        $this->assertSame([
            'impact' => 10.0,
            'special_needs' => 15.0,
            'unreported' => 12.5,
            'no_contact' => 5.0,
        ], $row['contributions']);
    }

    public function test_sitio_priority_excludes_households_with_only_address_labels(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A1']);
        DB::table('addresses')->insert([
            'address_id' => 3, 'purok_sitio' => 'Purok 5', 'barangay_id' => 1,
        ]);
        DB::table('addresses')->insert([
            'address_id' => 4, 'purok_sitio' => 'Purok A1, Sitio A', 'barangay_id' => 1,
        ]);
        DB::table('households')->insert([
            'household_id' => 'HH-3', 'address_id' => 3, 'household_name' => 'Third', 'member_count' => 1,
        ]);
        DB::table('households')->insert([
            'household_id' => 'HH-4', 'address_id' => 4, 'household_name' => 'Fourth', 'member_count' => 1,
        ]);

        $rows = app(SitioPriorityQuery::class)->ranked('EVT-1');
        $sitioNames = array_column($rows, 'sitio');

        $this->assertSame(['Sitio A'], $sitioNames);
        $this->assertSame(10, $rows[0]['sitio_id']);
        $this->assertSame(0, $rows[0]['households']);
    }

    public function test_household_list_excludes_name_only_addresses_when_purok_id_is_requested(): void
    {
        DB::table('sitios')->insert(['sitio_id' => 10, 'barangay_id' => 1, 'sitio_name' => 'Sitio A']);
        DB::table('puroks')->insert(['purok_id' => 31, 'sitio_id' => 10, 'purok_name' => 'Purok A1']);
        DB::table('addresses')->where('address_id', 1)->update(['purok_sitio' => 'Purok A1, Sitio A']);
        $query = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id');
        $request = \Illuminate\Http\Request::create('/households', 'GET', ['sitio_id' => 10, 'purok_id' => 31]);

        app(HouseholdStatusQuery::class)->applyListFilters($query, $request);

        $this->assertSame([], $query->pluck('h.household_id')->all());
    }

    public function test_urgent_reports_rank_ahead_of_area_and_contact_score(): void
    {
        $this->registerFixturePuroks();
        DB::table('sitios')->where('sitio_id', 902)->update(['barangay_id' => 1]);
        $rows = app(RescuePriorityQuery::class)->ranked('EVT-1')
            ->getCollection();

        $this->assertSame(['HH-2', 'HH-1'], $rows->pluck('household_id')->all());
        $this->assertSame(1, (int) $rows->first()->urgent_tier);
        $this->assertSame(1, (int) $rows->last()->no_contact_channel);
    }

    public function test_purok_ranking_and_timeline_use_the_configured_barangay_and_current_definition(): void
    {
        $this->registerFixturePuroks();
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
        $points = $query->timeline('EVT-1', $startedAt);
        $this->assertCount(6, $points);
        $this->assertSame(0, $points[0]['hours_since_alert']);
    }

    public function test_purok_score_uses_member_weighted_unreported_rate_and_excludes_confirmed_safe_vulnerable_members(): void
    {
        $this->registerFixturePuroks();
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
        $this->assertSame(51.3, $ranking[0]['priority_score']);

        DB::table('member_disaster_statuses')->insert(['member_id' => 'M-1', 'disaster_id' => 'EVT-1', 'status_id' => 2]);
        $ranking = $query->puroks('EVT-1');
        $this->assertSame(0.0, $ranking[0]['factors']['special_needs']);
        $this->assertSame(0.0, $ranking[0]['factors']['unreported']);
        $this->assertSame(100.0, $ranking[0]['factors']['no_contact']);
        $this->assertSame(15.0, $ranking[0]['priority_score']);
    }

    public function test_timeline_changes_at_recorded_report_times_and_extends_to_current_time(): void
    {
        $this->registerFixturePuroks();
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

    public function test_live_no_contact_share_uses_households_without_any_registered_contact_channel(): void
    {
        $this->registerFixturePuroks();
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });

        $query = app(RescueCriteriaQuery::class);
        $startedAt = now()->subHour();
        $this->assertSame(100.0, $query->current('EVT-1', $startedAt)['no_contact']);

        DB::table('device_tokens')->insert(['household_id' => 'HH-1', 'is_active' => 1]);

        $this->assertNull($query->current('EVT-1', $startedAt)['no_contact']);

        DB::table('device_tokens')->where('household_id', 'HH-1')->update(['is_active' => 0]);
        $this->assertSame(100.0, $query->current('EVT-1', $startedAt)['no_contact']);
        DB::table('households')->where('household_id', 'HH-2')->update(['contact_number' => null]);
        DB::table('trusted_households')->insert([
            'requesting_household_id' => 'HH-2', 'trusted_household_id' => 'HH-1', 'validation_status' => 'validated',
        ]);
        DB::table('device_tokens')->insert(['household_id' => 'HH-2', 'is_active' => 1]);
        $this->assertNull($query->current('EVT-1', $startedAt)['no_contact']);
    }

    public function test_event_keeps_its_weight_version_after_new_settings_are_created(): void
    {
        $this->registerFixturePuroks();
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->timestamp('started_at')->nullable();
            $table->unsignedBigInteger('rescue_priority_version')->nullable();
        });
        Schema::table('rescue_criteria_line_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('rescue_priority_version')->nullable();
        });
        $startedAt = now()->subHour();
        DB::table('disaster_events')->insert([
            'event_id' => 'EVT-1', 'started_at' => $startedAt, 'rescue_priority_version' => 1,
        ]);
        DB::table('household_members')->insert([
            'member_id' => 'M-6', 'household_id' => 'HH-1', 'birth_date' => '2015-01-01',
        ]);
        DB::table('rescue_priority_settings')->insert([
            'impact_weight' => 20, 'vulnerability_weight' => 35,
            'unreported_weight' => 30, 'no_contact_weight' => 15,
        ]);

        $this->assertSame(1, (int) app(RescuePriorityQuery::class)->settingsForEvent('EVT-1')->version);

        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        $this->assertSame(2, app(RescueCriteriaQuery::class)->puroks('EVT-1')[0]['total_vulnerable_members']);
        app(RescueCriteriaHistoryQuery::class)->refresh('EVT-1', $startedAt);

        $this->assertSame(1, (int) DB::table('rescue_criteria_line_snapshots')->value('rescue_priority_version'));
        $this->assertSame(2, (int) DB::table('rescue_criteria_line_snapshots')->value('vulnerable_total'));
    }

    public function test_live_timeline_saves_only_changed_measurements(): void
    {
        $this->registerFixturePuroks();
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });

        $startedAt = now()->subHours(3);
        $query = app(RescueCriteriaQuery::class);
        $query->timeline('EVT-1', $startedAt);
        $this->assertSame(2, DB::table('rescue_criteria_snapshots')->where('event_id', 'EVT-1')->count());

        DB::table('household_disasters')->insert([
            'household_disaster_id' => 2, 'household_id' => 'HH-1', 'disaster_id' => 'EVT-1',
            'current_status_id' => 1, 'needs_dispatch' => 0,
        ]);
        $this->assertSame(100.0, $query->current('EVT-1', $startedAt)['impact']);
        $query->timeline('EVT-1', $startedAt);
        $this->assertSame(2, DB::table('rescue_criteria_snapshots')->where('event_id', 'EVT-1')->count());
        $this->assertSame(100.0, (float) DB::table('rescue_criteria_snapshots')->where('event_id', 'EVT-1')->orderByDesc('observed_at')->value('impact'));

        $query->timeline('EVT-1', $startedAt);
        $this->assertSame(2, DB::table('rescue_criteria_snapshots')->where('event_id', 'EVT-1')->count());
    }

    public function test_empty_registered_population_keeps_null_measurements_and_valid_history(): void
    {
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 99]; }
        });
        $points = app(RescueCriteriaQuery::class)->timeline('EVT-1', now()->subHour());
        foreach (['impact', 'special_needs', 'unreported', 'no_contact'] as $criterion) {
            $this->assertNull(end($points)[$criterion]);
        }
        $this->assertDatabaseHas('rescue_criteria_snapshots', ['event_id' => 'EVT-1', 'impact' => null]);
    }

    public function test_criteria_feed_without_an_event_returns_empty_points_and_default_weight_labels(): void
    {
        $dispatch = \Mockery::mock(\App\Queries\RescueDispatchQuery::class);
        $dispatch->shouldReceive('activeEvent')->once()->andReturn(null);
        $response = app(\App\Http\Controllers\Api\RescueDispatchController::class)->criteriaTimeline(
            app(RescueCriteriaQuery::class), $dispatch, app(RescuePriorityQuery::class),
        );
        $this->assertSame([], $response->getData(true)['data']);
        $this->assertNull($response->getData(true)['meta']['event_id']);
        $this->assertSame(30, $response->getData(true)['meta']['settings']['impact_weight']);
        $this->assertSame('max-age=0, no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_band_settings_and_empty_measurement_migration_preserve_existing_values(): void
    {
        $migration = require database_path('migrations/2026_10_09_000003_version_rescue_bands_and_allow_empty_measurements.php');
        $migration->up();
        $migration->up();
        $settings = app(RescuePriorityQuery::class)->settingsForEvent('EVT-1');
        $this->assertSame(30, $settings->impact_weight);
        $this->assertSame(40.0, $settings->high_percent);
        $settings->high_percent = 60;
        $settings->medium_percent = 30;
        $presenter = app(\App\Presenters\RescueCriteriaPresenter::class);
        $this->assertSame('medium', $presenter->priorityBand(50, 2, $settings)['key']);
        $this->assertSame('low', $presenter->priorityBand(25, 2, $settings)['key']);

        Schema::table('rescue_priority_settings', function (Blueprint $table): void {
            $table->string('updated_by_user_id')->nullable();
            $table->string('change_reason')->nullable();
            $table->timestamps();
        });
        config(['rescue_priority.high_percent' => 90]);
        $next = app(\App\Actions\UpdateRescuePrioritySettings::class)->apply([
            'impact_weight' => 40, 'vulnerability_weight' => 20,
            'unreported_weight' => 25, 'no_contact_weight' => 15,
        ], 'admin-1', 'Use these weights for the next event.');
        $this->assertSame(2, $next->version);
        $this->assertSame(40.0, $next->high_percent);
        $this->assertSame('admin-1', $next->updated_by_user_id);
        $this->assertSame('Use these weights for the next event.', $next->change_reason);
        $this->assertNotNull($next->created_at);
    }

    public function test_live_partial_shares_and_updated_registered_vulnerability_are_not_truncated(): void
    {
        $this->registerFixturePuroks();
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        DB::table('households')->where('household_id', 'HH-2')->update(['address_id' => 1, 'contact_number' => null]);
        DB::table('household_members')->where('member_id', 'M-2')->update(['is_pregnant' => 1]);
        DB::table('member_statuses')->insert(['status_id' => 2, 'status_key' => 'safe']);
        DB::table('member_disaster_statuses')->insert(['member_id' => 'M-2', 'disaster_id' => 'EVT-1', 'status_id' => 2]);
        DB::table('household_disasters')->where('household_id', 'HH-2')->update(['last_reported_at' => now()]);
        $query = app(RescueCriteriaQuery::class);
        $point = $query->current('EVT-1', now()->subHour());
        $this->assertSame(50.0, $point['impact']);
        $this->assertSame(50.0, $point['special_needs']);
        $this->assertSame(50.0, $point['unreported']);
        $this->assertSame(50.0, $point['no_contact']);

        DB::table('household_members')->where('member_id', 'M-1')->update(['is_pwd' => 0]);
        $this->assertSame(0.0, $query->current('EVT-1', now()->subHour())['special_needs']);
        DB::table('household_members')->where('member_id', 'M-1')->update(['is_pregnant' => 1]);
        $this->assertSame(50.0, $query->current('EVT-1', now()->subHour())['special_needs']);
    }
    private function registerFixturePuroks(): void
    {
        DB::table('sitios')->insert([
            ['sitio_id' => 901, 'barangay_id' => 1, 'sitio_name' => 'Registered A'],
            ['sitio_id' => 902, 'barangay_id' => 2, 'sitio_name' => 'Registered B'],
        ]);
        DB::table('puroks')->insert([
            ['purok_id' => 901, 'sitio_id' => 901, 'purok_name' => 'Purok A'],
            ['purok_id' => 902, 'sitio_id' => 902, 'purok_name' => 'Purok B'],
        ]);
        DB::table('addresses')->where('address_id', 1)->update(['purok_id' => 901]);
        DB::table('addresses')->where('address_id', 2)->update(['purok_id' => 902]);
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
    }
}
