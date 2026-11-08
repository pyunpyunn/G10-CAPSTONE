<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SituationMemberStatusQuery
{
    private const SAFE_KEYS = ['safe', 'safe_at_home', 'active', 'returned'];
    private const UNSAFE_KEYS = ['unsafe', 'needs_help', 'need_help', 'needs_assistance', 'not_evacuated', 'displaced', 'trapped', 'unreachable'];
    private const EVACUATED_KEYS = ['evacuated', 'relocated'];

    public function byPurok(string $eventId, ?string $sitioId = null): ?Collection
    {
        foreach (['member_disaster_statuses', 'member_statuses', 'household_members'] as $table) {
            if (! Schema::hasTable($table)) return null;
        }

        $latest = DB::table('member_disaster_statuses as report')
            ->where('report.disaster_id', $eventId)
            ->whereNotExists(function ($newer): void {
                $newer->selectRaw('1')->from('member_disaster_statuses as newer')
                    ->whereColumn('newer.disaster_id', 'report.disaster_id')
                    ->whereColumn('newer.member_id', 'report.member_id')
                    ->where(function ($time): void {
                        $time->whereColumn('newer.reported_at', '>', 'report.reported_at')
                            ->orWhere(fn ($tie) => $tie->whereColumn('newer.reported_at', 'report.reported_at')
                                ->whereColumn('newer.member_status_id', '>', 'report.member_status_id'));
                    });
            });

        $query = DB::table('member_disaster_statuses as mds')
            ->joinSub($latest, 'latest', 'latest.member_status_id', '=', 'mds.member_status_id')
            ->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
            ->join('household_members as hm', function ($join): void {
                $join->on('hm.member_id', '=', 'mds.member_id')
                    ->on('hm.household_id', '=', 'mds.household_id');
            })
            ->join('households as h', 'h.household_id', '=', 'mds.household_id')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->whereIn('mds.household_id', HouseholdEligibilityQuery::ids())
            ->where('mds.disaster_id', $eventId)
            ->when($sitioId !== null, fn ($q) => $q->where('s.sitio_id', $sitioId));

        if (Schema::hasColumn('household_members', 'deleted_at')) {
            $query->whereNull('hm.deleted_at');
        }

        $statusKey = 'ms.status_key';
        $deathCondition = "$statusKey IN ('dead', 'deceased')";
        $injuryCondition = "$statusKey = 'injured'";
        if (Schema::hasColumn('member_statuses', 'counts_as_death')) {
            $deathCondition = "($deathCondition OR ms.counts_as_death = 1)";
        }
        if (Schema::hasColumn('member_statuses', 'counts_as_injury')) {
            $injuryCondition = "($injuryCondition OR ms.counts_as_injury = 1)";
        }

        return $query->select('p.purok_id')
            ->selectRaw('COUNT(DISTINCT mds.member_id) as reported_members')
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.$statusKey.' IN (\''.implode("','", self::SAFE_KEYS).'\') THEN mds.member_id END) as safe')
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.$statusKey.' IN (\''.implode("','", self::UNSAFE_KEYS).'\') THEN mds.member_id END) as unsafe')
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.$statusKey.' IN (\''.implode("','", self::EVACUATED_KEYS).'\') THEN mds.member_id END) as evacuated')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$deathCondition} THEN mds.member_id END) as dead")
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.$statusKey.' = \'missing\' THEN mds.member_id END) as missing')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$injuryCondition} THEN mds.member_id END) as injured")
            ->groupBy('p.purok_id')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->purok_id);
    }
}
