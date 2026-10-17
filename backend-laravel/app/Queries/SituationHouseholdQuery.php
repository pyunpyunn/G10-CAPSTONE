<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SituationHouseholdQuery
{
    public function rows($base, string $eventId): array
    {
        $columns = ['h.household_id', 's.sitio_id', 's.sitio_name', 'p.purok_id', 'p.purok_name', 'hs.status_key', 'latest_report.status_log_id'];
        if (Schema::hasColumn('households', 'household_code')) $columns[] = 'h.household_code';
        $households = $base->whereIn('hs.status_key', HouseholdEventStatusQuery::UNSAFE_KEYS)->orderBy('s.sitio_name')->orderBy('p.purok_name')->orderBy('h.household_id')->get($columns);
        if ($households->isEmpty()) return [];
        $logs = DB::table('household_status_logs')->whereIn('status_log_id', $households->pluck('status_log_id'))->get()->keyBy('status_log_id');
        $fieldReports = collect();
        if (Schema::hasTable('responder_field_reports') && Schema::hasColumn('responder_field_reports', 'status_log_id')) {
            $fieldReports = DB::table('responder_field_reports')->whereIn('status_log_id', $households->pluck('status_log_id'))->orderBy('report_id')->get()->keyBy('status_log_id');
        }
        $members = collect();
        if (Schema::hasTable('household_members')) {
            $q = DB::table('household_members as m')->whereIn('m.household_id', $households->pluck('household_id'));
            if (Schema::hasColumn('household_members', 'deleted_at')) $q->whereNull('m.deleted_at');
            $q->select('m.*');
            if (Schema::hasTable('genders')) $q->leftJoin('genders as g', 'g.gender_id', '=', 'm.gender_id')->addSelect('g.gender_label');
            $members = $q->orderBy('m.member_id')->get()->groupBy('household_id');
        }
        $statuses = collect();
        if (Schema::hasTable('member_disaster_statuses') && Schema::hasTable('member_statuses')) {
            $statuses = DB::table('member_disaster_statuses as mds')->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
                ->where('mds.disaster_id', $eventId)->whereIn('mds.household_id', $households->pluck('household_id'))
                ->orderBy('mds.member_status_id')->get(['mds.member_id', 'ms.status_key'])->keyBy('member_id');
        }
        return $households->map(function ($h) use ($logs, $members, $statuses, $fieldReports): array {
            $log = $logs->get($h->status_log_id);
            $notes = json_decode($log->notes ?? '', true);
            $notes = is_array($notes) ? $notes : [];
            $field = $fieldReports->get($h->status_log_id);
            foreach (['property_damage', 'injured_count', 'death_count'] as $key) if (isset($field->$key)) $notes[$key] = $field->$key;
            $recorded = collect($notes['members'] ?? [])->keyBy('member_id');
            $all = $members->get($h->household_id, collect())->map(function ($m) use ($recorded, $statuses): array {
                $status = $recorded->get($m->member_id)['status_key'] ?? $statuses->get($m->member_id)?->status_key;
                $age = null;
                if ($m->birth_date ?? null) {
                    try { $age = (int) Carbon::parse($m->birth_date)->age; } catch (\Throwable) {}
                }
                return ['member_id' => $m->member_id, 'name' => trim(implode(' ', array_filter([$m->first_name ?? '', $m->middle_name ?? '', $m->last_name ?? '']))),
                    'age' => $age, 'sex' => $m->gender_label ?? null, 'status' => $status];
            });
            $affected = $all->filter(fn ($m) => in_array($m['status'], [...HouseholdEventStatusQuery::UNSAFE_KEYS, ...HouseholdEventStatusQuery::EVACUATED_KEYS, 'rescued'], true))->values();
            $count = fn ($keys) => $all->filter(fn ($m) => in_array($m['status'], $keys, true))->count();
            $assessed = $all->isNotEmpty() && $all->every(fn ($m) => $m['status'] !== null && !in_array($m['status'], ['unknown', 'unchecked'], true));
            $damage = $notes['property_damage'] ?? null;
            return ['household_id' => $h->household_id, 'household' => $h->household_code ?? $h->household_id,
                'sitio_id' => $h->sitio_id, 'sitio' => $h->sitio_name, 'purok_id' => $h->purok_id, 'purok' => $h->purok_name,
                'registered_members' => Schema::hasTable('household_members') ? $all->count() : null, 'affected_members' => $assessed ? $affected->count() : null,
                'status' => $h->status_key, 'members' => $affected->all(),
                'missing' => $assessed ? $count(['missing']) : null,
                'injured' => $notes['injured_count'] ?? ($assessed ? $count(['injured']) : null),
                'rescued' => $assessed ? $count(['rescued', 'evacuated', 'relocated']) : null,
                'immediate_needs' => $notes['immediate_needs'] ?? $notes['user_notes'] ?? ($notes === [] ? ($log->notes ?? null) : null),
                'damage' => $damage, 'reported_at' => $log->submitted_at ?? null];
        })->all();
    }
}
