<?php

namespace App\Queries;

use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Facades\DB;

class SitioDispatchCoverageQuery
{
    public function __construct(private BarangayProfileService $barangay) {}

    public function withActiveAssignments(array $priorities, array $assignments): array
    {
        $assignments = array_filter($assignments, fn($assignment) => in_array($assignment['status']['key'] ?? '', ['dispatched','accepted','en_route','on_scene','returning'], true));
        $assignedIds = fn($assignment) => array_values(array_unique(array_filter(array_merge(
            $assignment['household_ids'] ?? [], [$assignment['household_id'] ?? null],
            array_merge([], ...array_values($assignment['responder_households'] ?? [])),
        ))));
        $legacyIds = [];
        foreach ($assignments as $assignment) {
            if (empty($assignment['sitio_id'])) $legacyIds = array_merge($legacyIds, $assignedIds($assignment));
        }
        $legacyIds = array_values(array_unique(array_filter($legacyIds)));
        $sites = $legacyIds ? DB::table('households as h')->join('addresses as a','a.address_id','=','h.address_id')
            ->leftJoin('puroks as p','p.purok_id','=','a.purok_id')->whereIn('h.household_id',$legacyIds)
            ->select('h.household_id')->selectRaw('COALESCE(p.sitio_id,a.sitio_id) as sitio_id')
            ->get()->pluck('sitio_id', 'household_id')->all() : [];
        return array_map(function ($priority) use ($assignments, $sites, $assignedIds) {
            $labels = array_merge([$priority['sitio']], array_column($priority['address_labels'] ?? [], 'label'));
            $priority['activeDispatches'] = [];
            foreach ($assignments as $assignment) {
                $ids = $assignedIds($assignment);
                $matches = !empty($assignment['sitio_id']) ? (int)$assignment['sitio_id'] === (int)$priority['sitio_id']
                    : ($ids ? collect($ids)->contains(fn($id) => (int)($sites[$id] ?? 0) === (int)$priority['sitio_id'])
                        : in_array($assignment['assigned_area'] ?? '', $labels, true));
                if ($matches) $priority['activeDispatches'][] = array_intersect_key($assignment, array_flip([
                    'assignment_id','assignment_code','team_name','assigned_area','status','selected_responder_ids','responder_id',
                ]));
            }
            return $priority;
        }, $priorities);
    }

    public function forEvent(?string $eventId, $riskAreas, array $activeAssignments): array
    {
        if (! $eventId) return [];
        $barangayId = $this->barangay->current()['barangay_id'] ?? null;
        if ($barangayId === null) return [];
        $sitios = DB::table('sitios')->where('barangay_id', $barangayId)->orderBy('sitio_name')->get();
        $households = DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->whereIn('a.purok_id', HouseholdEligibilityQuery::puroks($barangayId))->whereNull('h.deleted_at')
            ->select('h.household_id', 'a.purok_id', 'p.purok_name', DB::raw('COALESCE(p.sitio_id, a.sitio_id) as sitio_id'))
            ->get()->groupBy('sitio_id');
        $activeAssignments = array_filter($activeAssignments, fn ($a) =>
            in_array($a['status']['key'] ?? '', ['dispatched', 'accepted', 'en_route', 'on_scene', 'returning'], true)
            && (! isset($a['disaster_id']) || (string) $a['disaster_id'] === $eventId));
        return $sitios->map(function ($sitio) use ($households, $riskAreas, $activeAssignments): array {
            $members = $households->get($sitio->sitio_id, collect());
            $ids = $members->pluck('household_id')->map(fn ($id) => (string) $id)->all();
            $labels = $members->filter(fn ($h) => $h->purok_name !== null)
                ->map(fn ($h) => $h->purok_name.', '.$sitio->sitio_name)->push($sitio->sitio_name)->unique()->all();
            $assignments = [];
            $covered = [];
            $verified = true;
            foreach ($activeAssignments as $assignment) {
                $assignedIds = array_unique(array_map('strval', array_filter(array_merge(
                    [$assignment['household_id'] ?? null], $assignment['household_ids'] ?? [],
                    array_merge([], ...array_values($assignment['responder_households'] ?? [])),
                ))));
                $matching = array_values(array_intersect($ids, $assignedIds));
                $explicitSitio = $assignment['sitio_id'] ?? null;
                $legacyArea = ! $assignedIds && $explicitSitio === null
                    && in_array($assignment['assigned_area'] ?? '', $labels, true);
                if (! $matching && (string) $explicitSitio !== (string) $sitio->sitio_id && ! $legacyArea) continue;
                $assignments[] = $assignment;
                $covered = array_merge($covered, $matching);
                if (! $assignedIds) $verified = false;
            }
            $covered = array_values(array_unique($covered));
            $uncovered = array_values(array_diff($ids, $covered));
            $dispatchable = collect($riskAreas)->where('sitio_id', $sitio->sitio_id)
                ->flatMap(fn ($area) => collect($area['households'])->where('is_available_for_dispatch', true)->pluck('household_id'))
                ->map(fn ($id) => (string) $id)->unique()->filter(fn ($id) => in_array($id, $uncovered, true))->values()->all();
            return [
                'sitio_id' => (int) $sitio->sitio_id, 'sitio_name' => $sitio->sitio_name,
                'is_assigned' => count($assignments) > 0, 'coverage_verified' => $verified,
                'total_households' => count($ids), 'covered_households' => count($covered),
                'uncovered_households' => count($uncovered),
                'dispatchable_household_ids' => $verified ? $dispatchable : [],
                'dispatchable_households' => $verified ? count($dispatchable) : 0,
                'can_add_team' => $verified && count($assignments) > 0 && count($dispatchable) > 0,
                'active_assignments' => $assignments,
            ];
        })->all();
    }
}
