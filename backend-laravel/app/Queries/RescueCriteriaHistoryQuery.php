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

    public function __construct(private BarangayProfileService $barangay) {}

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
            ])->all();
    }

    /** Recompute buckets so corrected/backdated reports update their actual historical points. */
    public function refresh(string $eventId, Carbon $startedAt, ?Carbon $endedAt = null, bool $rebaseContact = false): int
    {
        $until = $endedAt && $endedAt->lt(now()) ? $endedAt : now();
        $barangayId = $this->barangay->current()['barangay_id'] ?? null;
        $households = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->when($barangayId !== null, fn ($q) => $q->where('a.barangay_id', $barangayId))
            ->where('h.created_at', '<=', $startedAt)
            ->where(fn ($q) => $q->whereNull('h.deleted_at')->orWhere('h.deleted_at', '>', $startedAt))
            ->get(['h.household_id', 'h.contact_number']);
        $ids = $households->pluck('household_id')->all();
        $trusted = $ids === [] ? [] : DB::table('trusted_households as t')
            ->join('households as h', 'h.household_id', '=', 't.trusted_household_id')
            ->whereIn('t.requesting_household_id', $ids)->whereIn('t.validation_status', ['validated', 'approved'])
            ->whereNotNull('h.contact_number')->whereRaw("TRIM(h.contact_number) <> ''")
            ->pluck('t.requesting_household_id')->flip()->all();
        $registeredDevices = $ids === [] ? [] : DB::table('device_tokens')
            ->whereIn('household_id', $ids)->where('is_active', 1)
            ->distinct()->pluck('household_id')->flip()->all();
        $baselines = $households->map(fn ($household): array => [
            'event_id' => $eventId,
            'household_id' => $household->household_id,
            'no_contact' => trim((string) $household->contact_number) === ''
                && ! isset($registeredDevices[$household->household_id])
                && ! isset($trusted[$household->household_id]),
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
        $members = $ids === [] ? collect() : DB::table('household_members as m')
            ->whereIn('m.household_id', $ids)->where('m.created_at', '<=', $startedAt)
            ->where(fn ($q) => $q->whereNull('m.deleted_at')->orWhere('m.deleted_at', '>', $startedAt))
            ->select('m.member_id')
            ->selectRaw('CASE WHEN m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id) THEN 1 ELSE 0 END as vulnerable')->get();
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
            $snapshots[] = array_merge(['event_id' => $eventId, 'bucket_hour' => $hour, 'observed_at' => $at,
                'created_at' => now(), 'updated_at' => now()], $values);
        }
        foreach (array_chunk($snapshots, 500) as $chunk) {
            DB::table('rescue_criteria_line_snapshots')->upsert($chunk, ['event_id', 'bucket_hour'],
                array_merge(array_keys($this->counts([], [])), ['observed_at', 'updated_at']));
        }
        return count($snapshots);
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
