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

    public function test_household_status_archive_does_not_show_member_notes_or_assessments(): void
    {
        $record = app(ArchivePresenter::class)->formatHouseholdStatus((object) [
            'status_log_id' => 3,
            'disaster_id' => 'EV-1',
            'status_key' => 'needs_help',
            'status_label' => 'Needs help',
            'household_name' => 'Rivera Household',
            'household_code' => 'HH-1',
            'purok_sitio' => 'Purok 1',
            'source' => 'responder_field_report',
            'notes' => json_encode([
                'report_type' => 'rescuer_field_report',
                'incident_type' => 'flood',
                'property_damage' => 'major',
                'death_count' => 1,
                'injured_count' => 2,
                'missing_count' => 0,
                'user_notes' => 'Evacuation required.',
                'members' => [['member_id' => 'M-1', 'status_key' => 'injured']],
            ]),
            'event_name' => 'Flood response',
            'event_ended_at' => null,
            'submitted_at' => '2026-11-12 12:00:00',
            'location_label' => null,
            'location_accuracy_m' => null,
            'battery_level' => null,
            'latitude' => null,
            'longitude' => null,
            'responder_name' => 'Responder',
        ]);

        $details = collect($record['details'])->keyBy('label')->map(fn (array $detail) => $detail['value']);

        $this->assertSame('Logged by Responder', $record['source']['meta']);
        $this->assertSame('Flood', $details['Incident type']);
        $this->assertSame('Major', $details['Property damage']);
        $this->assertSame('1', $details['Deaths']);
        $this->assertSame('2', $details['Injured']);
        $this->assertSame('0', $details['Missing']);
        $this->assertArrayNotHasKey('Member assessments', $details->all());
        $this->assertArrayNotHasKey('Field notes', $details->all());
        $this->assertSame('Evacuation required.', $details['Remarks / Notes']);
    }

    public function test_household_status_archive_ignores_member_notes(): void
    {
        $record = app(ArchivePresenter::class)->formatHouseholdStatus((object) [
            'status_log_id' => 4,
            'disaster_id' => 'EV-1',
            'status_key' => 'needs_help',
            'status_label' => 'Needs help',
            'household_name' => 'Rivera Household',
            'purok_sitio' => 'Purok 1',
            'source' => 'household_mobile',
            'notes' => json_encode(['member_notes' => 'Private member note']),
            'event_name' => 'Flood response',
            'event_ended_at' => null,
            'submitted_at' => '2026-11-12 12:00:00',
            'location_label' => null,
            'location_accuracy_m' => null,
            'battery_level' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $labels = collect($record['details'])->pluck('label');
        $this->assertFalse($labels->contains('Remarks / Notes'));
        $this->assertStringNotContainsString('Private member note', json_encode($record));
    }
}
