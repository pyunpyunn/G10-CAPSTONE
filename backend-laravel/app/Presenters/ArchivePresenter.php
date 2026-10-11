<?php

namespace App\Presenters;

use App\Support\ArchiveGroupPayload;
use Illuminate\Support\Carbon;

class ArchivePresenter
{
    public function savedGroup(?object $row, array $categories): array
    {
        $payload = app(ArchiveGroupPayload::class)->decode($row?->archive_note ?? null);
        $category = (string) ($row?->reference_table ?: ($payload['category'] ?? 'disaster-events'));
        $records = app(ArchiveGroupPayload::class)->records($row?->archive_note ?? null);
        $date = $row?->archived_at ? Carbon::parse($row->archived_at)->timezone('Asia/Manila') : now('Asia/Manila');
        return ['id' => (string) ($row?->reference_id ?: $row?->archive_id), 'category' => in_array($category, $categories, true) ? $category : 'disaster-events', 'savedAt' => $date->format('M d, Y, g:i A'), 'records' => $records];
    }

    public function formatHouseholdStatus(object $row): array
    {
        $statusLabel = $row->status_label ?: $this->label($row->status_key);
        $statusKey = $this->statusKey($row->status_key ?: $statusLabel);
        $purok = $row->purok_sitio ?: 'Unassigned';
        $householdName = $row->household_name ?: $row->household_code ?: 'Household';
        $source = $this->label($row->source ?: 'mobile_report');
        $meta = $row->location_label ?: 'No location label';

        if ($row->location_accuracy_m !== null) {
            $meta .= ' - '.$row->location_accuracy_m.'m accuracy';
        }

        $record = [
            'id' => $row->status_log_id,
            'event_id' => $row->disaster_id,
            'event_name' => $row->event_name ?: 'Disaster event',
            'purok_text' => $purok,
            'status_key' => trim($statusKey.' '.$this->eventStatusKey($row->event_ended_at)),
            'datetime' => $this->formatDateTime($row->submitted_at),
            'event' => $row->event_name ?: 'Disaster event',
            'household' => [
                'title' => $householdName,
                'meta' => $meta,
            ],
            'purok' => $purok,
            'status_change' => [
                'label' => 'Reported '.$statusLabel,
                'tone' => $this->statusTone($statusKey),
            ],
            'source' => [
                'title' => $source,
                'meta' => $row->notes ?: ($row->responder_name ? 'Logged by '.$row->responder_name : 'No remarks'),
            ],
        ];

        $record['details'] = $this->details([
            'Date / time' => $record['datetime'],
            'Event' => $record['event'],
            'Household / geotag' => $householdName.' - '.$meta,
            'Purok' => $purok,
            'Status change' => $record['status_change']['label'],
            'Source / remarks' => $source.' - '.$record['source']['meta'],
            'Battery level' => $row->battery_level !== null ? $row->battery_level.'%' : 'Not recorded',
            'Coordinates' => $row->latitude && $row->longitude ? $row->latitude.', '.$row->longitude : 'Not recorded',
        ]);
        $record['export'] = [
            'datetime' => $record['datetime'],
            'event' => $record['event'],
            'reference' => (string) $row->status_log_id,
            'household' => $householdName,
            'purok' => $purok,
            'status' => $record['status_change']['label'],
            'source' => $source,
        ];

        return $record;
    }

    public function formatDispatch(object $row): array
    {
        $route = $this->decodeJson($row->route_notes);
        $outcome = $this->decodeJson($row->outcome_notes);
        $teamName = $row->team_name ?: $row->responder_name ?: 'Assigned responder';
        $area = $row->assigned_area ?: ($route['area'] ?? 'Area not recorded');
        $status = $this->statusKey($row->status ?: 'assigned');
        $outcomeText = $this->dispatchOutcomeText($outcome, $row->outcome_notes);
        $purok = $this->purokFromText($area);

        $record = [
            'id' => $row->assignment_id,
            'event_id' => $row->disaster_id,
            'event_name' => $row->event_name ?: 'Disaster event',
            'purok_text' => $purok,
            'status_key' => trim($status.' '.$this->eventStatusKey($row->event_ended_at)),
            'datetime' => $this->formatDateTime($row->assigned_at),
            'event' => $row->event_name ?: 'Disaster event',
            'team_route' => [
                'title' => $teamName,
                'meta' => $area,
            ],
            'purok' => $purok,
            'status' => [
                'label' => $this->label($status),
                'tone' => $this->statusTone($status),
            ],
            'outcome' => [
                'title' => $outcomeText,
                'meta' => $row->dispatch_notes ?: ($row->assignment_code ?: 'No dispatch note'),
            ],
        ];

        $record['details'] = $this->details([
            'Date / time' => $record['datetime'],
            'Event' => $record['event'],
            'Team / route' => $teamName.' - '.$area,
            'Purok' => $purok,
            'Status' => $record['status']['label'],
            'Outcome entry' => $outcomeText,
            'Assignment code' => $row->assignment_code ?: 'Not recorded',
            'Priority' => $row->priority_level ?: 'Not recorded',
        ]);
        $record['export'] = [
            'datetime' => $record['datetime'],
            'event' => $record['event'],
            'reference' => $row->assignment_code ?: (string) $row->assignment_id,
            'team_route' => $teamName.' - '.$area,
            'purok' => $purok,
            'status' => $record['status']['label'],
            'outcome' => $outcomeText,
        ];

        return $record;
    }

    public function formatResourceRequest(object $row): array
    {
        $validation = $this->statusKey($row->validation_status ?: 'needs_validation');
        $quantity = trim((string) ($row->quantity ?? '').' '.(string) ($row->unit ?? ''));
        $requestTitle = $row->request_id;
        $requestMeta = ($row->item_name ?: $row->resource_type ?: 'Request').' - '.($quantity !== '' ? $quantity : 'No quantity');
        $site = $row->evacuation_center_name ?: $row->evacuation_center_id ?: 'Area not recorded';
        $event = $row->event_name ?: ($row->source_reference ?: 'Shared DB request');

        $record = [
            'id' => $row->request_id,
            'event_id' => $row->source_reference,
            'event_name' => $event,
            'purok_text' => $site.' '.$row->evacuation_center_address.' '.$row->description,
            'status_key' => trim($validation.' '.$this->eventStatusKey($row->event_ended_at)),
            'datetime' => $this->formatDateTime($row->created_at),
            'event' => $event,
            'request' => [
                'title' => $requestTitle,
                'meta' => $requestMeta,
            ],
            'purok_site' => [
                'title' => $site,
                'meta' => $row->evacuation_center_address ?: $row->description ?: 'No area note',
            ],
            'validation' => [
                'label' => $this->label($validation),
                'tone' => $this->statusTone($validation),
            ],
            'handoff' => [
                'title' => $row->tracking_reference ?: ($validation === 'verified' ? 'Ready for TrackingAid' : 'Not forwarded'),
                'meta' => $row->released_for_tracking_at ? $this->formatDateTime($row->released_for_tracking_at) : ($row->validation_notes ?: 'Validation record only'),
            ],
        ];

        $record['details'] = $this->details([
            'Date / time' => $record['datetime'],
            'Event' => $event,
            'Request' => $requestTitle.' - '.$requestMeta,
            'Purok / site' => $record['purok_site']['title'].' - '.$record['purok_site']['meta'],
            'Validation' => $record['validation']['label'],
            'Release / handoff' => $record['handoff']['title'].' - '.$record['handoff']['meta'],
            'Requested by' => $row->requested_by ?: 'Not recorded',
            'Source' => $this->label($row->request_source ?: 'shared_db'),
        ]);
        $record['export'] = [
            'datetime' => $record['datetime'],
            'event' => $event,
            'reference' => $requestTitle,
            'request' => $requestMeta,
            'purok_site' => $record['purok_site']['title'],
            'status' => $record['validation']['label'],
            'handoff' => $record['handoff']['title'],
        ];

        return $record;
    }

    public function formatRadioCommunication(object $row): array
    {
        $message = $this->decodeJson($row->message);
        $type = (string) ($message['type'] ?? 'message');
        $channel = (string) ($message['channel'] ?? 'team');
        $typeLabel = $this->radioTypeLabel($type, $message);
        $responderName = $row->responder_name ?: ($message['responder_name'] ?? 'Responder');
        $eventName = $row->event_name ?: ($message['event_name'] ?? 'No active event');
        $teamName = $row->team_name ?: ($message['team_name'] ?? 'Assigned team');

        return [
            'id' => $row->communication_id,
            'event_id' => $row->disaster_id,
            'event_name' => $eventName,
            'status_key' => $type,
            'datetime' => $this->formatDateTime($row->timestamp),
            'event' => [
                'title' => $eventName,
                'meta' => $row->disaster_id ? 'Event ID '.$row->disaster_id : 'General radio log',
            ],
            'team_route' => [
                'title' => $teamName,
                'meta' => trim(($row->team_code ?: 'Team').' - '.$responderName),
            ],
            'channel' => [
                'title' => $this->radioChannelLabel($channel),
                'meta' => $typeLabel,
            ],
            'transmission' => [
                'title' => $this->radioDisplayMessage($type, $message, $responderName),
                'meta' => $message['transmission_id'] ?? 'Signal / metadata log',
            ],
            'status' => [
                'label' => $typeLabel,
                'tone' => $this->statusTone($type),
            ],
            'details' => $this->details([
                'Communication ID' => $row->communication_id,
                'Date / time' => $this->formatDateTime($row->timestamp),
                'Event' => $eventName,
                'Responder' => $responderName,
                'Responder code' => $row->responder_code ?: ($message['responder_code'] ?? 'Not recorded'),
                'Team' => $teamName,
                'Channel' => $this->radioChannelLabel($channel),
                'Type' => $typeLabel,
                'Transmission ID' => $message['transmission_id'] ?? 'Not recorded',
                'Duration' => isset($message['duration_seconds']) ? ((int) $message['duration_seconds']).' seconds' : 'Not recorded',
                'Signal' => $message['signal'] ?? 'Not recorded',
                'Audio status' => $message['audio_status'] ?? 'Metadata only',
            ]),
            'export' => [
                'datetime' => $this->formatDateTime($row->timestamp),
                'event' => $eventName,
                'reference' => 'COM-'.$row->communication_id,
                'team_responder' => $teamName.' - '.$responderName,
                'channel' => $this->radioChannelLabel($channel),
                'transmission' => $this->radioDisplayMessage($type, $message, $responderName),
                'status' => $typeLabel,
            ],
        ];
    }

    public function formatSituationReport(object $row): array
    {
        $summary = $this->decodeJson($row->summary);
        $report = $summary['report'] ?? [];
        $household = $summary['household'] ?? [];
        $casualties = $summary['casualties'] ?? [];
        $event = $summary['event'] ?? [];
        $reportNumber = $row->report_number ?: 'SITREP-'.$row->sit_rep_id;
        $eventName = $row->event_name ?: ($event['name'] ?? 'Disaster event');
        $period = ($report['period_start'] ?? $this->formatDateTime($row->event_started_at)).' - '.($report['period_end'] ?? $this->formatDateTime($row->generated_at));
        $population = (int) ($household['total'] ?? 0).' HH - '.(int) ($household['evacuated'] ?? 0).' evacuated';
        $casualtyMeta = (int) ($casualties['deaths'] ?? 0).' deaths - '.(int) ($casualties['missing'] ?? 0).' missing - '.(int) ($casualties['injured'] ?? 0).' injured';
        $status = $this->statusKey($row->report_status ?: 'generated');

        $record = [
            'id' => $row->sit_rep_id,
            'event_id' => $row->disaster_id,
            'event_name' => $eventName,
            'purok_text' => $event['scope'] ?? ($summary['event']['scope'] ?? 'Barangay scope'),
            'status_key' => trim($status.' generated '.$this->eventStatusKey($row->event_ended_at)),
            'sitrep' => [
                'title' => $reportNumber,
                'meta' => 'Generated '.$this->formatDateTime($row->generated_at),
            ],
            'event' => [
                'title' => $eventName,
                'meta' => ($row->type_name ?: ($event['type'] ?? 'Disaster event')).' - '.($event['severity'] ?? 'Severity not recorded'),
            ],
            'period' => [
                'title' => $period,
                'meta' => $event['scope'] ?? 'Barangay scope',
            ],
            'submitted_by' => [
                'title' => $report['prepared_by'] ?? 'HQ/Admin Desk',
                'meta' => 'Reviewed by '.($report['reviewed_by'] ?? $row->escalated_to ?? 'Incident Commander'),
            ],
            'population' => [
                'title' => $population,
                'meta' => $casualtyMeta,
            ],
            'status' => [
                'label' => $this->label($status),
                'tone' => $status === 'generated' ? 'purple' : $this->statusTone($status),
            ],
        ];

        $record['details'] = $this->details([
            'SitRep No.' => $reportNumber,
            'Event / disaster type' => $record['event']['title'].' - '.$record['event']['meta'],
            'Reporting period' => $period,
            'Submitted by' => $record['submitted_by']['title'].' - '.$record['submitted_by']['meta'],
            'Population / casualties' => $population.' - '.$casualtyMeta,
            'Status' => $record['status']['label'],
            'Generated at' => $this->formatDateTime($row->generated_at),
        ]);
        $record['export'] = [
            'datetime' => $this->formatDateTime($row->generated_at),
            'event' => $eventName,
            'reference' => $reportNumber,
            'period' => $period,
            'submitted_by' => $record['submitted_by']['title'],
            'population' => $population,
            'status' => $record['status']['label'],
        ];

        return $record;
    }

    public function details(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $value, string $label): array => [
                'label' => $label,
                'value' => $value ?: 'Not recorded',
            ])
            ->values()
            ->all();
    }

    public function dispatchOutcomeText(array $outcome, ?string $rawNotes): string
    {
        if (! empty($outcome)) {
            $safe = (int) ($outcome['safe_count'] ?? 0);
            $evacuated = (int) ($outcome['evacuated_count'] ?? 0);
            $unsafe = (int) ($outcome['unsafe_count'] ?? 0);
            $injured = (int) ($outcome['injured_count'] ?? 0);
            $missing = (int) ($outcome['missing_count'] ?? 0);

            return $safe.' safe - '.$evacuated.' evacuated - '.$unsafe.' unsafe - '.$injured.' injured - '.$missing.' missing';
        }

        return $rawNotes ?: 'No outcome logged yet';
    }

    public function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function statusKey(?string $status): string
    {
        $key = strtolower(trim((string) $status));
        $key = str_replace([' ', '-'], '_', $key);

        return match ($key) {
            '', 'all_statuses' => 'all',
            'on_scene', 'onscene' => 'on_scene',
            'needs_validation', 'pending', 'needs_validation_' => 'needs_validation',
            'validated', 'approved' => 'verified',
            'filed' => 'archived',
            default => $key,
        };
    }

    public function statusTone(?string $status): string
    {
        $key = $this->statusKey($status);

        return match ($key) {
            'safe', 'evacuated', 'verified', 'forwarded', 'fulfilled', 'completed', 'on_scene', 'returned_home', 'generated' => 'green',
            'active', 'dispatched', 'en_route', 'needs_validation', 'pending', 'assigned' => 'amber',
            'unsafe', 'injured', 'missing', 'not_evacuated', 'displaced', 'returned', 'cancelled', 'failed', 'critical', 'ptt_start', 'ptt_heartbeat' => 'red',
            'reviewed', 'archived' => 'purple',
            default => 'gray',
        };
    }

    public function radioDisplayMessage(string $type, array $message, string $responderName): string
    {
        return match ($type) {
            'ptt_start' => $responderName.' started PTT',
            'ptt_heartbeat' => $responderName.' was transmitting',
            'ptt_audio' => $responderName.' sent a voice clip',
            'ptt_end' => $responderName.' ended PTT after '.((int) ($message['duration_seconds'] ?? 0)).'s',
            'quick_signal' => $responderName.' sent "'.((string) ($message['signal'] ?? 'Signal')).'"',
            default => (string) ($message['text'] ?? $responderName.' sent a radio log'),
        };
    }

    public function radioTypeLabel(string $type, array $message): string
    {
        return match ($type) {
            'ptt_start' => 'PTT started',
            'ptt_heartbeat' => 'PTT active',
            'ptt_audio' => 'Voice clip',
            'ptt_end' => 'PTT ended',
            'quick_signal' => (string) ($message['signal'] ?? 'Signal'),
            default => 'Radio log',
        };
    }

    public function radioChannelLabel(string $channel): string
    {
        return match ($channel) {
            'command' => 'HQ Command',
            'event' => 'Event',
            default => 'Team',
        };
    }

    public function eventStatusKey(?string $endedAt): string
    {
        return $endedAt ? 'closed' : 'active';
    }

    public function isCriticalSeverity(?string $key, ?string $label): bool
    {
        $text = strtolower(trim((string) $key.' '.(string) $label));

        return str_contains($text, 'high')
            || str_contains($text, 'critical')
            || str_contains($text, 'severe');
    }

    public function purokFromText(?string $text): string
    {
        $value = trim((string) $text);

        if ($value === '') {
            return 'Unassigned';
        }

        if (preg_match('/purok\s*[0-9a-z-]+/i', $value, $matches)) {
            return $matches[0];
        }

        return $value;
    }

    public function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    public function formatDateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : 'Not recorded';
    }

    public function formatDate(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('M Y') : 'No date';
    }

    public function formatTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : 'No time';
    }
}


