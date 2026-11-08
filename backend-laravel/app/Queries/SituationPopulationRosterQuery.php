<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class SituationPopulationRosterQuery
{
    public function rows(string $eventId, ?string $sitioId = null): array
    {
        if (! Schema::hasTable('household_members')) return [];

        $members = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->leftJoin('household_members as hm', function ($join): void {
                $join->on('hm.household_id', '=', 'h.household_id');
                if (Schema::hasColumn('household_members', 'deleted_at')) {
                    $join->whereNull('hm.deleted_at');
                }
            })
            ->whereIn('h.household_id', HouseholdEligibilityQuery::ids())
            ->when($sitioId !== null, fn ($q) => $q->where('s.sitio_id', $sitioId));

        if (Schema::hasColumn('households', 'deleted_at')) {
            $members->whereNull('h.deleted_at');
        }

        $query = $members->leftJoinSub($this->latestMemberStatuses($eventId), 'latest_member_status', 'latest_member_status.member_id', '=', 'hm.member_id')
            ->select([
                'h.household_id', 'hm.member_id', 's.sitio_id', 's.sitio_name',
                'p.purok_id', 'p.purok_name',
                'latest_member_status.status_key as member_status_key',
            ]);
        if (Schema::hasColumn('households', 'household_code')) {
            $query->addSelect('h.household_code');
        } else {
            $query->addSelect(DB::raw('h.household_id as household_code'));
        }
        foreach (['first_name', 'middle_name', 'last_name'] as $column) {
            $query->addSelect(Schema::hasColumn('household_members', $column)
                ? 'hm.'.$column
                : DB::raw('NULL as '.$column));
        }

        return $query->orderBy('s.sitio_name')->orderBy('p.purok_name')
            ->orderBy('h.household_id')->orderBy('hm.member_id')->get()
            ->map(fn (object $row): array => [
                'household_id' => (string) $row->household_id,
                'household' => $row->household_code,
                'sitio_id' => (string) $row->sitio_id,
                'sitio' => $row->sitio_name,
                'purok_id' => (string) $row->purok_id,
                'purok' => $row->purok_name,
                'member_id' => $row->member_id === null ? null : (string) $row->member_id,
                'member_name' => trim(implode(' ', array_filter([
                    $row->first_name, $row->middle_name, $row->last_name,
                ], fn ($part) => is_string($part) && trim($part) !== ''))),
                'status_key' => $row->member_status_key,
            ])->all();
    }

    private function latestMemberStatuses(string $eventId)
    {
        if (! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
            return DB::table('household_members')->whereRaw('1 = 0')
                ->selectRaw('NULL as member_id, NULL as status_key');
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

        return DB::table('member_disaster_statuses as mds')
            ->joinSub($latest, 'latest', 'latest.member_status_id', '=', 'mds.member_status_id')
            ->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
            ->select('mds.member_id', 'ms.status_key');
    }
}
