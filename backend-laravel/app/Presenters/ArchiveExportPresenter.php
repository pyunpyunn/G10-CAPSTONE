<?php

namespace App\Presenters;

class ArchiveExportPresenter
{
    public function headers(string $category): array
    {
        return match ($category) {
            'household-status-logs' => ['datetime' => 'Date / time', 'event' => 'Event', 'reference' => 'Reference', 'household' => 'Household', 'purok' => 'Purok', 'status' => 'Status', 'source' => 'Source'],
            'dispatch-logs' => ['datetime' => 'Date / time', 'event' => 'Event', 'reference' => 'Reference', 'team_route' => 'Team / route', 'purok' => 'Purok', 'status' => 'Status', 'outcome' => 'Outcome'],
            'radio-communication-logs' => ['datetime' => 'Date / time', 'event' => 'Event', 'reference' => 'Reference', 'team_responder' => 'Team / responder', 'channel' => 'Channel', 'transmission' => 'Transmission', 'status' => 'Status'],
            'resource-requests' => ['datetime' => 'Date / time', 'event' => 'Event', 'reference' => 'Reference', 'request' => 'Request', 'purok_site' => 'Purok / site', 'status' => 'Status', 'handoff' => 'Handoff'],
            'situation-reports' => ['datetime' => 'Generated at', 'event' => 'Event', 'reference' => 'SitRep No.', 'period' => 'Reporting period', 'submitted_by' => 'Submitted by', 'population' => 'Population', 'status' => 'Status'],
            default => ['event' => 'Event', 'reference' => 'Reference', 'type_weather' => 'Disaster / weather', 'period' => 'Date declared / finished', 'broadcasts' => 'Broadcast logs', 'scope' => 'Household scope', 'status' => 'Status'],
        };
    }

    public function row(array $record, string $category): array
    {
        $export = $record['export'] ?? [];

        return array_map(static fn (string $key): mixed => $export[$key] ?? '', array_keys($this->headers($category)));
    }
}
