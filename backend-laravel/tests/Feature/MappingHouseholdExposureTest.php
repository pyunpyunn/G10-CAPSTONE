<?php

namespace Tests\Feature;

use App\Queries\MappingQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MappingHouseholdExposureTest extends TestCase
{
    public function test_active_event_reports_are_aggregated_by_registered_purok_and_status(): void
    {
        Schema::create('sitios', function (Blueprint $table): void {
            $table->integer('sitio_id')->primary();
            $table->integer('barangay_id');
            $table->string('sitio_name');
        });
        Schema::create('puroks', function (Blueprint $table): void {
            $table->integer('purok_id')->primary();
            $table->integer('sitio_id');
            $table->string('purok_name');
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->integer('purok_id');
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->integer('address_id');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary();
            $table->string('status_key');
            $table->string('status_label');
        });
        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->integer('status_log_id')->primary();
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('status_id');
            $table->timestamp('submitted_at');
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->timestamp('deleted_at')->nullable();
        });

        DB::table('sitios')->insert([
            ['sitio_id' => 1, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'],
            ['sitio_id' => 2, 'barangay_id' => 2, 'sitio_name' => 'Other Barangay'],
        ]);
        DB::table('puroks')->insert([
            ['purok_id' => 10, 'sitio_id' => 1, 'purok_name' => 'Purok 1'],
            ['purok_id' => 20, 'sitio_id' => 2, 'purok_name' => 'Purok 1'],
        ]);
        DB::table('addresses')->insert([
            ['address_id' => 1, 'purok_id' => 10],
            ['address_id' => 2, 'purok_id' => 20],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-UNSAFE', 'address_id' => 1],
            ['household_id' => 'HH-SAFE', 'address_id' => 1],
            ['household_id' => 'HH-EVACUATED', 'address_id' => 1],
            ['household_id' => 'HH-OTHER', 'address_id' => 2],
        ]);
        DB::table('household_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'unsafe', 'status_label' => 'Unsafe'],
            ['status_id' => 2, 'status_key' => 'safe', 'status_label' => 'Safe'],
            ['status_id' => 3, 'status_key' => 'evacuated', 'status_label' => 'Evacuated'],
        ]);
        DB::table('household_status_logs')->insert([
            ['status_log_id' => 1, 'household_id' => 'HH-UNSAFE', 'disaster_id' => 'EVT-1', 'status_id' => 1, 'submitted_at' => '2026-11-08 10:00:00'],
            ['status_log_id' => 2, 'household_id' => 'HH-SAFE', 'disaster_id' => 'EVT-1', 'status_id' => 1, 'submitted_at' => '2026-11-08 10:00:00'],
            ['status_log_id' => 3, 'household_id' => 'HH-SAFE', 'disaster_id' => 'EVT-1', 'status_id' => 2, 'submitted_at' => '2026-11-08 11:00:00'],
            ['status_log_id' => 4, 'household_id' => 'HH-EVACUATED', 'disaster_id' => 'EVT-1', 'status_id' => 3, 'submitted_at' => '2026-11-08 10:00:00'],
            ['status_log_id' => 5, 'household_id' => 'HH-OTHER', 'disaster_id' => 'EVT-1', 'status_id' => 1, 'submitted_at' => '2026-11-08 10:00:00'],
        ]);
        foreach ([
            ['HH-UNSAFE', 3],
            ['HH-SAFE', 2],
            ['HH-EVACUATED', 2],
            ['HH-OTHER', 4],
        ] as [$householdId, $count]) {
            for ($index = 1; $index <= $count; $index++) {
                DB::table('household_members')->insert([
                    'member_id' => "{$householdId}-M{$index}",
                    'household_id' => $householdId,
                ]);
            }
        }

        $rows = app(MappingQuery::class)->getHouseholdExposureByPurok('EVT-1', 1);

        $this->assertSame([
            [
                'purok_id' => 10,
                'purok_name' => 'Purok 1',
                'sitio_id' => 1,
                'sitio_name' => 'Sitio A',
                'status_key' => 'evacuated',
                'status_label' => 'Evacuated',
                'families' => 1,
                'individuals' => 2,
            ],
            [
                'purok_id' => 10,
                'purok_name' => 'Purok 1',
                'sitio_id' => 1,
                'sitio_name' => 'Sitio A',
                'status_key' => 'unsafe',
                'status_label' => 'Unsafe',
                'families' => 1,
                'individuals' => 3,
            ],
        ], $rows);
    }

    public function test_no_active_event_has_no_reported_exposure_rows(): void
    {
        $this->assertSame([], app(MappingQuery::class)->getHouseholdExposureByPurok(null, 1));
    }
}
