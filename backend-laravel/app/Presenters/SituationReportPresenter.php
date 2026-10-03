<?php

namespace App\Presenters;

use Carbon\Carbon;

class SituationReportPresenter
{
    public function buildSummary(object $event, array $sources, array $profile, array $meta = []): array
    {
        $household = $this->householdSummary($sources);
        $dispatch = $this->dispatchSummary($sources);
        $weather = $this->weatherSummary($sources['weather']);
        $evacuation = $this->evacuationSummary($sources['evacuation']);
        $resources = $this->resourceSummary($sources['requests']);
        $casualties = $this->casualtySummary($dispatch);

        return [
            'report' => [
                'report_number' => $meta['report_number'] ?? 'SITREP-DRAFT-'.now()->format('Ymd'),
                'period_start' => $this->formatDateTime($meta['period_start'] ?? $event->started_at),
                'period_end' => $this->formatDateTime($meta['period_end'] ?? now()->toDateTimeString()),
                'prepared_by' => $meta['prepared_by'] ?? 'HQ/Admin Desk',
                'reviewed_by' => $meta['reviewed_by'] ?? 'Incident Commander',
                'generated_at' => $this->formatDateTime($meta['generated_at'] ?? now()->toDateTimeString()),
                'next_report' => $event->ended_at ? 'Final report filed' : $this->formatDateTime(now()->addHours(2)->toDateTimeString()),
            ],
            'barangay_profile' => $profile,
            'event' => [
                'event_id' => $event->event_id, 'name' => $event->name,
                'type' => $event->type_name ?? 'Disaster event', 'severity' => $event->severity_label ?? 'Unspecified',
                'severity_key' => $event->severity_key ?? 'medium', 'declared_at' => $this->formatDateTime($event->started_at),
                'declared_time' => $this->formatTime($event->started_at),
                'finished_at' => $event->ended_at ? $this->formatDateTime($event->ended_at) : 'Ongoing',
                'status' => $event->ended_at ? 'Closed' : 'Active',
                'situation_status' => $event->ended_at ? 'Final monitoring closed' : 'Active monitoring',
                'scope' => 'All registered households in barangay scope', 'coverage' => $household['total'].' households in scope',
            ],
            'weather' => $weather, 'household' => $household, 'casualties' => $casualties,
            'evacuation' => $evacuation, 'dispatch' => $dispatch,
            'damage' => ['partial' => 'For validation', 'total' => 'For validation', 'cost' => 'For validation'],
            'resources' => $resources, 'actions_text' => $meta['actions_text'] ?? '',
            'included_sections' => $meta['included_sections'] ?? [],
        ];
    }

    public function formatSavedReport(?object $report, bool $includeSummary = false): ?array
    {
        if (! $report) return null;
        $summary = $this->decodeJson($report->summary);
        $data = [
            'sit_rep_id' => $report->sit_rep_id, 'report_number' => $report->report_number,
            'event_id' => $report->disaster_id, 'event_name' => $report->event_name ?? ($summary['event']['name'] ?? 'Disaster event'),
            'report_status' => $this->label($report->report_status),
            'generated_at' => $this->formatDateTime($report->generated_at),
            'generated_time' => $this->formatTime($report->generated_at), 'is_archived' => (bool) $report->is_archived,
        ];
        if ($includeSummary) $data['summary'] = $summary;
        return $data;
    }

    public function formatEventOption(object $event): array
    {
        return [
            'event_id' => $event->event_id, 'name' => $event->name,
            'label' => $event->name.' - '.$this->formatDate($event->started_at),
            'type' => $event->type_name ?? 'Disaster event', 'severity' => $event->severity_label ?? 'Unspecified',
            'declared_at' => $this->formatDateTime($event->started_at),
            'finished_at' => $event->ended_at ? $this->formatDateTime($event->ended_at) : 'Ongoing',
            'status' => $event->ended_at ? 'Closed' : 'Active', 'scope' => 'All registered households',
        ];
    }

    private function householdSummary(array $sources): array
    {
        $counts = $sources['household_counts'];
        $total = (int) $sources['household_total'];
        $reported = (int) $sources['household_reported'];
        $safeOnly = $this->sumStatusKeys($counts, ['active', 'returned', 'safe']);
        $evacuated = $this->sumStatusKeys($counts, ['evacuated', 'relocated']);
        $unsafe = $this->sumStatusKeys($counts, ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured']);
        $safeTotal = $safeOnly + $evacuated;
        $unchecked = max($total - $reported, 0);
        $progress = $total > 0 ? round(($reported / $total) * 100) : 0;
        $purokRows = collect($sources['purok_rows'])->map(function (object $row): array {
            return ['purok' => $row->purok, 'total' => (int) $row->total, 'safe' => (int) $row->safe,
                'evacuated' => (int) $row->evacuated, 'unsafe' => (int) $row->unsafe,
                'unchecked' => max((int) $row->total - (int) $row->reported, 0)];
        })->values()->all();

        return [
            'total' => $total, 'reported' => $reported, 'progress_percent' => $progress,
            'progress_text' => $progress.'%', 'progress_sub' => $reported.' / '.$total,
            'safe_total' => $safeTotal, 'safe_only' => $safeOnly, 'evacuated' => $evacuated,
            'unsafe' => $unsafe, 'unchecked' => $unchecked,
            'safe_percent' => $this->percent($safeTotal, $total), 'evacuated_percent' => $this->percent($evacuated, $total),
            'unsafe_percent' => $this->percent($unsafe, $total), 'unchecked_percent' => $this->percent($unchecked, $total),
            'puroks' => $purokRows,
        ];
    }

    private function weatherSummary(?object $weather): array
    {
        if (! $weather) return ['condition' => 'No weather snapshot yet', 'wind' => 'For update', 'rainfall' => 'For update', 'temperature' => 'For update', 'source' => 'Confirm through PAGASA', 'advisory' => 'Confirm official warnings through PAGASA before broadcasting.'];
        return [
            'condition' => $weather->condition_name ?: 'Weather monitoring',
            'wind' => $weather->wind_speed !== null ? $weather->wind_speed.' km/h '.$weather->wind_direction : 'For update',
            'rainfall' => $weather->rainfall_mm !== null ? $weather->rainfall_mm.' mm' : 'For update',
            'temperature' => $weather->temperature !== null ? $weather->temperature.' C' : 'For update',
            'source' => trim($weather->source_name.' - '.$this->formatTime($weather->observed_at)),
            'advisory' => $weather->advisory_text ?: 'Confirm official warnings through PAGASA before broadcasting.',
        ];
    }

    private function evacuationSummary($centers): array
    {
        return $centers->map(function (object $center): array {
            $capacity = (int) ($center->capacity ?? 0); $occupancy = (int) ($center->current_occupancy ?? 0); $remaining = max($capacity - $occupancy, 0);
            return ['name' => $center->name ?: $center->evacuation_center_id, 'type' => $center->center_type ?: 'Evacuation site',
                'status' => $center->status ?: 'active', 'families' => 'For update',
                'persons' => $occupancy > 0 ? $occupancy.' persons' : 'Occupancy not encoded',
                'capacity_status' => $capacity > 0 ? $remaining.' slots left' : 'Capacity not encoded',
                'capacity_tone' => $capacity > 0 && $remaining <= 10 ? 'amber' : 'green'];
        })->values()->all();
    }

    private function dispatchSummary(array $sources): array
    {
        $assignments = $sources['assignments'];
        $rows = $assignments->take(8)->map(function (object $assignment): array {
            $route = $this->decodeJson($assignment->route_notes); $outcomes = $this->decodeJson($assignment->outcome_notes);
            return ['team' => $assignment->team_name ?: $assignment->responder_name ?: 'Assigned responder',
                'deployed_at' => $this->formatTime($assignment->assigned_at), 'area' => $assignment->assigned_area ?: 'Area not encoded',
                'households_reached' => $this->reachedHouseholds($outcomes).' / '.(int) ($route['households_to_cover'] ?? 0),
                'outcomes' => $this->outcomeText($outcomes), 'status' => $this->label($assignment->status ?: 'assigned'),
                'status_tone' => $this->statusTone($assignment->status), 'outcome_counts' => $outcomes];
        })->values()->all();
        $timeline = collect();
        foreach ($sources['broadcasts'] as $broadcast) $timeline->push(['time' => $this->formatTime($broadcast->sent_at), 'title' => 'Public broadcast', 'actor' => $broadcast->broadcast_title ?: 'HQ/Admin desk', 'detail' => $broadcast->scope_type ? $this->label($broadcast->scope_type).' alert sent' : 'Alert sent', 'status' => $this->label($broadcast->status ?: 'sent'), 'tone' => 'green']);
        foreach ($assignments->take(4) as $assignment) $timeline->push(['time' => $this->formatTime($assignment->assigned_at), 'title' => 'Dispatch assignment', 'actor' => $assignment->team_name ?: $assignment->responder_name ?: 'Response team', 'detail' => $assignment->assigned_area ?: 'No area encoded', 'status' => $this->label($assignment->status ?: 'assigned'), 'tone' => $this->statusTone($assignment->status)]);
        $stats = ['total' => 0, 'dispatched' => 0, 'on_scene' => 0, 'completed' => 0];
        $outcomes = ['safe_count' => 0, 'evacuated_count' => 0, 'unsafe_count' => 0, 'injured_count' => 0, 'missing_count' => 0];
        foreach ($sources['assignment_outcomes'] as $assignment) {
            $stats['total']++;
            if (in_array($assignment->status, ['dispatched', 'en_route', 'accepted'], true)) $stats['dispatched']++;
            if (in_array($assignment->status, ['on_scene', 'onscene'], true)) $stats['on_scene']++;
            if ($assignment->status === 'completed') $stats['completed']++;
            $detail = $this->decodeJson($assignment->outcome_notes);
            foreach (array_keys($outcomes) as $key) $outcomes[$key] += (int) ($detail[$key] ?? 0);
        }
        return [...$stats, 'rows' => $rows,
            'timeline' => $timeline->sortBy('time')->values()->all(), 'raw_outcomes' => [$outcomes]];
    }

    private function casualtySummary(array $dispatch): array
    {
        $safe = $evacuated = $unsafe = $injured = $missing = 0;
        foreach ($dispatch['raw_outcomes'] as $outcomes) { $safe += (int) ($outcomes['safe_count'] ?? 0); $evacuated += (int) ($outcomes['evacuated_count'] ?? 0); $unsafe += (int) ($outcomes['unsafe_count'] ?? 0); $injured += (int) ($outcomes['injured_count'] ?? 0); $missing += (int) ($outcomes['missing_count'] ?? 0); }
        return ['deaths' => 0, 'missing' => $missing, 'injured' => $injured, 'rescued' => $safe + $evacuated, 'unsafe' => $unsafe];
    }

    private function resourceSummary($requests): array
    {
        return ['needs_validation' => $requests->where('validation_status', 'needs_validation')->count(), 'verified' => $requests->where('validation_status', 'verified')->count(),
            'forwarded' => $requests->where('validation_status', 'forwarded')->count(), 'returned' => $requests->where('validation_status', 'returned')->count(),
            'rows' => $requests->map(fn (object $request): array => ['item' => $request->item_name ?: $request->resource_type ?: 'Request',
                'quantity' => trim(($request->quantity ?? 0).' '.($request->unit ?? '')), 'source' => $request->request_source ? $this->label($request->request_source) : 'Shared DB',
                'status' => $this->label($request->validation_status ?: 'needs_validation'), 'status_tone' => $this->statusTone($request->validation_status)])->values()->all()];
    }

    private function sumStatusKeys($counts, array $keys): int { return collect($keys)->sum(fn (string $key): int => (int) ($counts[$key] ?? 0)); }
    private function percent(int $value, int $total): int { return $total > 0 ? (int) round(($value / $total) * 100) : 0; }
    private function decodeJson(?string $value): array { $decoded = $value ? json_decode($value, true) : null; return is_array($decoded) ? $decoded : []; }
    private function reachedHouseholds(array $outcomes): int { return (int) ($outcomes['safe_count'] ?? 0) + (int) ($outcomes['evacuated_count'] ?? 0) + (int) ($outcomes['unsafe_count'] ?? 0) + (int) ($outcomes['injured_count'] ?? 0) + (int) ($outcomes['missing_count'] ?? 0); }
    private function outcomeText(array $outcomes): string { return implode(' / ', ['safe '.$this->countValue($outcomes, 'safe_count'), 'evac '.$this->countValue($outcomes, 'evacuated_count'), 'unsafe '.$this->countValue($outcomes, 'unsafe_count'), 'injured '.$this->countValue($outcomes, 'injured_count'), 'missing '.$this->countValue($outcomes, 'missing_count')]); }
    private function countValue(array $items, string $key): int { return (int) ($items[$key] ?? 0); }
    private function statusTone(?string $status): string { return match (str_replace('-', '_', strtolower((string) $status))) { 'completed', 'verified', 'forwarded', 'sent', 'on_scene', 'onscene' => 'green', 'needs_validation', 'pending', 'dispatched', 'en_route' => 'amber', 'returned', 'cancelled', 'failed' => 'red', default => 'gray' }; }
    private function label(?string $value): string { return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown')); }
    private function formatDateTime(?string $value): ?string { return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null; }
    private function formatDate(?string $value): ?string { return $value ? Carbon::parse($value)->format('M Y') : null; }
    private function formatTime(?string $value): ?string { return $value ? Carbon::parse($value)->format('g:i A') : null; }
}


