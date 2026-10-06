<?php

namespace App\Queries;

use App\Models\RescuePrioritySetting;
use App\Models\Sitio;
use App\Presenters\RescueCriteriaPresenter;
use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Facades\DB;

class SitioPriorityQuery
{
    public function __construct(private BarangayProfileService $barangay, private RescueCriteriaPresenter $presenter) {}

    public function ranked(string $eventId): array
    {
        $barangayId = $this->barangay->current()['barangay_id'] ?? null;
        if ($barangayId === null) return [];
        $settings = RescuePrioritySetting::query()->orderByDesc('version')->firstOrFail();
        $sitios = Sitio::query()->where('barangay_id', $barangayId)->orderBy('sitio_name')->get(['sitio_id', 'sitio_name']);
        $compositeName = DB::connection()->getDriverName() === 'sqlite'
            ? "(p.purok_name || ', ' || s.sitio_name)"
            : "CONCAT(p.purok_name, ', ', s.sitio_name)";
        $puroks = DB::table('puroks as p')
            ->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->leftJoin('addresses as a', function ($join) use ($compositeName): void {
                $join->on('a.purok_id', '=', 'p.purok_id')
                    ->orWhereRaw("(a.purok_id IS NULL AND a.barangay_id = s.barangay_id AND
                        ((a.sitio_id = p.sitio_id AND a.purok_sitio = p.purok_name)
                        OR (a.sitio_id IS NULL AND a.purok_sitio = {$compositeName})))");
            })
            ->leftJoin('households as h', fn ($join) => $join->on('h.address_id', '=', 'a.address_id')->whereNull('h.deleted_at'))
            ->whereIn('p.sitio_id', $sitios->pluck('sitio_id'))
            ->groupBy('p.purok_id', 'p.sitio_id', 'p.purok_name')
            ->orderBy('p.purok_name')
            ->get(['p.purok_id', 'p.sitio_id', 'p.purok_name', DB::raw('COUNT(DISTINCT h.household_id) as household_count')])
            ->groupBy('sitio_id');
        $area = 'COALESCE(a.sitio_id, p.sitio_id)';
        $households = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('household_disasters as hd', fn ($join) => $join->on('hd.household_id', '=', 'h.household_id')->where('hd.disaster_id', $eventId))
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('a.barangay_id', $barangayId)->whereNull('h.deleted_at')
            ->whereNotNull(DB::raw($area))->groupBy(DB::raw($area))
            ->selectRaw($area.' as sitio_id, COUNT(DISTINCT h.household_id) as households')
            ->selectRaw("COUNT(DISTINCT CASE WHEN hs.status_key IN ('unsafe','needs_help','needs_assistance') THEN h.household_id END) as impacted_households")
            ->selectRaw("COUNT(DISTINCT CASE WHEN hd.needs_dispatch = 1 THEN h.household_id END) as urgent_households")
            ->selectRaw("COUNT(DISTINCT CASE WHEN (h.contact_number IS NULL OR TRIM(h.contact_number) = '')
                AND NOT EXISTS (SELECT 1 FROM trusted_households t JOIN households trusted
                    ON ((t.requesting_household_id = h.household_id AND trusted.household_id = t.trusted_household_id)
                    OR (t.trusted_household_id = h.household_id AND trusted.household_id = t.requesting_household_id))
                    WHERE t.validation_status IN ('validated','approved') AND trusted.contact_number IS NOT NULL
                    AND TRIM(trusted.contact_number) <> '')
                AND hd.last_reported_at IS NULL THEN h.household_id END) as no_contact_households")
            ->get()->keyBy('sitio_id');
        $members = DB::table('household_members as m')
            ->join('households as h', 'h.household_id', '=', 'm.household_id')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('member_disaster_statuses as report', fn ($join) => $join->on('report.member_id', '=', 'm.member_id')->where('report.disaster_id', $eventId))
            ->leftJoin('member_statuses as ms', 'ms.status_id', '=', 'report.status_id')
            ->where('a.barangay_id', $barangayId)->whereNull('h.deleted_at')->whereNull('m.deleted_at')
            ->whereNotNull(DB::raw($area))->groupBy(DB::raw($area))
            ->selectRaw($area.' as sitio_id, COUNT(DISTINCT m.member_id) as total_members')
            ->selectRaw('COUNT(DISTINCT CASE WHEN report.member_id IS NULL THEN m.member_id END) as unreported_members')
            ->selectRaw('COUNT(DISTINCT CASE WHEN m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS
                (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id) THEN m.member_id END) as total_vulnerable_members')
            ->selectRaw("COUNT(DISTINCT CASE WHEN (m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS
                (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id))
                AND (ms.status_key IS NULL OR ms.status_key NOT IN ('safe','safe_at_home','evacuated'))
                THEN m.member_id END) as unresolved_vulnerable_members")
            ->get()->keyBy('sitio_id');

        return $sitios->map(function (Sitio $sitio) use ($households, $members, $settings, $puroks): array {
            $h = $households->get($sitio->sitio_id);
            $m = $members->get($sitio->sitio_id);
            $householdTotal = (int) ($h->households ?? 0);
            $memberTotal = (int) ($m->total_members ?? 0);
            $vulnerableTotal = (int) ($m->total_vulnerable_members ?? 0);
            $shares = [
                'impact' => $householdTotal ? (int) $h->impacted_households / $householdTotal : 0,
                'special_needs' => $vulnerableTotal ? (int) $m->unresolved_vulnerable_members / $vulnerableTotal : 0,
                'unreported' => $memberTotal ? (int) $m->unreported_members / $memberTotal : 0,
                'no_contact' => $householdTotal ? (int) $h->no_contact_households / $householdTotal : 0,
            ];
            $weights = ['impact' => $settings->impact_weight, 'special_needs' => $settings->vulnerability_weight,
                'unreported' => $settings->unreported_weight, 'no_contact' => $settings->no_contact_weight];
            $contributions = [];
            foreach ($shares as $key => $share) $contributions[$key] = round($share * $weights[$key], 2);
            return [
                'sitio_id' => $sitio->sitio_id, 'sitio' => $sitio->sitio_name,
                'puroks' => ($puroks->get($sitio->sitio_id) ?? collect())->map(fn ($purok): array => [
                    'purok_id' => (int) $purok->purok_id,
                    'purok_name' => $purok->purok_name,
                    'household_count' => (int) $purok->household_count,
                ])->values()->all(),
                'households' => $householdTotal, 'impacted_households' => (int) ($h->impacted_households ?? 0),
                'urgent_households' => (int) ($h->urgent_households ?? 0),
                'unreported_members' => (int) ($m->unreported_members ?? 0),
                'no_contact_households' => (int) ($h->no_contact_households ?? 0),
                'priority_score' => round(array_sum($contributions), 1), 'contributions' => $contributions,
            ];
        })->sort(fn ($a, $b) => ($b['priority_score'] <=> $a['priority_score']) ?: strcmp($a['sitio'], $b['sitio']))
            ->values()->map(function ($row, $index): array {
                $row['rank'] = $index + 1;
                $row['band'] = $this->presenter->priorityBand($row['priority_score'], $row['rank']);
                return $row;
            })->all();
    }
}
