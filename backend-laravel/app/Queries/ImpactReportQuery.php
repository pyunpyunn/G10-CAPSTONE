<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImpactReportQuery
{
    private const COLORS = ['#2563eb', '#dc2626', '#059669', '#7c3aed', '#d97706', '#0891b2', '#db2777', '#65a30d', '#4f46e5', '#ea580c', '#0d9488', '#9333ea', '#a16207', '#0284c7', '#be123c', '#15803d', '#6d28d9', '#475569'];

    public function __construct(
        private RescueCriteriaHistoryQuery $history,
        private DashboardImpactQuery $dashboardImpact,
        private SitioPriorityQuery $sitioPriorities,
    ) {}

    public function sitioRanking(string $eventId, array $rankingTypes): array
    {
        $event = $this->event($eventId);
        $sitios = $this->series($event);
        $statusPeaks = [];
        $priorityRows = [];
        $statusKeys = [];

        foreach ($sitios as $sitio) {
            $points = $sitio['points'];
            $current = $points ? $points[array_key_last($points)] : null;
            $priorityPeak = null;
            $metricPeaks = [];

            foreach ($points as $point) {
                $counts = $point['counts'];
                foreach ($counts['status_counts'] ?? [] as $status => $quantity) {
                    $statusKeys[$status] = true;
                    if (! isset($statusPeaks[$sitio['sitio_id']][$status]) || $quantity > $statusPeaks[$sitio['sitio_id']][$status]['quantity']) {
                        $statusPeaks[$sitio['sitio_id']][$status] = ['quantity' => (int) $quantity, 'at' => $point['observed_at']];
                    }
                }

                $score = $this->priorityScore($counts);
                if ($priorityPeak === null || $score > $priorityPeak['score']) {
                    $priorityPeak = ['score' => $score, 'at' => $point['observed_at']];
                }
                foreach (['impact_open', 'special_open', 'unreported_open', 'no_contact_open'] as $metric) {
                    if (! isset($metricPeaks[$metric]) || $counts[$metric] > $metricPeaks[$metric]['quantity']) {
                        $metricPeaks[$metric] = ['quantity' => (int) $counts[$metric], 'at' => $point['observed_at']];
                    }
                }
            }

            $priorityRows[] = [
                'sitio' => $sitio['sitio'],
                'current_score' => $current ? $this->priorityScore($current['counts']) : 0,
                'peak' => $priorityPeak ?? ['score' => 0, 'at' => null],
                'current_counts' => $current['counts'] ?? [],
                'metric_peaks' => $metricPeaks,
            ];
        }

        $statusGroups = [];
        foreach (array_keys($statusKeys) as $status) {
            $entries = [];
            foreach ($sitios as $sitio) {
                $peak = $statusPeaks[$sitio['sitio_id']][$status] ?? null;
                if ($peak === null) {
                    continue;
                }
                $current = $sitio['points'] ? $sitio['points'][array_key_last($sitio['points'])] : null;
                $entries[] = [
                    'sitio' => $sitio['sitio'],
                    'quantity' => $peak['quantity'],
                    'current' => (int) ($current['counts']['status_counts'][$status] ?? 0),
                    'peak_at' => $peak['at'],
                ];
            }
            usort($entries, fn (array $left, array $right): int => ($right['quantity'] <=> $left['quantity']) ?: strcmp($left['sitio'], $right['sitio']));
            foreach ($entries as $index => &$entry) {
                $entry['rank'] = $index + 1;
            }
            unset($entry);
            $statusGroups[] = ['status' => $status, 'entries' => $entries];
        }

        usort($priorityRows, fn (array $left, array $right): int => ($right['peak']['score'] <=> $left['peak']['score']) ?: strcmp($left['sitio'], $right['sitio']));
        foreach ($priorityRows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        $chartSeries = [];
        if (in_array('status', $rankingTypes, true)) {
            foreach (array_keys($statusKeys) as $status) {
                $chartSeries[] = [
                    'label' => $status,
                    'color' => self::COLORS[count($chartSeries) % count(self::COLORS)],
                    'values' => array_map(fn (array $sitio): int => (int) ($statusPeaks[$sitio['sitio_id']][$status]['quantity'] ?? 0), $sitios),
                ];
            }
        }

        return [
            'type' => 'ranking',
            'title' => 'Sitio ranking',
            'event_name' => $event->name,
            'event_id' => $event->event_id,
            'generated_at' => now()->toDateTimeString(),
            'ranking_types' => $rankingTypes,
            'ui_rows' => $this->sitioPriorities->ranked($eventId),
            'status_groups' => in_array('status', $rankingTypes, true) ? $statusGroups : [],
            'priority_rows' => in_array('priority', $rankingTypes, true) ? $priorityRows : [],
            'chart' => [
                'kind' => 'ranking',
                'labels' => array_column($sitios, 'sitio'),
                'status_series' => $chartSeries,
                'priority_series' => in_array('priority', $rankingTypes, true) ? [[
                    'label' => 'Highest priority score',
                    'color' => '#2563eb',
                    'values' => array_map(fn (array $row): float => (float) $row['peak']['score'], $priorityRows),
                    'labels' => array_column($priorityRows, 'sitio'),
                ]] : [],
            ],
        ];
    }

    public function purokImpact(string $eventId): array
    {
        $event = $this->event($eventId);
        $sitios = $this->series($event);
        $timeline = [];
        $series = [];
        $summaries = [];

        foreach ($sitios as $sitio) {
            foreach ($sitio['points'] as $point) {
                $hour = (float) $point['hours_since_alert'];
                $timeline[sprintf('%.8F', $hour)] = $hour;
            }
        }
        asort($timeline, SORT_NUMERIC);
        $hours = array_values($timeline);
        $elapsedHours = max(0, Carbon::parse($event->started_at)->diffInSeconds($event->ended_at ? Carbon::parse($event->ended_at) : now(), false) / 3600,
            ...($hours ?: [0]));

        foreach ($sitios as $index => $sitio) {
            $points = $sitio['points'];
            $valuesByHour = [];
            $peak = 0;
            $peakPoint = null;
            foreach ($points as $point) {
                $hourKey = sprintf('%.8F', (float) $point['hours_since_alert']);
                $valuesByHour[$hourKey] = $point['purok_impact'] === null ? null : (float) $point['purok_impact'];
                if ($point['purok_impact'] !== null && ($peakPoint === null || (float) $point['purok_impact'] >= $peak)) {
                    $peak = (float) $point['purok_impact'];
                    $peakPoint = $point;
                }
            }
            $summaries[] = [
                'sitio' => $sitio['sitio'],
                'latest' => $points ? (float) $points[array_key_last($points)]['purok_impact'] : 0,
                'peak' => (float) $peak,
                'peak_at' => $peakPoint['observed_at'] ?? null,
            ];
            $samples = [];
            foreach ($points as $point) {
                $samples[sprintf('%.8F', (float) $point['hours_since_alert'])] = [
                    'x' => (float) $point['hours_since_alert'],
                    'y' => $point['purok_impact'] === null ? null : (float) $point['purok_impact'],
                ];
            }
            $samples = array_values($samples);
            usort($samples, fn ($a, $b) => $a['x'] <=> $b['x']);
            if ($samples && $samples[array_key_last($samples)]['x'] < $elapsedHours) {
                $samples[] = ['x' => $elapsedHours, 'y' => $samples[array_key_last($samples)]['y']];
            }
            $series[] = [
                'label' => $sitio['sitio'],
                'color' => self::COLORS[$index % count(self::COLORS)],
                'samples' => $samples,
                'values' => array_map(fn (float $hour): ?float => $valuesByHour[sprintf('%.8F', $hour)] ?? null, $hours),
            ];
        }

        return [
            'type' => 'purok-impact',
            'title' => 'Purok impact by sitio over time',
            'event_name' => $event->name,
            'event_id' => $event->event_id,
            'generated_at' => now()->toDateTimeString(),
            'summaries' => $summaries,
            'chart' => [
                'kind' => 'line',
                'axis_label' => 'Hours since the disaster broadcast',
                'value_label' => 'Weighted purok impact per sitio',
                'max_value' => 100,
                'x_values' => $hours,
                'x_max_value' => max(1 / 3600, $elapsedHours),
                'labels' => array_map(fn (float $hour): string => $this->hoursLabel($hour), $hours),
                'series' => $series,
            ],
        ];
    }

    public function pastEvents(string $period = 'monthly'): array
    {
        $data = $this->dashboardImpact->history($period);
        $metrics = [
            ['key' => 'deaths', 'label' => 'Deaths', 'color' => '#dc2626'],
            ['key' => 'injured', 'label' => 'Injured', 'color' => '#d97706'],
            ['key' => 'missing', 'label' => 'Missing', 'color' => '#7c3aed'],
            ['key' => 'damaged_houses', 'label' => 'Damaged houses', 'color' => '#2563eb'],
        ];

        return [
            'type' => 'past-event-impact',
            'title' => 'Past disaster events — overall impact in Mambaling',
            'generated_at' => now()->toDateTimeString(),
            'period' => $period,
            'event_count' => $data['event_count'],
            'assessed_households' => $data['assessed_households'],
            'totals' => $data['totals'],
            'periods' => $data['periods'],
            'chart' => [
                'kind' => 'line',
                'axis_label' => ucfirst($period).' period',
                'value_label' => 'Recorded count',
                'labels' => array_column($data['periods'], 'label'),
                'series' => array_map(fn (array $metric): array => [
                    'label' => $metric['label'],
                    'color' => $metric['color'],
                    'values' => array_map(fn (array $row): int => (int) $row[$metric['key']], $data['periods']),
                ], $metrics),
            ],
        ];
    }

    private function series(object $event): array
    {
        if (! $event->started_at) {
            throw ValidationException::withMessages(['event_id' => 'The selected event has no recorded start time.']);
        }

        return array_values(array_filter(
            $this->history->sitioSeries((string) $event->event_id, Carbon::parse($event->started_at),
                $event->ended_at ? Carbon::parse($event->ended_at) : null),
            fn (array $sitio): bool => ! ($sitio['is_demo'] ?? false),
        ));
    }

    private function event(string $eventId): object
    {
        $query = DB::table('disaster_events')->where('event_id', $eventId);
        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $event = $query->first(['event_id', 'name', 'started_at', 'ended_at']);
        if (! $event) {
            throw ValidationException::withMessages(['event_id' => 'The selected disaster event was not found.']);
        }

        return $event;
    }

    private function priorityScore(array $counts): float
    {
        $share = static fn (int $open, int $total): float => $total > 0 ? $open / $total : 0;
        $criteria = [
            'unsafe_reports' => $share((int) $counts['impact_open'], (int) $counts['household_total']),
            'special_needs' => $share((int) $counts['special_open'], (int) $counts['vulnerable_total']),
            'no_contact' => $share((int) $counts['no_contact_open'], (int) $counts['no_contact_total']),
            'unreported_members' => $share((int) $counts['unreported_open'], (int) $counts['member_total']),
        ];
        $weights = RescueCriteriaQuery::SITIO_WEIGHTS;
        $score = array_sum(array_map(fn (string $key, int $weight): float => $criteria[$key] * $weight,
            array_keys($weights), array_values($weights)));

        return round($score, 1);
    }

    private function hoursLabel(float $hours): string
    {
        return number_format($hours, 2, '.', '').'h';
    }
}
