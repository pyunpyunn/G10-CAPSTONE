<?php

namespace App\Queries;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use App\Services\Shared\BarangayProfileService;
use App\Presenters\RescueCriteriaPresenter;

class RescueCriteriaQuery
{
    public const SITIO_WEIGHTS = ['unsafe_reports' => 30, 'special_needs' => 30, 'no_contact' => 15, 'unreported_members' => 25];

    public function sitioTimeline(string $eventId, Carbon $startedAt): array
    {
        return $this->historyQuery->sitioSeries($eventId, $startedAt);
    }

    public function __construct(
        private RescuePriorityQuery $priorities,
        private BarangayProfileService $barangay,
        private RescueCriteriaPresenter $presenter,
        private RescueCriteriaHistoryQuery $historyQuery,
    ) {}

    public function puroks(string $eventId): array
    {
        [$scored, $settings] = $this->priorities->scored($eventId, $this->barangay->current()['barangay_id'] ?? null);
        $rows = DB::query()->fromSub($scored, 'scored')
            ->whereNotNull('area_name')->where('area_name', '<>', '')
            ->groupBy('area_name')
            ->selectRaw('area_name, COUNT(*) as households, SUM(urgent_tier) as urgent_households')
            ->selectRaw('SUM(CASE WHEN purok_id IS NULL THEN 1 ELSE 0 END) as unlinked_households')
            ->selectRaw('MAX(impacted_households) as impacted_households, MAX(vulnerable_members) as unresolved_vulnerable_members, MAX(total_vulnerable_members) as total_vulnerable_members')
            ->selectRaw('AVG(1.0 * impacted_households / CASE WHEN area_households < 1 THEN 1 ELSE area_households END) as impact_share')
            ->selectRaw('AVG(1.0 * vulnerable_members / CASE WHEN total_vulnerable_members < 1 THEN 1 ELSE total_vulnerable_members END) as vulnerability_share')
            ->selectRaw('SUM(unreported_members) as unreported_members, SUM(household_members) as total_members')
            ->selectRaw('SUM(no_contact_channel) as no_contact_households, SUM(contact_missing) as no_contact_eligible')
            ->selectRaw('CASE WHEN SUM(contact_missing) > 0 THEN 1.0 * SUM(no_contact_channel) / SUM(contact_missing) ELSE 0 END as no_contact_share')
            ->orderBy('area_name')->get();

        return $rows->map(function (object $row) use ($settings): array {
            $shares = [
                'impact' => (float) $row->impact_share,
                'special_needs' => (float) $row->vulnerability_share,
                'unreported' => (int) $row->total_members > 0 ? (int) $row->unreported_members / (int) $row->total_members : 0,
                'no_contact' => (float) $row->no_contact_share,
            ];
            $contributions = [
                'impact' => $shares['impact'] * $settings->impact_weight,
                'special_needs' => $shares['special_needs'] * $settings->vulnerability_weight,
                'unreported' => $shares['unreported'] * $settings->unreported_weight,
                'no_contact' => $shares['no_contact'] * $settings->no_contact_weight,
            ];
            $factors = [
                'impact' => round(100 * $shares['impact'], 1),
                'special_needs' => round(100 * $shares['special_needs'], 1),
                'unreported' => round(100 * $shares['unreported'], 1),
                'no_contact' => round(100 * $shares['no_contact'], 1),
            ];
            return [
                'purok' => $row->area_name,
                'households' => (int) $row->households,
                'unlinked_households' => (int) $row->unlinked_households,
                'urgent_households' => (int) $row->urgent_households,
                'impacted_households' => (int) $row->impacted_households,
                'unresolved_vulnerable_members' => (int) $row->unresolved_vulnerable_members,
                'total_vulnerable_members' => (int) $row->total_vulnerable_members,
                'unreported_members' => (int) $row->unreported_members,
                'total_members' => (int) $row->total_members,
                'no_contact_households' => (int) $row->no_contact_households,
                'no_contact_eligible' => (int) $row->no_contact_eligible,
                'priority_score' => round(array_sum($contributions), 1),
                'factors' => $factors,
                'contributions' => array_map(fn (float $value): float => round($value, 1), $contributions),
            ];
        })->sort(function (array $left, array $right): int {
            return ($right['priority_score'] <=> $left['priority_score'])
                ?: ($right['factors']['impact'] <=> $left['factors']['impact'])
                ?: strcmp($left['purok'], $right['purok']);
        })->values()->map(function (array $row, int $index) use ($settings): array {
            $row['rank'] = $index + 1;
            $row['band'] = $this->presenter->priorityBand($row['priority_score'], $row['rank'], $settings);
            return $row;
        })->all();
    }

    public function current(string $eventId, Carbon $startedAt): array
    {
        $observedAt = now()->startOfSecond();
        $puroks = $this->puroks($eventId);
        $percent = function (string $numerator, string $denominator) use ($puroks): ?float {
            $total = array_sum(array_column($puroks, $denominator));
            return $total > 0 ? round(100 * array_sum(array_column($puroks, $numerator)) / $total, 1) : null;
        };

        return [
            'observed_at' => $observedAt->toIso8601String(),
            'hours_since_alert' => max(0, round($startedAt->diffInSeconds($observedAt, false) / 3600, 6)),
            'impact' => $percent('impacted_households', 'households'),
            'special_needs' => $percent('unresolved_vulnerable_members', 'total_vulnerable_members'),
            'unreported' => $percent('unreported_members', 'total_members'),
            'no_contact' => $percent('no_contact_households', 'no_contact_eligible'),
            'purok_count' => count($puroks),
        ];
    }

    public function timeline(string $eventId, Carbon $startedAt): array
    {
        $current = $this->current($eventId, $startedAt);
        $settingsVersion = (int) $this->priorities->settingsForEvent($eventId)->version;
        $current['rescue_priority_version'] = $settingsVersion;
        $elapsedHours = max(0, (int) $startedAt->diffInHours(now(), false));
        $latestBucket = $startedAt->copy()->addHours(intdiv($elapsedHours, 3) * 3);
        $hasLatestBucket = DB::table('rescue_criteria_snapshots')
            ->where('event_id', $eventId)->where('definition_version', 6)
            ->where('observed_at', $latestBucket->toDateTimeString())->exists();
        if (! $hasLatestBucket) {
            $this->historyQuery->refresh($eventId, $startedAt);
        }

        $history = DB::table('rescue_criteria_snapshots')->where('event_id', $eventId)->where('definition_version', 6)
            ->whereBetween('observed_at', [$startedAt, Carbon::parse($current['observed_at'])])
            ->orderBy('observed_at')->get()
            ->map(fn (object $row): array => [
                'observed_at' => Carbon::parse($row->observed_at)->toIso8601String(),
                'hours_since_alert' => max(0, round($startedAt->diffInSeconds(Carbon::parse($row->observed_at), false) / 3600, 6)),
                'impact' => $row->impact === null ? null : (float) $row->impact,
                'special_needs' => $row->special_needs === null ? null : (float) $row->special_needs,
                'unreported' => $row->unreported === null ? null : (float) $row->unreported,
                'no_contact' => $row->no_contact === null ? null : (float) $row->no_contact,
                'purok_count' => (int) $row->purok_count,
                'rescue_priority_version' => $row->rescue_priority_version ?? $settingsVersion,
            ])->all();

        $latest = end($history);
        if (! $latest || ! $this->sameValues($latest, $current)) {
            $this->persistPoint($eventId, $current);
        }
        $history[] = $current;

        return $history;
    }

    private function persistPoint(string $eventId, array $point): void
    {
        if (! Schema::hasTable('rescue_criteria_snapshots')) return;

        $snapshot = [
            'event_id' => $eventId,
            'observed_at' => Carbon::parse($point['observed_at'])->toDateTimeString(),
            'definition_version' => 6,
            'impact' => $point['impact'],
            'special_needs' => $point['special_needs'],
            'unreported' => $point['unreported'],
            'no_contact' => $point['no_contact'],
            'purok_count' => $point['purok_count'],
        ];
        if (Schema::hasColumn('rescue_criteria_snapshots', 'rescue_priority_version')) {
            $snapshot['rescue_priority_version'] = $point['rescue_priority_version'] ?? null;
        }

        $updates = ['definition_version', 'impact', 'special_needs', 'unreported', 'no_contact', 'purok_count'];
        if (Schema::hasColumn('rescue_criteria_snapshots', 'rescue_priority_version')) {
            $updates[] = 'rescue_priority_version';
        }
        DB::table('rescue_criteria_snapshots')->upsert([$snapshot], ['event_id', 'observed_at'], $updates);
    }

    private function sameValues(array $left, array $right): bool
    {
        foreach (['impact', 'special_needs', 'unreported', 'no_contact'] as $key) {
            if ($left[$key] === null && $right[$key] === null) continue;
            if ($left[$key] === null || $right[$key] === null || abs((float) $left[$key] - (float) $right[$key]) >= 0.1) {
                return false;
            }
        }

        return true;
    }
}
