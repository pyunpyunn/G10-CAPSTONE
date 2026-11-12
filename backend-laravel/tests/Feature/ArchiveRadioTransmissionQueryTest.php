<?php

namespace Tests\Feature;

use App\Presenters\ArchiveEventPresenter;
use App\Presenters\ArchivePresenter;
use App\Queries\ArchiveQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ArchiveRadioTransmissionQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->unsignedInteger('responder_id')->primary();
            $table->string('full_name')->nullable();
            $table->string('responder_code')->nullable();
        });
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->unsignedInteger('team_id')->primary();
            $table->string('team_code')->nullable();
            $table->string('team_type')->nullable();
        });
        Schema::create('responder_communication_logs', function (Blueprint $table): void {
            $table->increments('communication_id');
            $table->unsignedInteger('responder_id')->nullable();
            $table->unsignedInteger('team_id')->nullable();
            $table->string('team_name')->nullable();
            $table->string('disaster_id')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('timestamp');
        });

        DB::table('responders')->insert(['responder_id' => 1, 'full_name' => 'Hannah P. Garcia', 'responder_code' => 'BDRRM-ECM-2000']);
        DB::table('rescue_teams')->insert(['team_id' => 1, 'team_code' => 'ECM', 'team_type' => 'Evacuation and Camp Management Team']);
        $this->addLog(11, 'PTT-ABC', 'ptt_start', '2026-11-10 15:56:00');
        $this->addLog(12, 'PTT-ABC', 'ptt_audio', '2026-11-10 15:56:02', ['audio_path' => 'radio/ptt-abc.webm', 'duration_seconds' => 9]);
        $this->addLog(13, 'PTT-ABC', 'ptt_end', '2026-11-10 15:56:04', ['duration_seconds' => 9]);
        $this->addLog(14, 'PTT-DEF', 'ptt_start', '2026-11-10 15:55:00');
        $this->addLog(15, null, 'quick_signal', '2026-11-10 15:54:00', ['signal' => 'Need assistance']);
        Storage::fake('public');
        Storage::disk('public')->put('radio/ptt-abc.webm', 'test-recording');
    }

    public function test_list_groups_transmission_logs_before_pagination_and_keeps_all_details(): void
    {
        [$paginator, $records] = $this->query()->categoryRows(
            'radio-communication-logs',
            Request::create('/archive/radio-communication-logs', 'GET', ['per_page' => 2]),
            25,
        );

        $this->assertSame(3, $paginator->total());
        $this->assertCount(2, $records);
        $transmission = collect($records)->firstWhere('transmission_id', 'PTT-ABC');
        $this->assertSame('11', $transmission['id']);
        $this->assertSame(['11', '12', '13'], $transmission['communication_ids']);
        $this->assertSame('12', $transmission['recordings'][0]['communication_id']);
        $this->assertSame(['PTT started', 'Voice clip', 'PTT ended'], explode(' · ', $transmission['channel']['meta']));
        $this->assertStringContainsString('COM-12', collect($transmission['details'])->firstWhere('label', 'Communication details')['value']);
        $this->assertStringContainsString('PTT-ABC', collect($transmission['details'])->firstWhere('label', 'Transmission ID')['value']);
    }

    public function test_search_and_exports_return_one_complete_record_per_matching_transmission(): void
    {
        $query = $this->query();
        [$paginator, $records] = $query->categoryRows(
            'radio-communication-logs',
            Request::create('/archive/radio-communication-logs', 'GET', ['search' => 'PTT-ABC']),
            25,
        );
        $this->assertSame(1, $paginator->total());
        $this->assertSame(['11', '12', '13'], $records[0]['communication_ids']);

        [$total, $exported] = $query->exportRows(
            'radio-communication-logs',
            Request::create('/archive/export', 'GET', ['ids' => ['11', '12', '13']]),
        );
        $this->assertSame(1, $total);
        $this->assertCount(1, $exported);
        $this->assertSame('PTT-ABC', $exported[0]['export']['reference']);
        $this->assertStringContainsString('COM-11', $exported[0]['export']['transmission']);
        $this->assertStringContainsString('COM-13', $exported[0]['export']['transmission']);
    }

    private function addLog(int $id, ?string $transmissionId, string $type, string $timestamp, array $extras = []): void
    {
        $message = array_merge([
            'type' => $type,
            'channel' => 'team',
            'responder_name' => 'Hannah P. Garcia',
            'responder_code' => 'BDRRM-ECM-2000',
            'team_name' => 'Evacuation and Camp Management Team',
        ], $transmissionId ? ['transmission_id' => $transmissionId] : [], $extras);

        DB::table('responder_communication_logs')->insert([
            'communication_id' => $id,
            'responder_id' => 1,
            'team_id' => 1,
            'team_name' => 'Evacuation and Camp Management Team',
            'message' => json_encode($message),
            'timestamp' => $timestamp,
        ]);
    }

    private function query(): ArchiveQuery
    {
        return new ArchiveQuery(new ArchivePresenter(), Mockery::mock(ArchiveEventPresenter::class));
    }
}
