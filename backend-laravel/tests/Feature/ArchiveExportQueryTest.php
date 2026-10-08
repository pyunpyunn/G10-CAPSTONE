<?php

namespace Tests\Feature;

use App\Presenters\ArchiveEventPresenter;
use App\Presenters\ArchivePresenter;
use App\Queries\ArchiveQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ArchiveExportQueryTest extends TestCase
{
    public function test_export_reads_all_matching_records_in_five_hundred_row_chunks(): void
    {
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->string('type_id')->nullable();
            $table->string('severity_level_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('disaster_types', function (Blueprint $table): void {
            $table->string('type_id')->primary();
            $table->string('type_name');
        });
        Schema::create('severity_levels', function (Blueprint $table): void {
            $table->string('severity_id')->primary();
            $table->string('severity_key');
            $table->string('severity_label');
        });

        for ($start = 1; $start <= 1200; $start += 100) {
            $batch = [];
            for ($number = $start; $number < $start + 100; $number++) {
                $batch[] = ['event_id' => sprintf('EVT-%04d', $number), 'name' => 'Flood', 'started_at' => '2026-01-01 00:00:00'];
            }
            DB::table('disaster_events')->insert($batch);
        }

        $presenter = Mockery::mock(ArchiveEventPresenter::class);
        $presenter->shouldReceive('presentBatch')->times(3)->andReturnUsing(
            static fn ($batch): array => $batch->map(static fn (object $row): array => ['export' => ['reference' => $row->event_id]])->all()
        );
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'from "disaster_events"')) $queries[] = $query->sql;
        });

        [$total, $rows] = (new ArchiveQuery(new ArchivePresenter(), $presenter))
            ->exportRows('disaster-events', Request::create('/archive/export', 'GET'));

        $results = $rows->all();
        $this->assertSame(1200, $total);
        $this->assertCount(1200, $results);
        $this->assertCount(4, $queries); // one count and three bounded reads
        $this->assertSame('EVT-1200', $results[0]['export']['reference']);
    }
}
