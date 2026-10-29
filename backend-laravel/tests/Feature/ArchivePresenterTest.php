<?php

namespace Tests\Feature;

use App\Presenters\ArchivePresenter;
use Tests\TestCase;

class ArchivePresenterTest extends TestCase
{
    public function test_radio_archive_includes_playable_recording_only_when_the_file_exists(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $path = 'radio/test-recording.webm';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'test-audio-file');
        $row = (object) [
            'communication_id' => 17, 'disaster_id' => null,
            'message' => json_encode(['type' => 'ptt_audio', 'audio_path' => $path, 'duration_seconds' => 3.5]),
            'responder_name' => 'Responder', 'responder_code' => 'R17', 'team_name' => 'Team',
            'event_name' => null, 'team_code' => 'T1', 'timestamp' => '2026-10-29 09:00:00',
        ];
        $presenter = app(ArchivePresenter::class);
        $record = $presenter->formatRadioCommunication($row);
        $this->assertTrue($record['recording']['available']);
        $this->assertSame('/storage/'.$path, $record['recording']['url']);
        $this->assertSame(3.5, $record['recording']['duration_seconds']);

        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        $record = $presenter->formatRadioCommunication($row);
        $this->assertFalse($record['recording']['available']);
        $this->assertNull($record['recording']['url']);
        $this->assertStringContainsString('unavailable', $record['recording']['status']);
    }

    public function test_saved_group_payload_is_presented_with_a_safe_category_and_valid_records(): void
    {
        $presenter = app(ArchivePresenter::class);
        $group = $presenter->savedGroup((object) [
            'archive_note' => json_encode(['category' => 'dispatch-logs', 'records' => [['id' => 'A-1'], ['label' => 'missing id']]]),
            'reference_table' => 'dispatch-logs',
            'reference_id' => 'AG-1',
            'archive_id' => 5,
            'archived_at' => '2026-09-29 01:02:03',
        ], ['disaster-events', 'dispatch-logs']);

        $this->assertSame('AG-1', $group['id']);
        $this->assertSame('dispatch-logs', $group['category']);
        $this->assertSame([['id' => 'A-1']], $group['records']);
        $this->assertSame('Sep 29, 2026, 1:02 AM', $group['savedAt']);
    }

    public function test_invalid_saved_group_payload_uses_default_category_and_empty_records(): void
    {
        $group = app(ArchivePresenter::class)->savedGroup((object) [
            'archive_note' => '{bad json', 'reference_table' => 'unknown', 'reference_id' => null,
            'archive_id' => 9, 'archived_at' => null,
        ], ['disaster-events']);

        $this->assertSame('9', $group['id']);
        $this->assertSame('disaster-events', $group['category']);
        $this->assertSame([], $group['records']);
    }
}


