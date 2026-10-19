<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SituationEvacuationQuery
{
    public function summary($base, string $eventId): array
    {
        $columns = ['h.household_id', 's.sitio_id', 's.sitio_name', 'p.purok_id', 'p.purok_name', 'hs.status_key'];
        if (Schema::hasColumn('households', 'household_code')) $columns[] = 'h.household_code';
        $households = $base->get($columns)->keyBy('household_id');
        $members = collect(); $assessedHouseholds = collect();
        if (Schema::hasTable('household_members') && Schema::hasTable('member_disaster_statuses') && Schema::hasTable('member_statuses')) {
            $q = DB::table('member_disaster_statuses as mds')->join('household_members as m', 'm.member_id', '=', 'mds.member_id')
                ->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')->where('mds.disaster_id', $eventId)
                ->whereIn('m.household_id', $households->keys())->whereColumn('mds.household_id', 'm.household_id');
            if (Schema::hasColumn('household_members', 'deleted_at')) $q->whereNull('m.deleted_at');
            $q->select('m.*', 'ms.status_key');
            if (Schema::hasTable('genders') && Schema::hasColumn('household_members','gender_id')) $q->leftJoin('genders as g','g.gender_id','=','m.gender_id')->addSelect('g.gender_label');
            $q->addSelect(Schema::hasColumn('member_disaster_statuses', 'evacuation_center_id') ? 'mds.evacuation_center_id' : DB::raw('NULL as evacuation_center_id'));
            $assessments = $q->orderBy('mds.member_status_id')->get()->keyBy('member_id');
            $assessedHouseholds = $assessments->pluck('household_id')->unique();
            $members = $assessments->filter(fn ($m) => in_array($m->status_key, HouseholdEventStatusQuery::EVACUATED_KEYS, true))->values();
        }
        $records = collect();
        if (Schema::hasTable('evacuation_records')) {
            $q = DB::table('evacuation_records')->where('event_id', $eventId)->whereIn('household_id', $households->keys());
            if (Schema::hasColumn('evacuation_records', 'verified_at')) $q->orderBy('verified_at');
            $records = $q->orderBy('evacuation_id')->get()->keyBy('household_id');
        }
        $fallback = $households->filter(fn ($h) => in_array($h->status_key, HouseholdEventStatusQuery::EVACUATED_KEYS, true) && !$assessedHouseholds->contains($h->household_id));
        $siteIds = $members->pluck('evacuation_center_id')->merge($fallback->keys()->map(fn ($id) => $records->get($id)?->center_id))->filter()->unique();
        $sites = collect();
        if (Schema::hasTable('evacuation_centers')) {
            $q = DB::table('evacuation_centers')->where(function ($q) use ($eventId, $siteIds) {
                $q->whereIn('evacuation_center_id', $siteIds);
                if (Schema::hasColumn('evacuation_centers', 'current_event_id')) $q->orWhere('current_event_id', $eventId);
            });
            if (Schema::hasColumn('evacuation_centers', 'deleted_at')) $q->whereNull('deleted_at');
            $sites = $q->orderBy('name')->get()->keyBy('evacuation_center_id');
        }
        $memberRows = $members->map(function ($m) use ($households, $sites): array {
            $h = $households->get($m->household_id); $age = null;
            if ($m->birth_date ?? null) { try { $age = (int) Carbon::parse($m->birth_date)->age; } catch (\Throwable) {} }
            return [...$this->location($h), 'member_id' => $m->member_id,
                'name' => trim(implode(' ', array_filter([$m->first_name ?? '', $m->middle_name ?? '', $m->last_name ?? '']))),
                'age' => $age, 'sex' => $m->gender_label ?? null,
                'is_pwd' => isset($m->is_pwd) ? (bool) $m->is_pwd : null,
                'is_pregnant' => isset($m->is_pregnant) ? (bool) $m->is_pregnant : null,
                'is_lactating' => isset($m->is_lactating) ? (bool) $m->is_lactating : null,
                'displacement_location' => $m->evacuation_center_id ? 'inside' : null,
                'status' => $m->status_key, 'site_id' => $m->evacuation_center_id,
                'site' => $sites->get($m->evacuation_center_id)?->name];
        })->all();
        $householdRows = [];
        foreach ($members->groupBy('household_id') as $id => $people) {
            $h = $households->get($id);
            $householdRows[] = [...$this->location($h), 'evacuated_members' => $people->count(),
                'displacement_location' => $people->every(fn ($m) => !empty($m->evacuation_center_id)) ? 'inside' : null,
                'sites' => $people->map(fn ($m) => $sites->get($m->evacuation_center_id)?->name ?? 'Not recorded')->unique()->values()->all(), 'source' => 'Member assessments'];
        }
        foreach ($fallback as $h) {
            $record = $records->get($h->household_id);
            $householdRows[] = [...$this->location($h), 'evacuated_members' => isset($record->evacuated_count) ? (int) $record->evacuated_count : null,
                'displacement_location' => $record?->center_id ? 'inside' : null,
                'sites' => [$sites->get($record?->center_id)?->name ?? 'Not recorded'], 'source' => 'Household evacuation record; member identities not recorded'];
        }
        $siteRows = $sites->map(function ($s) use ($members): array {
            $capacity = isset($s->capacity) ? (int) $s->capacity : null;
            $occupied = isset($s->current_occupancy) ? (int) $s->current_occupancy : null;
            return ['site_id' => $s->evacuation_center_id, 'name' => $s->name, 'status' => $s->status ?? null,
                'capacity' => $capacity, 'occupancy' => $occupied,
                'vacancy' => $capacity !== null && $occupied !== null ? max(0, $capacity-$occupied) : null,
                'assigned_members' => $members->where('evacuation_center_id', $s->evacuation_center_id)->count()];
        })->values()->all();
        return ['households' => $householdRows, 'members' => $memberRows, 'sites' => $siteRows];
    }

    private function location(object $h): array
    {
        return ['household_id' => $h->household_id, 'household' => $h->household_code ?? $h->household_id,
            'sitio_id' => $h->sitio_id, 'sitio' => $h->sitio_name, 'purok_id' => $h->purok_id, 'purok' => $h->purok_name];
    }
}
