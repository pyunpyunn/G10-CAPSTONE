<?php

namespace Tests\Feature;

use App\Queries\SituationMemberStatusQuery;
use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SituationMemberStatusQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedStatuses();
        $this->mock(BarangayProfileService::class, fn ($mock) => $mock->shouldReceive('current')->andReturn(['barangay_id' => 1]));
    }

    public function test_latest_active_event_member_statuses_are_aggregated_for_export_by_purok(): void
    {
        $rows = app(SituationMemberStatusQuery::class)->byPurok('EV-ACTIVE');

        $this->assertNotNull($rows);
        $status = $rows->get('P1');
        $this->assertSame(6, (int) $status->reported_members);
        $this->assertSame(1, (int) $status->safe);
        $this->assertSame(1, (int) $status->unsafe);
        $this->assertSame(1, (int) $status->evacuated);
        $this->assertSame(1, (int) $status->dead);
        $this->assertSame(1, (int) $status->missing);
        $this->assertSame(1, (int) $status->injured);
    }

    private function createTables(): void
    {
        foreach (['member_disaster_statuses', 'member_statuses', 'household_members', 'households', 'addresses', 'puroks', 'sitios'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('sitios', function ($table): void {
            $table->string('sitio_id')->primary();
            $table->unsignedBigInteger('barangay_id');
            $table->string('sitio_name');
        });
        Schema::create('puroks', function ($table): void {
            $table->string('purok_id')->primary();
            $table->string('sitio_id');
            $table->string('purok_name');
        });
        Schema::create('addresses', function ($table): void {
            $table->string('address_id')->primary();
            $table->string('purok_id');
        });
        Schema::create('households', function ($table): void {
            $table->string('household_id')->primary();
            $table->string('address_id');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('household_members', function ($table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('member_statuses', function ($table): void {
            $table->unsignedBigInteger('status_id')->primary();
            $table->string('status_key');
            $table->boolean('counts_as_death')->default(false);
            $table->boolean('counts_as_injury')->default(false);
        });
        Schema::create('member_disaster_statuses', function ($table): void {
            $table->bigIncrements('member_status_id');
            $table->string('disaster_id');
            $table->string('household_id');
            $table->string('member_id');
            $table->unsignedBigInteger('status_id');
            $table->timestamp('reported_at');
        });
    }

    private function seedStatuses(): void
    {
        DB::table('sitios')->insert(['sitio_id' => 'S1', 'barangay_id' => 1, 'sitio_name' => 'Camansi']);
        DB::table('puroks')->insert(['purok_id' => 'P1', 'sitio_id' => 'S1', 'purok_name' => 'Purok 1']);
        DB::table('addresses')->insert(['address_id' => 'A1', 'purok_id' => 'P1']);
        DB::table('households')->insert(['household_id' => 'H1', 'address_id' => 'A1']);
        DB::table('member_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'counts_as_death' => false, 'counts_as_injury' => false],
            ['status_id' => 2, 'status_key' => 'unsafe', 'counts_as_death' => false, 'counts_as_injury' => false],
            ['status_id' => 3, 'status_key' => 'evacuated', 'counts_as_death' => false, 'counts_as_injury' => false],
            ['status_id' => 4, 'status_key' => 'deceased', 'counts_as_death' => true, 'counts_as_injury' => false],
            ['status_id' => 5, 'status_key' => 'missing', 'counts_as_death' => false, 'counts_as_injury' => false],
            ['status_id' => 6, 'status_key' => 'injured', 'counts_as_death' => false, 'counts_as_injury' => true],
        ]);
        DB::table('household_members')->insert(array_map(
            fn (int $id): array => ['member_id' => 'M'.$id, 'household_id' => 'H1'],
            range(1, 6),
        ));
        DB::table('member_disaster_statuses')->insert([
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M1', 'status_id' => 2, 'reported_at' => '2026-11-08 09:00:00'],
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M1', 'status_id' => 1, 'reported_at' => '2026-11-08 10:00:00'],
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M2', 'status_id' => 2, 'reported_at' => '2026-11-08 10:00:00'],
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M3', 'status_id' => 3, 'reported_at' => '2026-11-08 10:00:00'],
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M4', 'status_id' => 4, 'reported_at' => '2026-11-08 10:00:00'],
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M5', 'status_id' => 5, 'reported_at' => '2026-11-08 10:00:00'],
            ['disaster_id' => 'EV-ACTIVE', 'household_id' => 'H1', 'member_id' => 'M6', 'status_id' => 6, 'reported_at' => '2026-11-08 10:00:00'],
            ['disaster_id' => 'EV-CLOSED', 'household_id' => 'H1', 'member_id' => 'M1', 'status_id' => 5, 'reported_at' => '2026-11-08 11:00:00'],
        ]);
    }
}
