<?php

namespace App\Presenters;

use App\Queries\ArchiveEventDetailQuery;

class ArchiveEventPresenter
{
    public function __construct(private ArchiveEventDetailQuery $details, private ArchivePresenter $presenter) {}

    public function present(object $row): array
    {
        return $this->presentWithDetails($row, $this->details->forEvent((string) $row->event_id));
    }

    public function presentBatch(iterable $rows): array
    {
        $batch = collect($rows);
        $details = $this->details->forEvents($batch->pluck('event_id')->map(fn (mixed $id): string => (string) $id)->all());

        return $batch->map(fn (object $row): array => $this->presentWithDetails($row, $details[(string) $row->event_id]))->all();
    }

    private function presentWithDetails(object $row, array $details): array
    {
        $weather = $details['weather'];
        $scopeText = $details['purok_scope'];
        $isCritical = $this->presenter->isCriticalSeverity($row->severity_key, $row->severity_label);
        $record = [
            'id' => $row->event_id,
            'event_id' => $row->event_id,
            'event_name' => $row->name,
            'purok_text' => $scopeText,
            'status_key' => trim(($row->ended_at ? 'closed' : 'active').' '.($isCritical ? 'critical' : '')),
            'event' => ['title' => $row->name, 'meta' => 'Event ID '.$row->event_id],
            'disaster' => ['title' => ($row->type_name ?: 'Disaster event').' - '.($weather['condition'] ?: 'Weather not saved'), 'meta' => $weather['meta'] ?: ($row->severity_label ?: 'Severity not recorded')],
            'period' => ['title' => $this->presenter->formatDateTime($row->started_at).' - '.($row->ended_at ? $this->presenter->formatDateTime($row->ended_at) : 'Ongoing'), 'meta' => $details['archive']?->archive_note ?: ($row->ended_at ? 'Closed event log' : 'Active event log')],
            'broadcasts' => ['title' => $details['broadcast_count'].' broadcast'.($details['broadcast_count'] === 1 ? '' : 's'), 'meta' => $details['latest_broadcast'] ? 'Latest: '.$details['latest_broadcast']->broadcast_title.' - '.$this->presenter->formatTime($details['latest_broadcast']->sent_at) : 'No broadcast saved'],
            'scope' => ['title' => $details['household_scope'].' HH in scope', 'meta' => $scopeText],
            'status' => ['label' => $row->ended_at ? 'Closed' : 'Active', 'tone' => $row->ended_at ? 'gray' : ($isCritical ? 'red' : 'blue')],
        ];
        $record['details'] = $this->presenter->details(['Event' => $record['event']['title'], 'Event ID' => $row->event_id, 'Disaster / weather' => $record['disaster']['title'], 'Declared / finished' => $record['period']['title'], 'Broadcast logs' => $record['broadcasts']['title'].' - '.$record['broadcasts']['meta'], 'Household scope' => $record['scope']['title'].' - '.$record['scope']['meta'], 'Status' => $record['status']['label']]);
        $record['export'] = ['event' => $row->name, 'reference' => $row->event_id, 'type_weather' => $record['disaster']['title'], 'period' => $record['period']['title'], 'broadcasts' => $record['broadcasts']['title'], 'scope' => $record['scope']['title'], 'status' => $record['status']['label']];
        return $record;
    }
}


