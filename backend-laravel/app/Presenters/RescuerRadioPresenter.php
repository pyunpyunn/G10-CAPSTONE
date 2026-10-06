<?php

namespace App\Presenters;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class RescuerRadioPresenter
{
    public function channelLabel(string $channel): string
    {
        return match ($channel) {
            'command' => 'HQ Command',
            'event' => 'Event',
            default => 'Team',
        };
    }

    public function logs(Collection $rows): Collection
    {
        return $rows->map(fn (object $row): array => $this->log($row))->values();
    }

    public function log(object $row): array
    {
        $message = $this->decode($row->message ?? null);
        $type = (string) ($message['type'] ?? 'message');
        $channel = (string) ($message['channel'] ?? 'team');

        return [
            'id' => $row->communication_id,
            'communication_id' => $row->communication_id,
            'type' => $type,
            'type_label' => $this->typeLabel($type, $message),
            'channel' => $channel,
            'channel_label' => (string) ($message['channel_label'] ?? $this->channelLabel($channel)),
            'transmission_id' => $message['transmission_id'] ?? null,
            'signal' => $message['signal'] ?? null,
            'duration_seconds' => (int) ($message['duration_seconds'] ?? 0),
            'responder_id' => $row->responder_id,
            'responder_name' => (string) ($message['responder_name'] ?? 'Responder'),
            'responder_code' => $message['responder_code'] ?? null,
            'team_id' => $row->team_id,
            'team_name' => $row->team_name ?: ($message['team_name'] ?? 'Assigned team'),
            'event_id' => $row->disaster_id,
            'event_name' => (string) ($message['event_name'] ?? 'No active event'),
            'audio_status' => (string) ($message['audio_status'] ?? 'metadata_only'),
            'audio_path' => $message['audio_path'] ?? null,
            'audio_url' => ! empty($message['audio_path']) ? Storage::disk('public')->url($message['audio_path']) : null,
            'message' => $this->displayMessage($type, $message),
            'timestamp' => $this->mobileDateTime($row->timestamp),
            'raw_timestamp' => $row->timestamp,
        ];
    }

    public function teamMembers(Collection $members, Collection $logs, ?array $activeTransmission, object $responder): array
    {
        return $members->map(function (object $member) use ($logs, $activeTransmission, $responder): array {
            $memberId = (string) $member->responder_id;
            $queuedCount = $logs
                ->filter(fn (array $log): bool => $log['type'] === 'ptt_audio'
                    && (string) $log['responder_id'] === $memberId)
                ->count();

            return [
                'responder_id' => $member->responder_id,
                'responder_code' => $member->responder_code,
                'full_name' => $member->full_name ?: 'Responder',
                'initials' => $this->initials($member->full_name ?: 'Responder'),
                'team_id' => $member->team_id,
                'team_name' => $member->team_name ?: 'Assigned team',
                'team_code' => $member->team_code,
                'is_self' => (int) $member->responder_id === (int) $responder->responder_id,
                'is_transmitting' => $activeTransmission
                    && (string) ($activeTransmission['responder_id'] ?? '') === $memberId,
                'queued_count' => $queuedCount,
            ];
        })->values()->all();
    }

    public function activeTransmission(object $responder, Collection $logs): ?array
    {
        $recentLogs = $logs
            ->filter(fn (array $log): bool => in_array($log['type'], ['ptt_start', 'ptt_heartbeat', 'ptt_end'], true))
            ->values();

        $endedIds = $recentLogs
            ->where('type', 'ptt_end')
            ->pluck('transmission_id')
            ->filter()
            ->unique()
            ->all();

        $latestOpen = $recentLogs
            ->filter(fn (array $log): bool => in_array($log['type'], ['ptt_start', 'ptt_heartbeat'], true))
            ->first(fn (array $log): bool => $log['transmission_id'] && ! in_array($log['transmission_id'], $endedIds, true));

        if (! $latestOpen || now()->diffInSeconds(Carbon::parse($latestOpen['raw_timestamp'])) > 12) {
            return null;
        }

        return [
            'transmission_id' => $latestOpen['transmission_id'],
            'channel' => $latestOpen['channel'],
            'channel_label' => $latestOpen['channel_label'],
            'responder_id' => $latestOpen['responder_id'],
            'responder_name' => $latestOpen['responder_name'],
            'responder_code' => $latestOpen['responder_code'],
            'team_name' => $latestOpen['team_name'],
            'duration_seconds' => $latestOpen['duration_seconds'],
            'is_self' => (int) $latestOpen['responder_id'] === (int) $responder->responder_id,
            'audio_status' => $latestOpen['audio_status'],
            'last_seen_at' => $latestOpen['timestamp'],
        ];
    }

    public function initials(string $name): string
    {
        $parts = collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
            ->join('');

        return $parts ?: 'R';
    }

    private function typeLabel(string $type, array $message): string
    {
        return match ($type) {
            'ptt_start' => 'PTT started',
            'ptt_heartbeat' => 'PTT active',
            'ptt_end' => 'PTT ended',
            'ptt_audio' => 'Voice message',
            'quick_signal' => (string) ($message['signal'] ?? 'Signal'),
            default => 'Radio log',
        };
    }

    private function displayMessage(string $type, array $message): string
    {
        $name = (string) ($message['responder_name'] ?? 'Responder');

        return match ($type) {
            'ptt_start' => $name.' started PTT',
            'ptt_heartbeat' => $name.' is transmitting',
            'ptt_end' => $name.' ended PTT after '.((int) ($message['duration_seconds'] ?? 0)).'s',
            'ptt_audio' => $name.' sent a voice message',
            'quick_signal' => $name.' sent "'.((string) ($message['signal'] ?? 'Signal')).'"',
            default => (string) ($message['text'] ?? $name.' sent a radio log'),
        };
    }

    private function mobileDateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->timezone('Asia/Manila')->format('M d, g:i A') : 'Not recorded';
    }

    private function decode(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}


