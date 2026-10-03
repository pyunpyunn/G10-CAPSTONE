<?php

namespace Tests\Feature;

use App\Queries\ArchiveEventDetailQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArchiveEventDetailQueryTest extends TestCase
{
    public function test_related_archive_details_are_fetched_for_a_batch(): void
    {
        Schema::create('weather_logs', function (Blueprint $table): void {
            $table->increments('weather_log_id');
            $table->string('disaster_id');
            $table->string('condition_name')->nullable();
            $table->string('advisory_title')->nullable();
            $table->string('wind_speed')->nullable();
            $table->string('wind_direction')->nullable();
            $table->string('rainfall_mm')->nullable();
            $table->string('temperature')->nullable();
            $table->string('source_name')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('disaster_broadcasts', function (Blueprint $table): void {
            $table->increments('broadcast_id');
            $table->string('disaster_id');
            $table->string('broadcast_title');
            $table->timestamp('sent_at')->nullable();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->string('disaster_id');
            $table->string('household_id');
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('address_id')->nullable();
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->string('address_id')->primary();
            $table->string('purok_sitio')->nullable();
        });
        Schema::create('incident_archives', function (Blueprint $table): void {
            $table->increments('archive_id');
            $table->string('disaster_id')->nullable();
            $table->string('archive_note')->nullable();
            $table->timestamp('archived_at')->nullable();
        });

        DB::table('weather_logs')->insert([
            ['disaster_id' => 'EVT-1', 'condition_name' => 'Old', 'observed_at' => '2026-01-01', 'created_at' => '2026-01-01'],
            ['disaster_id' => 'EVT-1', 'condition_name' => 'Rain', 'observed_at' => '2026-01-02', 'created_at' => '2026-01-02'],
        ]);
        DB::table('disaster_broadcasts')->insert([
            ['disaster_id' => 'EVT-1', 'broadcast_title' => 'First', 'sent_at' => '2026-01-01'],
            ['disaster_id' => 'EVT-1', 'broadcast_title' => 'Latest', 'sent_at' => '2026-01-02'],
        ]);
        DB::table('addresses')->insert(['address_id' => 'A-1', 'purok_sitio' => 'Purok 1']);
        DB::table('households')->insert(['household_id' => 'H-1', 'address_id' => 'A-1']);
        DB::table('household_disasters')->insert(['disaster_id' => 'EVT-1', 'household_id' => 'H-1']);
        DB::table('incident_archives')->insert(['disaster_id' => 'EVT-1', 'archive_note' => 'Closed', 'archived_at' => '2026-01-03']);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void { $queryCount++; });
        $details = (new ArchiveEventDetailQuery())->forEvents(['EVT-1', 'EVT-2']);

        $this->assertSame('Rain', $details['EVT-1']['weather']['condition']);
        $this->assertSame(2, $details['EVT-1']['broadcast_count']);
        $this->assertSame('Latest', $details['EVT-1']['latest_broadcast']->broadcast_title);
        $this->assertSame(1, $details['EVT-1']['household_scope']);
        $this->assertSame('Purok 1', $details['EVT-1']['purok_scope']);
        $this->assertSame('Closed', $details['EVT-1']['archive']->archive_note);
        $this->assertSame('Barangay scope', $details['EVT-2']['purok_scope']);
        $this->assertSame(6, $queryCount);
    }
}
