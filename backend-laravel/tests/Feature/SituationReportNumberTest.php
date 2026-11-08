<?php

namespace Tests\Feature;

use App\Queries\SituationReportQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SituationReportNumberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('situation_reports');
        Schema::dropIfExists('operational_sequences');
        Schema::create('situation_reports', function ($table): void {
            $table->bigIncrements('sit_rep_id');
            $table->string('disaster_id');
            $table->string('report_number')->unique();
        });
        Schema::create('operational_sequences', function ($table): void {
            $table->string('sequence_key')->primary();
            $table->unsignedBigInteger('next_value');
        });
    }

    public function test_report_number_uses_generation_date_and_a_daily_increment_across_events(): void
    {
        $query = app(SituationReportQuery::class);
        $generatedAt = \Carbon\Carbon::parse('2026-11-08 19:11:00');

        $this->assertSame('SITREP-20261108-0001', $query->nextReportNumber('EV1', $generatedAt));
        $this->assertSame('SITREP-20261108-0002', $query->nextReportNumber('EV2', $generatedAt));
        $this->assertSame('SITREP-20261109-0001', $query->nextReportNumber('EV1', $generatedAt->copy()->addDay()));
        $this->assertDatabaseHas('operational_sequences', ['sequence_key' => 'sitrep-series:20261108', 'next_value' => 3]);
        $this->assertDatabaseHas('operational_sequences', ['sequence_key' => 'sitrep-series:20261109', 'next_value' => 2]);
    }

    public function test_existing_date_numbers_seed_the_next_increment(): void
    {
        DB::table('situation_reports')->insert([
            'disaster_id' => 'EV1',
            'report_number' => 'SITREP-20261108-0007',
        ]);

        $this->assertSame(
            'SITREP-20261108-0008',
            app(SituationReportQuery::class)->nextReportNumber('EV1', \Carbon\Carbon::parse('2026-11-08 19:11:00')),
        );
    }
}
