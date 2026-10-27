<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardImpactQuery
{
    public function history(string $period): array
    {
        $empty = ['deaths' => 0, 'injured' => 0, 'missing' => 0, 'damaged_houses' => 0];
        $events = DB::table('disaster_events')->whereNotNull('ended_at')
            ->when(Schema::hasColumn('disaster_events', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('ended_at')->get(['event_id', 'ended_at']);
        $eventCounts = [];
        $assessed = 0;
        if ($events->isNotEmpty() && Schema::hasTable('responder_field_reports')) {
            // Reassessments replace earlier household counts; never sum report revisions.
            $latest = DB::table('responder_field_reports')->whereIn('disaster_id', $events->pluck('event_id'))
                ->whereIn('household_id', HouseholdEligibilityQuery::ids())
                ->selectRaw('MAX(report_id) as report_id')->groupBy('disaster_id', 'household_id');
            $reports = DB::table('responder_field_reports as r')->joinSub($latest, 'latest', 'latest.report_id', '=', 'r.report_id')->select('r.*')->get();
            $members = collect();
            if ($reports->isNotEmpty() && Schema::hasTable('responder_field_report_members')) {
                $members = DB::table('responder_field_report_members')->whereIn('report_id', $reports->pluck('report_id'))->get()->groupBy('report_id');
            }
            foreach ($reports as $report) {
                $counts = $eventCounts[$report->disaster_id] ?? $empty;
                $notes = json_decode($report->notes ?? '', true);
                $notes = is_array($notes) ? $notes : [];
                $assessment = $members->get($report->report_id, collect($notes['members'] ?? []));
                $counts['deaths'] += (int) ($report->death_count ?? $notes['death_count'] ?? 0);
                $counts['injured'] += (int) ($report->injured_count ?? $notes['injured_count'] ?? 0);
                $counts['missing'] += $assessment->filter(fn ($member) => data_get($member, 'status_key') === 'missing')->count();
                $damage = strtolower(trim((string) ($report->property_damage ?? $notes['property_damage'] ?? '')));
                if ($damage !== '' && !in_array($damage, ['no_damage', 'none', 'no damage', 'not_yet_assessed', 'not yet assessed', 'unknown'], true)) {
                    $counts['damaged_houses']++;
                }
                $eventCounts[$report->disaster_id] = $counts;
                $assessed++;
            }
        }
        $groups = [];
        $totals = $empty;
        foreach ($events as $event) {
            $date = Carbon::parse($event->ended_at, config('app.timezone'))->setTimezone('Asia/Manila');
            $start = match ($period) {
                'weekly' => $date->copy()->startOfWeek(Carbon::MONDAY),
                'yearly' => $date->copy()->startOfYear(),
                default => $date->copy()->startOfMonth(),
            };
            $key = $start->format('Y-m-d');
            $label = match ($period) {
                'weekly' => $start->format('M d, Y').' – '.$start->copy()->endOfWeek(Carbon::SUNDAY)->format('M d, Y'),
                'yearly' => $start->format('Y'),
                default => $start->format('M Y'),
            };
            $groups[$key] ??= array_merge(['key' => $key, 'label' => $label, 'event_count' => 0], $empty);
            $groups[$key]['event_count']++;
            foreach ($empty as $metric => $_) {
                $value = $eventCounts[$event->event_id][$metric] ?? 0;
                $groups[$key][$metric] += $value;
                $totals[$metric] += $value;
            }
        }
        ksort($groups);
        return ['periods' => array_values($groups), 'totals' => $totals, 'event_count' => $events->count(), 'assessed_households' => $assessed];
    }
}
