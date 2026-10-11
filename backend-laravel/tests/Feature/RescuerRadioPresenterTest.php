<?php

namespace Tests\Feature;

use App\Presenters\RescuerRadioPresenter;
use Tests\TestCase;

class RescuerRadioPresenterTest extends TestCase
{
    public function test_radio_log_display_fields_keep_labels_messages_and_timestamp_format(): void
    {
        $presenter = app(RescuerRadioPresenter::class);
        $log = $presenter->log((object) [
            'communication_id' => 44,
            'responder_id' => 7,
            'team_id' => 2,
            'team_name' => null,
            'disaster_id' => 'EVT-4',
            'timestamp' => '2026-09-12 10:30:00',
            'message' => '{"type":"quick_signal","channel":"command","signal":"Need backup","responder_name":"Alex"}',
        ]);

        $this->assertSame(44, $log['id']);
        $this->assertSame('quick_signal', $log['type']);
        $this->assertSame('Need backup', $log['type_label']);
        $this->assertSame('HQ Command', $log['channel_label']);
        $this->assertSame('Alex sent "Need backup"', $log['message']);
        $this->assertSame('Assigned team', $log['team_name']);
        $this->assertSame('Sep 12, 10:30 AM', $log['timestamp']);
    }

    public function test_radio_audio_url_uses_a_same_origin_storage_path(): void
    {
        $presenter = app(RescuerRadioPresenter::class);
        $log = $presenter->log((object) [
            'communication_id' => 45,
            'responder_id' => 7,
            'team_id' => 2,
            'team_name' => 'Team A',
            'disaster_id' => 'EVT-4',
            'timestamp' => '2026-10-08 10:30:00',
            'message' => json_encode([
                'type' => 'ptt_audio',
                'channel' => 'team',
                'audio_path' => 'radio-ptt/2026/10/08/clip.m4a',
            ]),
        ]);

        $this->assertSame('/storage/radio-ptt/2026/10/08/clip.m4a', $log['audio_url']);
    }

    public function test_radio_transmission_summary_ignores_ended_transmissions(): void
    {
        $presenter = app(RescuerRadioPresenter::class);
        $responder = (object) ['responder_id' => 7];
        $recent = now()->subSeconds(3)->toDateTimeString();
        $logs = collect([
            [
                'type' => 'ptt_start',
                'transmission_id' => 'PTT-1',
                'raw_timestamp' => $recent,
                'channel' => 'team',
                'channel_label' => 'Team',
                'responder_id' => 7,
                'responder_name' => 'Alex',
                'responder_code' => 'R-7',
                'team_name' => 'Team A',
                'duration_seconds' => 3,
                'timestamp' => 'Sep 12, 10:30 AM',
                'audio_status' => 'metadata_only',
            ],
            [
                'type' => 'ptt_end',
                'transmission_id' => 'PTT-1',
                'raw_timestamp' => $recent,
            ],
        ]);

        $this->assertNull($presenter->activeTransmission($responder, $logs));
    }
}


