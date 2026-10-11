<?php

namespace App\Presenters;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class DisasterBroadcastPresenter
{
    public function statusOptions(): array
    {
        $fallback = [
            ['key' => 'safe', 'label' => 'Safe'],
            ['key' => 'evacuated', 'label' => 'Evacuated'],
            ['key' => 'need_help', 'label' => 'Need help'],
            ['key' => 'unsafe', 'label' => 'Unsafe'],
            ['key' => 'injured', 'label' => 'Injured'],
            ['key' => 'missing_contact', 'label' => 'Missing contact'],
            ['key' => 'needs_supplies', 'label' => 'Needs supplies'],
            ['key' => 'follow_up', 'label' => 'Follow-up'],
        ];

        if (! Schema::hasTable('household_statuses') || ! Schema::hasColumn('household_statuses', 'status_key')) {
            return array_map(fn ($option) => $option + ['safety_basis' => \App\Support\StatusSafety::basis($option['key'])], $fallback);
        }

        $labelColumn = Schema::hasColumn('household_statuses', 'status_label')
            ? 'status_label'
            : (Schema::hasColumn('household_statuses', 'status_name') ? 'status_name' : null);
        if (! $labelColumn) return array_map(fn ($option) => $option + ['safety_basis' => \App\Support\StatusSafety::basis($option['key'])], $fallback);

        $columns = ['status_key', $labelColumn.' as status_label'];
        if (Schema::hasColumn('household_statuses', 'safety_basis')) $columns[] = 'safety_basis';
        $options = DB::table('household_statuses')->orderBy('status_id')->get($columns)
            ->map(fn (object $status): array => ['key' => $status->status_key, 'label' => $status->status_label, 'safety_basis' => \App\Support\StatusSafety::basis($status->status_key, $status->safety_basis ?? null)])
            ->values()->all();

        return $options;
    }

    public function formatEvent(object $event, int $statusSentCount = 0, array $latestWeather = []): array
    {
        $isActive = ! $event->ended_at;

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type_id' => $event->type_id,
            'type_code' => $event->type_code,
            'type_name' => $event->type_name ?? 'Disaster event',
            'severity_level_id' => $event->severity_level_id,
            'severity_key' => $event->severity_key ?? 'medium',
            'severity_label' => $event->severity_label ?? 'Unspecified',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
            'ended_at' => $this->formatDateTime($event->ended_at),
            'ended_time' => $this->formatTime($event->ended_at),
            'duration_label' => $this->durationLabel($event->started_at, $event->ended_at),
            'status_sent_count' => $statusSentCount,
            'latest_weather' => $latestWeather,
            'status' => $isActive ? 'active' : 'closed',
        ];
    }

    public function durationLabel(?string $startedAt, ?string $endedAt): string
    {
        if (! $startedAt) {
            return 'Not recorded';
        }

        $start = Carbon::parse($startedAt);
        $end = $endedAt ? Carbon::parse($endedAt) : now();

        return $start->diffForHumans($end, true).' '.($endedAt ? 'total' : 'active');
    }

    public function formatBroadcast(object $broadcast, ?int $fallbackRecipientCount = null): array
    {
        $metadata = $this->decodeMetadata($broadcast->allowed_statuses);
        $directPuroks = $this->decodeJsonArray($broadcast->direct_impact_puroks_json ?? null);
        $statusKeys = $this->decodeJsonArray($broadcast->allowed_statuses_json ?? null);

        if (empty($directPuroks)) {
            $directPuroks = $metadata['puroks'] ?? [];
        }

        if (empty($statusKeys)) {
            $statusKeys = $metadata['statuses'] ?? $this->legacyStatuses($broadcast->allowed_statuses);
        }

        $recipientCount = $broadcast->recipient_count ?? null;

        return [
            'broadcast_id' => $broadcast->broadcast_id,
            'broadcast_title' => $broadcast->broadcast_title,
            'disaster_id' => $broadcast->disaster_id,
            'sent_by_admin_id' => $broadcast->sent_by_admin_id,
            'severity_id' => $broadcast->severity_id,
            'severity_key' => $broadcast->severity_key ?? 'medium',
            'severity_label' => $broadcast->severity_label ?? 'Medium',
            'scope_type' => $broadcast->scope_type,
            'scope_label' => $this->scopeLabel($broadcast->scope_type),
            'target_area' => $broadcast->target_area_label ?? $metadata['area'] ?? $this->scopeLabel($broadcast->scope_type),
            'direct_puroks' => $directPuroks,
            'allowed_statuses' => $statusKeys,
            'status_options' => $this->decodeJsonArray($broadcast->status_options_json ?? null),
            'estimated_duration' => $metadata['duration'] ?? null,
            'recipient_count' => $recipientCount === null
                ? $fallbackRecipientCount
                : (int) $recipientCount,
            'message' => $broadcast->message,
            'channel' => $broadcast->channel,
            'push_status' => $broadcast->push_status ?? null,
            'provider_ids' => $this->decodeJsonArray($broadcast->push_provider_ids_json ?? null),
            'push_errors' => $this->decodeJsonArray($broadcast->push_errors_json ?? null),
            'accepted_count' => isset($broadcast->push_accepted_count) ? (int) $broadcast->push_accepted_count : 0,
            'sms_status' => $broadcast->sms_status ?? null,
            'status' => $broadcast->status,
            'sent_at' => $this->formatDateTime($broadcast->sent_at),
            'sent_time' => $this->formatTime($broadcast->sent_at),
        ];
    }

    public function decodeMetadata(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function legacyStatuses(?string $text): array
    {
        if (! $text) {
            return [];
        }

        return collect(explode(',', $text))
            ->map(fn (string $status): string => trim($status))
            ->filter()
            ->values()
            ->all();
    }

    public function decodeJsonArray(mixed $text): array
    {
        // Older broadcasts stored JSON strings through Eloquent array casts,
        // so some records contain a JSON-encoded JSON string.
        for ($attempt = 0; $attempt < 3 && is_string($text); $attempt++) {
            $text = json_decode($text, true);
        }
        return is_array($text) ? $text : [];
    }

    public function scopeLabel(string $scopeType): string
    {
        return match ($scopeType) {
            'selected_puroks' => 'Selected puroks',
            default => 'Barangay-wide',
        };
    }

    public function formatDateTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('M d, Y h:i A');
    }

    public function formatTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('h:i A');
    }
}


