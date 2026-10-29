<?php

namespace Tests\Feature;

use App\Services\Archive\ArchiveGroupManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveGroupSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::disk('public')->put('radio/source.webm', 'test recording');
        Schema::create('disaster_events', function (Blueprint $t): void { $t->string('event_id')->primary(); $t->string('name')->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('ended_at')->nullable(); });
        Schema::create('responders', function (Blueprint $t): void { $t->integer('responder_id')->primary(); $t->string('full_name'); $t->string('responder_code'); });
        Schema::create('rescue_teams', function (Blueprint $t): void { $t->integer('team_id')->primary(); $t->string('team_code'); $t->string('team_type'); });
        Schema::create('responder_communication_logs', function (Blueprint $t): void { $t->integer('communication_id')->primary(); $t->string('disaster_id')->nullable(); $t->integer('responder_id'); $t->integer('team_id'); $t->string('team_name'); $t->text('message'); $t->timestamp('timestamp'); });
        Schema::create('incident_archives', function (Blueprint $t): void { $t->integer('archive_id')->primary(); $t->string('archive_type'); $t->string('reference_table'); $t->string('reference_id'); $t->text('archive_note'); $t->timestamp('archived_at'); });
        DB::table('responders')->insert(['responder_id' => 1, 'full_name' => 'DB Responder', 'responder_code' => 'R1']);
        DB::table('rescue_teams')->insert(['team_id' => 1, 'team_code' => 'SAR', 'team_type' => 'Rescue']);
        DB::table('responder_communication_logs')->insert(['communication_id' => 17, 'responder_id' => 1, 'team_id' => 1, 'team_name' => 'Database team', 'timestamp' => now(), 'message' => json_encode(['type' => 'ptt_audio', 'audio_path' => 'radio/source.webm', 'duration_seconds' => 4])]);
        DB::table('operational_sequences')->insert(['sequence_key' => 'incident_archives', 'next_value' => 1]);
    }

    public function test_saved_groups_use_database_records_and_legacy_groups_receive_current_recording_metadata(): void
    {
        $manager = app(ArchiveGroupManager::class);
        $request = Request::create('/', 'POST', ['category' => 'radio-communication-logs', 'records' => [['id' => 17, 'transmission' => ['title' => 'Client supplied title'], 'recording' => ['url' => '/storage/not-real.webm']]]]);
        $response = $manager->store($request);
        $this->assertSame(201, $response->getStatusCode());
        $record = $response->getData(true)['data']['group']['records'][0];
        $this->assertStringContainsString('DB Responder', $record['transmission']['title']);
        $this->assertSame('/storage/radio/source.webm', $record['recording']['url']);
        $this->assertStringNotContainsString('Client supplied title', DB::table('incident_archives')->value('archive_note'));

        $note = json_decode(DB::table('incident_archives')->value('archive_note'), true);
        unset($note['records'][0]['recording']);
        DB::table('incident_archives')->update(['archive_note' => json_encode($note)]);
        $record = $manager->savedGroups()->getData(true)['data']['groups'][0]['records'][0];
        $this->assertTrue($record['recording']['available']);
        $this->assertSame('/storage/radio/source.webm', $record['recording']['url']);

        $note['records'][0]['recording'] = $record['recording'];
        DB::table('incident_archives')->update(['archive_note' => json_encode($note)]);
        DB::table('responder_communication_logs')->delete();
        $archived = $manager->savedGroups()->getData(true)['data']['groups'][0]['records'][0];
        $this->assertTrue($archived['recording']['available']);
        Storage::disk('public')->delete('radio/source.webm');
        $archived = $manager->savedGroups()->getData(true)['data']['groups'][0]['records'][0];
        $this->assertFalse($archived['recording']['available']);
    }

    public function test_missing_source_records_cannot_be_saved_as_fabricated_archives(): void
    {
        $response = app(ArchiveGroupManager::class)->store(Request::create('/', 'POST', ['category' => 'radio-communication-logs', 'records' => [['id' => 999, 'transmission' => ['title' => 'Not in database']]]]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, DB::table('incident_archives')->count());
    }
}
