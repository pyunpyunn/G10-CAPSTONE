<?php

namespace App\Queries;

use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RescueCriteriaHistoryQuery
{
    private const SAFE = ['safe', 'safe_at_home', 'evacuated'];
    private const UNCHECKED = ['unknown', 'unchecked', 'unreported'];
    private const IMPACT = ['unsafe', 'needs_help', 'needs_assistance'];

    public function __construct(private BarangayProfileService $barangay, private RescuePriorityQuery $priorities) {}

    /** Dashboard reads a single indexed snapshot query; no history reconstruction in HTTP. */
    public function forEvent(string $eventId, Carbon $startedAt): array
    {
        return DB::table('rescue_criteria_line_snapshots')->where('event_id', $eventId)
            ->orderBy('bucket_hour')->get()->map(fn (object $row): array => [
                'observed_at' => Carbon::parse($row->observed_at)->toIso8601String(),
                'hours_since_alert' => (int) $row->bucket_hour,
                'impact' => $this->percent($row->impact_open, $row->household_total),
                'special_needs' => $this->percent($row->special_open, $row->vulnerable_total),
                'unreported' => $this->percent($row->unreported_open, $row->member_total),
                'no_contact' => $this->percent($row->no_contact_open, $row->no_contact_total),
                'rescue_priority_version' => $row->rescue_priority_version ?? null,
            ])->all();
    }

    /** Recompute buckets so corrected/backdated reports update their actual historical points. */
    public function refresh(string $eventId, Carbon $startedAt, ?Carbon $endedAt = null, bool $rebaseContact = false): int
    {
        $until = $endedAt && $endedAt->lt(now()) ? $endedAt : now();
        $weightVersion = Schema::hasColumn('disaster_events', 'rescue_priority_version')
            ? DB::table('disaster_events')->where('event_id', $eventId)->value('rescue_priority_version')
            : null;
        $barangayId = $this->barangay->current()['barangay_id'] ?? null;
        $households = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->when($barangayId !== null, fn ($q) => $q->where('a.barangay_id', $barangayId))
            ->where(fn ($q) => $q->whereNull('h.created_at')->orWhere('h.created_at', '<=', $startedAt))
            ->where(fn ($q) => $q->whereNull('h.deleted_at')->orWhere('h.deleted_at', '>', $startedAt))
            ->select('h.household_id', 'a.purok_sitio')
            ->selectRaw($this->priorities->contactMissingExpression().' as contact_missing')->get();
        $ids = $households->pluck('household_id')->all();
        // Rebase once when upgrading from the previous phone/device definition.
        $rebaseContact = $rebaseContact || ! DB::table('rescue_criteria_snapshots')
            ->where('event_id', $eventId)->where('definition_version', 6)->exists();
        $baselines = $households->map(fn ($household): array => [
            'event_id' => $eventId,
            'household_id' => $household->household_id,
            'no_contact' => (bool) $household->contact_missing,
            'captured_at' => now(),
        ])->all();
        foreach (array_chunk($baselines, 500) as $chunk) {
            if ($rebaseContact) {
                DB::table('rescue_criteria_contact_baselines')->upsert($chunk, ['event_id', 'household_id'], ['no_contact']);
            } else {
                DB::table('rescue_criteria_contact_baselines')->insertOrIgnore($chunk);
            }
        }
        $baselineContact = DB::table('rescue_criteria_contact_baselines')->where('event_id', $eventId)
            ->whereIn('household_id', $ids)->pluck('no_contact', 'household_id');
        $householdStates = [];
        foreach ($households as $household) {
            $householdStates[$household->household_id] = [
                'status' => null, 'reported' => false,
                'no_contact' => (bool) $baselineContact->get($household->household_id, false),
            ];
        }
        $minorPredicate = Schema::hasColumn('household_members', 'birth_date')
            ? ' OR (m.birth_date IS NOT NULL AND m.birth_date > ?)'
            : '';
        $minorBindings = $minorPredicate === '' ? [] : [$startedAt->copy()->subYears(18)->toDateString()];
        $members = $ids === [] ? collect() : DB::table('household_members as m')
            ->whereIn('m.household_id', $ids)
            // Imported registrations can have no timestamp; they still belong to the event population.
            ->where(fn ($q) => $q->whereNull('m.created_at')->orWhere('m.created_at', '<=', $startedAt))
            ->where(fn ($q) => $q->whereNull('m.deleted_at')->orWhere('m.deleted_at', '>', $startedAt))
            ->select('m.member_id')
            ->selectRaw("CASE WHEN m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id){$minorPredicate} THEN 1 ELSE 0 END as vulnerable", $minorBindings)->get();
        $memberStates = $members->mapWithKeys(fn ($m) => [$m->member_id => ['status' => null, 'vulnerable' => (bool) $m->vulnerable]])->all();
        $reports = [];
        if ($ids !== []) {
            DB::table('household_status_logs as l')->join('household_statuses as s', 's.status_id', '=', 'l.status_id')
                ->where('l.disaster_id', $eventId)->whereIn('l.household_id', $ids)
                ->whereBetween('l.submitted_at', [$startedAt, $until])->orderBy('l.submitted_at')->orderBy('l.status_log_id')
                ->select('l.household_id as id', 's.status_key as status', 'l.submitted_at as at')
                ->chunk(500, function ($rows) use (&$reports): void {
                    foreach ($rows as $row) $reports[] = ['kind' => 'household', 'id' => $row->id, 'status' => $row->status, 'at' => $row->at];
                });
        }
        if ($memberStates !== []) {
            DB::table('member_status_logs as l')->join('member_statuses as s', 's.status_id', '=', 'l.to_status_id')
                ->where('l.disaster_id', $eventId)->whereIn('l.member_id', array_keys($memberStates))
                ->whereBetween('l.reported_at', [$startedAt, $until])->orderBy('l.reported_at')->orderBy('l.member_status_log_id')
                ->select('l.member_id as id', 's.status_key as status', 'l.reported_at as at')
                ->chunk(500, function ($rows) use (&$reports): void {
                    foreach ($rows as $row) $reports[] = ['kind' => 'member', 'id' => $row->id, 'status' => $row->status, 'at' => $row->at];
                });
        }
        // Older local schemas lack dispatch_type; only explicitly tagged welfare checks count.
        if ($ids !== [] && Schema::hasColumn('responder_assignments', 'dispatch_type')) {
            DB::table('responder_assignments')->where('disaster_id', $eventId)
                ->whereIn('household_id', $ids)->where('dispatch_type', 'welfare_check')
                ->where('status', 'completed')->whereBetween('completed_at', [$startedAt, $until])
                ->orderBy('completed_at')->orderBy('assignment_id')
                ->select('household_id as id', 'completed_at as at')
                ->chunk(500, function ($rows) use (&$reports): void {
                    foreach ($rows as $row) $reports[] = ['kind' => 'welfare', 'id' => $row->id, 'at' => $row->at];
                });
        }
        usort($reports, fn ($a, $b) => strcmp($a['at'], $b['at']));
        $next = 0;
        $snapshots = [];
        $hours = max(0, (int) $startedAt->diffInHours($until));
        for ($hour = 0; $hour <= $hours; $hour += 3) {
            $at = $startedAt->copy()->addHours($hour);
            while (isset($reports[$next]) && Carbon::parse($reports[$next]['at'])->lte($at)) {
                $report = $reports[$next++];
                if ($report['kind'] === 'welfare') {
                    $householdStates[$report['id']]['reported'] = true;
                } elseif ($report['kind'] === 'household') {
                    $householdStates[$report['id']]['status'] = $report['status'];
                    $householdStates[$report['id']]['reported'] = true;
                } else {
                    $memberStates[$report['id']]['status'] = $report['status'];
                }
            }
            $values = $this->counts($householdStates, $memberStates);
            $snapshot = array_merge(['event_id' => $eventId, 'bucket_hour' => $hour, 'observed_at' => $at,
                'created_at' => now(), 'updated_at' => now()], $values);
            if (Schema::hasColumn('rescue_criteria_line_snapshots', 'rescue_priority_version')) {
                $snapshot['rescue_priority_version'] = $weightVersion;
            }
            $snapshots[] = $snapshot;
        }
        if (Schema::hasTable('rescue_criteria_line_snapshots')) {
            foreach (array_chunk($snapshots, 500) as $chunk) {
                $updates = array_merge(array_keys($this->counts([], [])), ['observed_at', 'updated_at']);
                if (Schema::hasColumn('rescue_criteria_line_snapshots', 'rescue_priority_version')) {
                    $updates[] = 'rescue_priority_version';
                }
                DB::table('rescue_criteria_line_snapshots')->upsert($chunk, ['event_id', 'bucket_hour'], $updates);
            }
        }

        if (Schema::hasTable('rescue_criteria_snapshots')) {
            $purokCount = $households->pluck('purok_sitio')->filter()->unique()->count();
            $criteriaSnapshots = array_map(function (array $snapshot) use ($purokCount, $weightVersion): array {
                $values = $snapshot;
                $criteriaSnapshot = [
                    'event_id' => $values['event_id'],
                    'observed_at' => $values['observed_at'],
                    'definition_version' => 6,
                    'impact' => $this->percent($values['impact_open'], $values['household_total']),
                    'special_needs' => $this->percent($values['special_open'], $values['vulnerable_total']),
                    'unreported' => $this->percent($values['unreported_open'], $values['member_total']),
                    'no_contact' => $this->percent($values['no_contact_open'], $values['no_contact_total']),
                    'purok_count' => $purokCount,
                ];
                if (Schema::hasColumn('rescue_criteria_snapshots', 'rescue_priority_version')) {
                    $criteriaSnapshot['rescue_priority_version'] = $weightVersion;
                }
                return $criteriaSnapshot;
            }, $snapshots);

            foreach (array_chunk($criteriaSnapshots, 500) as $chunk) {
                $updates = ['definition_version', 'impact', 'special_needs', 'unreported', 'no_contact', 'purok_count'];
                if (Schema::hasColumn('rescue_criteria_snapshots', 'rescue_priority_version')) {
                    $updates[] = 'rescue_priority_version';
                }
                DB::table('rescue_criteria_snapshots')->upsert($chunk, ['event_id', 'observed_at'], $updates);
            }
        }
        return count($snapshots);
    }


    /** One fixed-weight impact series per sitio, pooling the counts of its puroks. */
    public function sitioSeries(string $eventId, Carbon $startedAt): array
    {
        $until = now()->startOfSecond();
        $barangayId = $this->barangay->current()['barangay_id'] ?? null;
        if ($barangayId === null) return [];
        $sitios = DB::table('sitios')->where('barangay_id', $barangayId)->orderBy('sitio_name')
            ->get(['sitio_id', 'sitio_name'])->keyBy('sitio_id');
        $puroks = DB::table('puroks')->whereIn('sitio_id', $sitios->keys())->get()->keyBy('purok_id');
        $labels = [];
        foreach ($sitios as $sitio) $labels[mb_strtolower(trim($sitio->sitio_name))] = [$sitio->sitio_id, null];
        foreach ($puroks as $purok) {
            $label = $purok->purok_name.', '.$sitios[$purok->sitio_id]->sitio_name;
            $labels[mb_strtolower(trim($label))] = [$purok->sitio_id, $purok->purok_id];
        }
        $households = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->where('a.barangay_id', $barangayId)
            ->where(fn ($q) => $q->whereNull('h.created_at')->orWhere('h.created_at', '<=', $until))
            ->where(fn ($q) => $q->whereNull('h.deleted_at')->orWhere('h.deleted_at', '>', $startedAt))
            ->select('h.household_id', 'h.created_at', 'h.deleted_at', 'a.sitio_id', 'a.purok_id', 'a.purok_sitio')
            ->selectRaw($this->priorities->contactMissingExpression().' as contact_missing')->get();
        $householdStates = [];
        foreach ($households as $h) {
            $purok = $puroks->get($h->purok_id);
            [$sitioId, $purokId] = $purok ? [$purok->sitio_id, $purok->purok_id]
                : ($labels[mb_strtolower(trim((string) $h->purok_sitio))] ?? [$h->sitio_id, null]);
            $sitioId = $h->sitio_id ?? $sitioId;
            if (! $sitios->has($sitioId)) continue;
            $householdStates[$h->household_id] = ['sitio_id' => $sitioId, 'purok_id' => $purokId,
                'created_at' => $h->created_at, 'deleted_at' => $h->deleted_at,
                'status' => null, 'reported' => false, 'no_contact' => (bool) $h->contact_missing];
        }
        $ids = array_keys($householdStates);
        $minor = Schema::hasColumn('household_members', 'birth_date') ? ' OR (m.birth_date IS NOT NULL AND m.birth_date > ?)' : '';
        $members = $ids === [] ? collect() : DB::table('household_members as m')->whereIn('m.household_id', $ids)
            ->where(fn ($q) => $q->whereNull('m.created_at')->orWhere('m.created_at', '<=', $until))
            ->where(fn ($q) => $q->whereNull('m.deleted_at')->orWhere('m.deleted_at', '>', $startedAt))
            ->select('m.member_id', 'm.household_id', 'm.created_at', 'm.deleted_at')
            ->selectRaw("CASE WHEN m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS
                (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id){$minor} THEN 1 ELSE 0 END as vulnerable",
                $minor === '' ? [] : [$startedAt->copy()->subYears(18)->toDateString()])->get();
        $memberStates = [];
        foreach ($members as $m) $memberStates[$m->member_id] = ['household_id' => $m->household_id,
            'created_at' => $m->created_at, 'deleted_at' => $m->deleted_at, 'status' => null, 'vulnerable' => (bool) $m->vulnerable];
        $reports = [];
        if ($ids !== []) {
            $rows = DB::table('household_status_logs as l')->join('household_statuses as s', 's.status_id', '=', 'l.status_id')
                ->where('l.disaster_id', $eventId)->whereIn('l.household_id', $ids)->whereBetween('l.submitted_at', [$startedAt, $until])
                ->orderBy('l.submitted_at')->orderBy('l.status_log_id')->get(['l.household_id as id', 's.status_key as status', 'l.submitted_at as at']);
            foreach ($rows as $r) $reports[] = ['kind' => 'household', 'id' => $r->id, 'status' => $r->status, 'at' => $r->at];
            if (Schema::hasColumn('responder_assignments', 'dispatch_type')) {
                $rows = DB::table('responder_assignments')->where('disaster_id', $eventId)->whereIn('household_id', $ids)
                    ->where('dispatch_type', 'welfare_check')->where('status', 'completed')->whereBetween('completed_at', [$startedAt, $until])
                    ->orderBy('completed_at')->orderBy('assignment_id')->get(['household_id as id', 'completed_at as at']);
                foreach ($rows as $r) $reports[] = ['kind' => 'welfare', 'id' => $r->id, 'at' => $r->at];
            }
        }
        if ($memberStates !== []) {
            $rows = DB::table('member_status_logs as l')->join('member_statuses as s', 's.status_id', '=', 'l.to_status_id')
                ->where('l.disaster_id', $eventId)->whereIn('l.member_id', array_keys($memberStates))->whereBetween('l.reported_at', [$startedAt, $until])
                ->orderBy('l.reported_at')->orderBy('l.member_status_log_id')->get(['l.member_id as id', 's.status_key as status', 'l.reported_at as at']);
            foreach ($rows as $r) $reports[] = ['kind' => 'member', 'id' => $r->id, 'status' => $r->status, 'at' => $r->at];
        }
        usort($reports, fn ($a, $b) => strcmp($a['at'], $b['at']));
        $series = [];
        foreach ($sitios as $sitio) $series[$sitio->sitio_id] = ['sitio_id' => $sitio->sitio_id, 'sitio' => $sitio->sitio_name,
            'is_demo' => false, 'points' => []];
        $times = [];
        $hours = max(0, (int) $startedAt->diffInHours($until, false));
        for ($hour = 0; $hour <= $hours; $hour += 3) $times[] = $startedAt->copy()->addHours($hour);
        if (! end($times)->equalTo($until)) $times[] = $until;
        $next = 0;
        foreach ($times as $at) {
            while (isset($reports[$next]) && Carbon::parse($reports[$next]['at'])->lte($at)) {
                $r = $reports[$next++];
                if ($r['kind'] === 'member') $memberStates[$r['id']]['status'] = $r['status'];
                else {
                    $householdStates[$r['id']]['reported'] = true;
                    if ($r['kind'] === 'household') $householdStates[$r['id']]['status'] = $r['status'];
                }
            }
            // The live endpoint uses authoritative current status, including imported reports without logs.
            if ($at->equalTo($until)) {
                if ($ids !== []) {
                    $live = DB::table('household_disasters as hd')->leftJoin('household_statuses as s', 's.status_id', '=', 'hd.current_status_id')
                        ->where('hd.disaster_id', $eventId)->whereIn('hd.household_id', $ids)->get(['hd.household_id', 'hd.last_reported_at', 's.status_key']);
                    foreach ($live as $r) {
                        $householdStates[$r->household_id]['status'] = $r->status_key;
                        $householdStates[$r->household_id]['reported'] = $householdStates[$r->household_id]['reported'] || $r->last_reported_at !== null
                            || in_array($r->status_key, ['safe', 'safe_at_home', 'evacuated', 'returned', 'relocated'], true);
                    }
                }
                if ($memberStates !== []) {
                    $live = DB::table('member_disaster_statuses as r')->leftJoin('member_statuses as s', 's.status_id', '=', 'r.status_id')
                        ->where('r.disaster_id', $eventId)->whereIn('r.member_id', array_keys($memberStates))->get(['r.member_id', 's.status_key']);
                    foreach ($live as $r) $memberStates[$r->member_id]['status'] = $r->status_key;
                }
            }
            $groupedHouseholds = $groupedMembers = [];
            foreach ($householdStates as $id => $h) {
                if (! $this->registeredAt($h, $at)) continue;
                $groupedHouseholds[$h['sitio_id']][$id] = $h;
            }
            foreach ($memberStates as $id => $m) {
                $h = $householdStates[$m['household_id']];
                if (! isset($groupedHouseholds[$h['sitio_id']][$m['household_id']]) || ! $this->registeredAt($m, $at)) continue;
                $groupedMembers[$h['sitio_id']][$id] = $m;
            }
            foreach ($sitios as $sitio) {
                $hh = $groupedHouseholds[$sitio->sitio_id] ?? [];
                $mm = $groupedMembers[$sitio->sitio_id] ?? [];
                $point = $this->weightedImpact($this->counts($hh, $mm));
                $point['observed_at'] = $at->toIso8601String();
                $point['hours_since_alert'] = max(0, round($startedAt->diffInSeconds($at, false) / 3600, 6));
                $point['puroks'] = [];
                foreach ($puroks->where('sitio_id', $sitio->sitio_id) as $purok) {
                    $ph = array_filter($hh, fn ($h) => $h['purok_id'] == $purok->purok_id);
                    $pm = array_filter($mm, fn ($m) => isset($ph[$m['household_id']]));
                    $point['puroks'][] = ['purok_id' => $purok->purok_id, 'purok' => $purok->purok_name]
                        + $this->weightedImpact($this->counts($ph, $pm));
                }
                $unlinked = array_filter($hh, fn ($h) => $h['purok_id'] === null);
                if ($unlinked !== []) {
                    $unlinkedMembers = array_filter($mm, fn ($m) => isset($unlinked[$m['household_id']]));
                    $point['puroks'][] = ['purok_id' => null, 'purok' => 'Unassigned purok']
                        + $this->weightedImpact($this->counts($unlinked, $unlinkedMembers));
                }
                $series[$sitio->sitio_id]['points'][] = $point;
            }
        }
        // Only missing catalog slots are synthetic; never overwrite a registered sitio's measurements.
        $missing = max(0, 18 - count($series));
        for ($slot = 1; $slot <= $missing; $slot++) {
            $points = [];
            foreach ($times as $at) {
                $hour = max(0, $startedAt->diffInSeconds($at, false) / 3600);
                $progress = 1 - exp(-$hour / (10 + $slot * 4));
                $factors = ['unsafe_reports' => round(max(0, min(100, 12 + $slot * 7 + 20 * sin($hour / (8 + $slot)))), 1),
                    'special_needs' => round(100 - (50 + $slot * 4) * $progress, 1),
                    'no_contact' => round(100 - (65 + $slot * 3) * $progress, 1),
                    'unreported_members' => round(100 - (70 + $slot * 3) * $progress, 1)];
                $contributions = [];
                foreach (RescueCriteriaQuery::SITIO_WEIGHTS as $key => $weight) $contributions[$key] = round($factors[$key] * $weight / 100, 2);
                $points[] = ['observed_at' => $at->toIso8601String(), 'hours_since_alert' => round($hour, 6),
                    'purok_impact' => round(array_sum($contributions), 1), 'criteria' => $factors, 'contributions' => $contributions,
                    'is_empty' => false, 'puroks' => []];
            }
            $series['demo-'.$slot] = ['sitio_id' => 'demo-'.$slot, 'sitio' => 'Demo Sitio '.(count($sitios) + $slot), 'is_demo' => true, 'points' => $points];
        }
        return array_values($series);
    }

    private function registeredAt(array $record, Carbon $at): bool
    {
        return ($record['created_at'] === null || Carbon::parse($record['created_at'])->lte($at))
            && ($record['deleted_at'] === null || Carbon::parse($record['deleted_at'])->gt($at));
    }

    private function weightedImpact(array $counts): array
    {
        $criteria = ['unsafe_reports' => $this->percent($counts['impact_open'], $counts['household_total']),
            'special_needs' => $this->percent($counts['special_open'], $counts['vulnerable_total']),
            'no_contact' => $this->percent($counts['no_contact_open'], $counts['no_contact_total']),
            'unreported_members' => $this->percent($counts['unreported_open'], $counts['member_total'])];
        $contributions = [];
        foreach (RescueCriteriaQuery::SITIO_WEIGHTS as $key => $weight) $contributions[$key] = round(($criteria[$key] ?? 0) * $weight / 100, 2);
        return ['purok_impact' => round(array_sum($contributions), 1), 'criteria' => $criteria,
            'contributions' => $contributions, 'counts' => $counts, 'is_empty' => $counts['household_total'] === 0];
    }

    private function counts(array $households, array $members): array
    {
        $impact = $noContact = $noneTotal = $special = $vulnerable = $unreported = 0;
        foreach ($households as $household) {
            $impact += (int) in_array($household['status'], self::IMPACT, true);
            if ($household['no_contact']) {
                $noneTotal++;
                $noContact += (int) ! $household['reported'];
            }
        }
        foreach ($members as $member) {
            $unreported += (int) ($member['status'] === null || in_array($member['status'], self::UNCHECKED, true));
            if ($member['vulnerable']) {
                $vulnerable++;
                $special += (int) ! in_array($member['status'], self::SAFE, true);
            }
        }
        return ['impact_open' => $impact, 'household_total' => count($households),
            'special_open' => $special, 'vulnerable_total' => $vulnerable,
            'unreported_open' => $unreported, 'member_total' => count($members),
            'no_contact_open' => $noContact, 'no_contact_total' => $noneTotal];
    }

    private function percent(int $open, int $total): ?float
    {
        return $total === 0 ? null : round(100 * $open / $total, 1);
    }
}
