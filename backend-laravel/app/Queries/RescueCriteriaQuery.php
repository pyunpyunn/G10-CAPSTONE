<?php

namespace App\Queries;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Shared\BarangayProfileService;
use App\Presenters\RescueCriteriaPresenter;

class RescueCriteriaQuery
{
    public function __construct(private RescuePriorityQuery $priorities, private BarangayProfileService $barangay, private RescueCriteriaPresenter $presenter) {}

    public function puroks(string $eventId): array
    {
        [$scored, $settings] = $this->priorities->scored($eventId, $this->barangay->current()['barangay_id'] ?? null);
        $rows = DB::query()->fromSub($scored, 'scored')
            ->whereNotNull('area_name')->where('area_name', '<>', '')
            ->groupBy('area_name')
            ->selectRaw('area_name, COUNT(*) as households, SUM(urgent_tier) as urgent_households')
            ->selectRaw('SUM(CASE WHEN purok_id IS NULL THEN 1 ELSE 0 END) as unlinked_households')
            ->selectRaw('MAX(impacted_households) as impacted_households, MAX(vulnerable_members) as unresolved_vulnerable_members, MAX(total_vulnerable_members) as total_vulnerable_members')
            ->selectRaw('AVG(impacted_households / CASE WHEN area_households < 1 THEN 1 ELSE area_households END) as impact_share')
            ->selectRaw('AVG(vulnerable_members / CASE WHEN total_vulnerable_members < 1 THEN 1 ELSE total_vulnerable_members END) as vulnerability_share')
            ->selectRaw('SUM(unreported_members) as unreported_members, SUM(household_members) as total_members')
            ->selectRaw('SUM(no_contact_channel) as no_contact_households')
            ->selectRaw('AVG(no_contact_channel) as no_contact_share')
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
                'priority_score' => round(array_sum($contributions), 1),
                'factors' => $factors,
                'contributions' => array_map(fn (float $value): float => round($value, 1), $contributions),
            ];
        })->sort(function (array $left, array $right): int {
            return ($right['priority_score'] <=> $left['priority_score'])
                ?: ($right['factors']['impact'] <=> $left['factors']['impact'])
                ?: strcmp($left['purok'], $right['purok']);
        })->values()->map(function (array $row, int $index): array {
            $row['rank'] = $index + 1;
            $row['band'] = $this->presenter->priorityBand($row['priority_score'], $row['rank']);
            return $row;
        })->all();
    }

    public function current(string $eventId, Carbon $startedAt): array
    {
        $puroks = $this->puroks($eventId);
        $percent = function (string $numerator, string $denominator) use ($puroks): float {
            $total = array_sum(array_column($puroks, $denominator));
            return $total > 0 ? round(100 * array_sum(array_column($puroks, $numerator)) / $total, 1) : 0;
        };

        return [
            'observed_at' => now()->toIso8601String(),
            'hours_since_alert' => max(0, round($startedAt->diffInMinutes(now()) / 60, 1)),
            'impact' => $percent('impacted_households', 'households'),
            'special_needs' => $percent('unresolved_vulnerable_members', 'total_vulnerable_members'),
            'unreported' => $percent('unreported_members', 'total_members'),
            'no_contact' => $percent('no_contact_households', 'households'),
            'purok_count' => count($puroks),
        ];
    }

    public function timeline(string $eventId, Carbon $startedAt): array
    {
        $history = DB::table('rescue_criteria_snapshots')->where('event_id', $eventId)->where('definition_version', 4)
            ->orderByDesc('observed_at')->limit(250)->get()->reverse()->values()
            ->map(fn (object $row): array => [
                'observed_at' => Carbon::parse($row->observed_at)->toIso8601String(),
                'hours_since_alert' => max(0, round($startedAt->diffInMinutes(Carbon::parse($row->observed_at)) / 60, 1)),
                'impact' => (float) $row->impact,
                'special_needs' => (float) $row->special_needs,
                'unreported' => (float) $row->unreported,
                'no_contact' => (float) $row->no_contact,
                'purok_count' => (int) $row->purok_count,
            ])->all();

        $current = $this->current($eventId, $startedAt);
        if ($history === [] || Carbon::parse(end($history)['observed_at'])->diffInMinutes(now()) >= 5) {
            $history[] = $current;
        }

        return $history;
    }
}
