<?php

namespace App\Queries;

use App\Models\Sitio;
use App\Presenters\RescueCriteriaPresenter;
use App\Services\Shared\BarangayProfileService;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SitioPriorityQuery
{
    public function __construct(private BarangayProfileService $barangay, private RescueCriteriaPresenter $presenter, private RescuePriorityQuery $priorities) {}

    public function ranked(string $eventId): array
    {
        $barangayId = $this->barangay->current()['barangay_id'] ?? null;
        if ($barangayId === null) return [];
        $settings = $this->priorities->settingsForEvent($eventId);
        $minorPredicate = '';
        if (Schema::hasColumn('household_members', 'birth_date') && Schema::hasColumn('disaster_events', 'started_at')) {
            $eventStartedAt = DB::table('disaster_events')->where('event_id', $eventId)->value('started_at');
            if ($eventStartedAt) {
                $minorCutoff = Carbon::parse($eventStartedAt)->subYears(18)->toDateString();
                $minorPredicate = " OR (m.birth_date IS NOT NULL AND m.birth_date > '{$minorCutoff}')";
            }
        }
        $sitios = Sitio::query()->where('barangay_id', $barangayId)->orderBy('sitio_name')->get(['sitio_id', 'sitio_name'])->keyBy('sitio_id');
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
        $households = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('household_disasters as hd', fn ($join) => $join->on('hd.household_id', '=', 'h.household_id')->where('hd.disaster_id', $eventId))
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('a.barangay_id', $barangayId)->whereNull('h.deleted_at')
            ->groupBy('a.sitio_id', 'p.sitio_id', 'a.purok_id', 'a.purok_sitio')
            ->selectRaw('a.sitio_id as address_sitio_id, p.sitio_id as purok_sitio_id, a.purok_id as address_purok_id, a.purok_sitio as address_label')
            ->selectRaw('COUNT(DISTINCT h.household_id) as households')
            ->selectRaw("COUNT(DISTINCT CASE WHEN hs.status_key IN ('unsafe','needs_help','needs_assistance') THEN h.household_id END) as impacted_households")
            ->selectRaw("COUNT(DISTINCT CASE WHEN hd.needs_dispatch = 1 THEN h.household_id END) as urgent_households")
            ->selectRaw("COUNT(DISTINCT CASE WHEN NOT EXISTS (
                SELECT 1 FROM device_tokens device
                WHERE device.household_id = h.household_id AND device.is_active = 1
            ) AND NOT EXISTS (
                SELECT 1 FROM geotagged_locations location
                WHERE location.household_id = h.household_id
                    AND location.latitude IS NOT NULL AND location.longitude IS NOT NULL
            ) THEN h.household_id END) as no_contact_households")
            ->get();
        $members = DB::table('household_members as m')
            ->join('households as h', 'h.household_id', '=', 'm.household_id')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('member_disaster_statuses as report', fn ($join) => $join->on('report.member_id', '=', 'm.member_id')->where('report.disaster_id', $eventId))
            ->leftJoin('member_statuses as ms', 'ms.status_id', '=', 'report.status_id')
            ->where('a.barangay_id', $barangayId)->whereNull('h.deleted_at')->whereNull('m.deleted_at')
            ->groupBy('a.sitio_id', 'p.sitio_id', 'a.purok_id', 'a.purok_sitio')
            ->selectRaw('a.sitio_id as address_sitio_id, p.sitio_id as purok_sitio_id, a.purok_id as address_purok_id, a.purok_sitio as address_label')
            ->selectRaw('COUNT(DISTINCT m.member_id) as total_members')
            ->selectRaw("COUNT(DISTINCT CASE WHEN ms.status_key IS NULL OR ms.status_key IN ('unknown','unchecked','unreported') THEN m.member_id END) as unreported_members")
            ->selectRaw("COUNT(DISTINCT CASE WHEN m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS
                (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id){$minorPredicate} THEN m.member_id END) as total_vulnerable_members")
            ->selectRaw("COUNT(DISTINCT CASE WHEN (m.is_pwd = 1 OR m.is_senior = 1 OR m.is_pregnant = 1 OR EXISTS
                (SELECT 1 FROM member_vulnerable_groups g WHERE g.member_id = m.member_id){$minorPredicate})
                AND (ms.status_key IS NULL OR ms.status_key NOT IN ('safe','safe_at_home','evacuated'))
                THEN m.member_id END) as unresolved_vulnerable_members")
            ->get();

        $normalize = fn (string $name): string => mb_strtolower(trim($name));
        $labels = [];
        foreach ($sitios as $sitio) {
            $labels[$normalize($sitio->sitio_name)] = [(int) $sitio->sitio_id, null];
        }
        foreach ($puroks as $sitePuroks) {
            foreach ($sitePuroks as $purok) {
                $sitio = $sitios->get($purok->sitio_id);
                if ($sitio) $labels[$normalize($purok->purok_name.', '.$sitio->sitio_name)] = [(int) $sitio->sitio_id, (int) $purok->purok_id];
            }
        }
        $resolveSitio = function (object $row) use ($sitios, $labels, $normalize): ?array {
            $sitioId = $row->address_sitio_id ?? $row->purok_sitio_id;
            $purokId = $row->address_purok_id;
            if ($sitioId === null) {
                $match = $labels[$normalize((string) $row->address_label)] ?? null;
                if ($match) [$sitioId, $purokId] = $match;
            }
            return $sitioId !== null && $sitios->has($sitioId)
                ? ['sitio_id' => (int) $sitioId, 'purok_id' => $purokId === null ? null : (int) $purokId]
                : null;
        };
        $groups = $sitios->mapWithKeys(fn (Sitio $sitio): array => [(int) $sitio->sitio_id => [
                'sitio_id' => (int) $sitio->sitio_id,
                'sitio' => $sitio->sitio_name,
                'is_catalogued' => true,
                'address_labels' => [],
                'households' => 0,
                'impacted_households' => 0,
                'urgent_households' => 0,
                'total_members' => 0,
                'special_needs_members' => 0,
                'unchecked_members' => 0,
                'unreported_members' => 0,
                'no_contact_households' => 0,
            ]])->all();
        foreach ($households as $householdRow) {
            $sitio = $resolveSitio($householdRow);
            if (! $sitio) continue;
            $group = &$groups[$sitio['sitio_id']];
            $group['households'] += (int) $householdRow->households;
            $group['impacted_households'] += (int) $householdRow->impacted_households;
            $group['urgent_households'] += (int) $householdRow->urgent_households;
            $group['no_contact_households'] += (int) $householdRow->no_contact_households;
            unset($group);
        }
        foreach ($members as $memberRow) {
            $sitio = $resolveSitio($memberRow);
            if (! $sitio) continue;
            $group = &$groups[$sitio['sitio_id']];
            $group['total_members'] += (int) $memberRow->total_members;
            $group['special_needs_members'] += (int) $memberRow->total_vulnerable_members;
            $group['unchecked_members'] += (int) $memberRow->unreported_members;
            $group['unreported_members'] += (int) $memberRow->unreported_members;
            unset($group);
        }

        foreach ($sitios as $sitio) {
            foreach ($puroks->get($sitio->sitio_id, collect()) as $purok) {
                $purokLabel = $purok->purok_name.', '.$sitio->sitio_name;
                $group = &$groups[$sitio->sitio_id];
                $group['address_labels'][$purokLabel] = (int) $purok->household_count;
                unset($group);
            }
        }

        return collect(array_values($groups))->map(function (array $group) use ($settings, $puroks): array {
            $householdTotal = $group['households'];
            $memberTotal = $group['total_members'];
            $shares = [
                'impact' => $householdTotal ? $group['impacted_households'] / $householdTotal : 0,
                'special_needs' => $memberTotal ? $group['special_needs_members'] / $memberTotal : 0,
                'unreported' => $memberTotal ? $group['unchecked_members'] / $memberTotal : 0,
                'no_contact' => $householdTotal ? $group['no_contact_households'] / $householdTotal : 0,
            ];
            $weights = ['impact' => 30, 'special_needs' => 30, 'unreported' => 25, 'no_contact' => 15];
            $contributions = [];
            foreach ($shares as $key => $share) $contributions[$key] = round($share * $weights[$key], 2);
            return [
                'sitio_id' => $group['sitio_id'], 'sitio' => $group['sitio'],
                'is_catalogued' => $group['is_catalogued'],
                'address_labels' => collect($group['address_labels'])->map(fn (int $count, string $label): array => [
                    'label' => $label,
                    'households' => $count,
                ])->values()->all(),
                'puroks' => ($puroks->get($group['sitio_id']) ?? collect())->map(fn ($purok): array => [
                    'purok_id' => (int) $purok->purok_id,
                    'purok_name' => $purok->purok_name,
                    'household_count' => (int) $purok->household_count,
                ])->values()->all(),
                'households' => $householdTotal, 'impacted_households' => $group['impacted_households'],
                'urgent_households' => $group['urgent_households'],
                'special_needs_members' => $group['special_needs_members'],
                'unchecked_members' => $group['unchecked_members'],
                'unreported_members' => $group['unreported_members'],
                'no_contact_households' => $group['no_contact_households'],
                'priority_score' => round(array_sum($contributions), 1), 'contributions' => $contributions,
            ];
        })->sort(fn ($a, $b) => ($b['priority_score'] <=> $a['priority_score']) ?: strcmp($a['sitio'], $b['sitio']))
            ->values()->map(function ($row, $index) use ($settings): array {
                $row['rank'] = $index + 1;
                $row['band'] = $this->presenter->priorityBand($row['priority_score'], $row['rank'], $settings);
                return $row;
            })->all();
    }
}
