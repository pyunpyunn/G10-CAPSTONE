<?php

namespace App\Services\Navigation;

use App\Models\CachedRoute;
use App\Models\TeamBase;
use App\Services\Shared\ValhallaRoutingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RoutePrecomputeService
{
    public function __construct(
        private ValhallaRoutingService $routing,
        private TeamBaseInitializationService $bases,
    ) {}

    public function precomputeTeam(int $teamId): array
    {
        $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();
        if (! $team) {
            return ['team_id' => $teamId, 'households' => 0, 'ready' => 0, 'failed' => 1, 'error' => 'team_not_found'];
        }

        $targets = $this->householdIdsForTeam($team)->map(fn (string $id): array => [
            'kind' => 'household', 'id' => $id,
        ])->merge($this->evacuationCenters())->values();

        $base = $this->bases->ensureDefaultBase($teamId);
        $result = ['team_id' => $teamId, 'households' => 0, 'centers' => 0, 'ready' => 0, 'failed' => 0];
        foreach ($targets as $target) {
            if ($target['kind'] === 'household') {
                $result['households']++;
            } else {
                $result['centers']++;
            }

            foreach (config('navigation.profiles', ['auto', 'pedestrian']) as $profile) {
                $route = $this->precomputeTarget($teamId, $base, $target, $profile);
                $result[$route['status']]++;
            }
        }

        if ($targets->isEmpty()) {
            Log::warning('Navigation route coverage has no household or evacuation-center targets.', [
                'team_id' => $teamId,
                'assigned_purok_id' => $team->assigned_purok_id,
            ]);
        }

        return $result;
    }

    public function precomputeHouseholdForTeam(int $teamId, string $householdId): array
    {
        $base = $this->bases->ensureDefaultBase($teamId);
        $target = ['kind' => 'household', 'id' => $householdId];
        $results = [];

        foreach (config('navigation.profiles', ['auto', 'pedestrian']) as $profile) {
            $results[$profile] = $this->precomputeTarget($teamId, $base, $target, $profile);
        }

        return $results;
    }

    public function assignedHouseholdIds(int $teamId)
    {
        $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();

        return $team ? $this->householdIdsForTeam($team) : collect();
    }

    public function recomputeHousehold(string $householdId): int
    {
        $teamIds = collect();
        $householdAddress = DB::table('households as h')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->where('h.household_id', $householdId)
            ->value('a.purok_id');

        if ($householdAddress !== null) {
            $teamIds = $teamIds->merge(DB::table('rescue_teams as team')
                ->join('addresses as team_address', 'team_address.address_id', '=', 'team.assigned_purok_id')
                ->where('team_address.purok_id', $householdAddress)
                ->pluck('team.team_id'));
        }

        $assignments = DB::table('responder_assignments')
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->where('team_id', '>', 0)
            ->get(['team_id', 'household_id', 'route_notes']);

        foreach ($assignments as $assignment) {
            if ($assignment->household_id === $householdId) {
                $teamIds->push($assignment->team_id);
                continue;
            }

            $notes = json_decode((string) $assignment->route_notes, true) ?: [];
            $assigned = array_merge(
                $notes['household_ids'] ?? [],
                ...array_values($notes['responder_households'] ?? []),
            );
            if (in_array($householdId, array_map('strval', $assigned), true)) {
                $teamIds->push($assignment->team_id);
            }
        }

        $teamIds = $teamIds->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        foreach ($teamIds as $teamId) {
            $this->precomputeHouseholdForTeam($teamId, $householdId);
        }

        if ($teamIds->isEmpty()) {
            Log::warning('Household route recompute found no team assignment.', ['household_id' => $householdId]);
        }

        return $teamIds->count();
    }

    public function coverageRows(?int $teamId = null): array
    {
        $teams = DB::table('rescue_teams')->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->orderBy('team_id')->get(['team_id', 'team_name', 'assigned_purok_id']);
        $coverage = [];
        $failures = [];

        foreach ($teams as $team) {
            $householdIds = $this->householdIdsForTeam($team);
            $householdCache = DB::table('cached_routes')->where('team_id', $team->team_id)
                ->whereIn('household_id', $householdIds->all())->get()->keyBy(fn ($route): string => $route->household_id.'|'.$route->profile);

            foreach (config('navigation.profiles', ['auto', 'pedestrian']) as $profile) {
                $readyCount = 0;
                foreach ($householdIds as $householdId) {
                    $route = $householdCache->get($householdId.'|'.$profile);
                    if ($route?->status === 'ready') {
                        $readyCount++;
                        continue;
                    }

                    $failures[] = [
                        'team_id' => (int) $team->team_id,
                        'team_name' => $team->team_name,
                        'household_id' => $householdId,
                        'evac_center_id' => null,
                        'profile' => $profile,
                        'failure_reason' => $route?->failure_reason ?? 'not_precomputed',
                    ];
                }

                $total = $householdIds->count();
                $coverage[] = [
                    'team_id' => (int) $team->team_id,
                    'team_name' => $team->team_name,
                    'profile' => $profile,
                    'total' => $total,
                    'ready' => $readyCount,
                    'failed' => $total - $readyCount,
                    'percent' => $total > 0 ? round(($readyCount / $total) * 100, 1) : null,
                ];
            }
        }

        return ['coverage' => $coverage, 'failures' => $failures];
    }

    private function precomputeTarget(int $teamId, ?TeamBase $base, array $target, string $profile): array
    {
        $householdId = $target['kind'] === 'household' ? $target['id'] : null;
        $centerId = $target['kind'] === 'evac_center' ? $target['id'] : null;
        $identity = [
            'team_id' => $teamId,
            'target_key' => $target['kind'].':'.$target['id'],
            'profile' => $profile,
        ];
        $reason = null;
        $result = null;
        $destination = $this->targetPoint($target);

        if (! $base) {
            $reason = 'team_base_missing';
        } elseif (! $destination) {
            $reason = 'target_geotag_missing';
        } else {
            try {
                $result = $this->routing->routeBetween(
                    ['latitude' => (float) $base->lat, 'longitude' => (float) $base->lng],
                    $destination,
                    $profile,
                );
                if (! $result) {
                    $reason = 'no_route_found';
                }
            } catch (Throwable $exception) {
                $reason = 'valhalla_error';
                Log::warning('Valhalla route precompute failed.', [
                    'team_id' => $teamId,
                    'target_type' => $target['kind'],
                    'target_id' => $target['id'],
                    'profile' => $profile,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $ready = $result !== null;
        CachedRoute::query()->updateOrCreate($identity, [
            'household_id' => $householdId,
            'evac_center_id' => $centerId,
            'polyline6' => $result['polyline6'] ?? null,
            'steps' => $result['steps'] ?? null,
            'distance_m' => $result['distance_m'] ?? null,
            'duration_s' => $result['duration_s'] ?? null,
            'status' => $ready ? 'ready' : 'failed',
            'failure_reason' => $reason,
            'computed_at' => now(),
        ]);

        if (! $ready && $reason !== 'valhalla_error') {
            Log::warning('Cached navigation route is unavailable.', [
                'team_id' => $teamId,
                'target_type' => $target['kind'],
                'target_id' => $target['id'],
                'profile' => $profile,
                'reason' => $reason,
            ]);
        }

        return ['status' => $ready ? 'ready' : 'failed', 'reason' => $reason];
    }

    private function householdIdsForTeam(object $team)
    {
        $ids = collect();
        if ($team->assigned_purok_id && Schema::hasTable('addresses')) {
            $purokId = DB::table('addresses')->where('address_id', $team->assigned_purok_id)->value('purok_id');
            if ($purokId !== null) {
                $query = DB::table('households as household')
                    ->join('addresses as address', 'address.address_id', '=', 'household.address_id')
                    ->where('address.purok_id', $purokId);
                if (Schema::hasColumn('households', 'deleted_at')) {
                    $query->whereNull('household.deleted_at');
                }
                $ids = $ids->merge($query->pluck('household.household_id'));
            }
        }

        $assignments = DB::table('responder_assignments')->where('team_id', $team->team_id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get(['household_id', 'route_notes']);
        foreach ($assignments as $assignment) {
            if ($assignment->household_id) {
                $ids->push((string) $assignment->household_id);
            }
            $notes = json_decode((string) $assignment->route_notes, true) ?: [];
            $ids = $ids->merge($notes['household_ids'] ?? []);
            foreach ($notes['responder_households'] ?? [] as $households) {
                $ids = $ids->merge(is_array($households) ? $households : []);
            }
        }

        return $ids->map(fn ($id): string => (string) $id)->filter()->unique()->values();
    }

    private function evacuationCenters(): array
    {
        $query = DB::table('evacuation_centers')->where('status', 'active');
        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->get(['evacuation_center_id'])->map(fn (object $center): array => [
            'kind' => 'evac_center', 'id' => (string) $center->evacuation_center_id,
        ])->all();
    }

    private function targetPoint(array $target): ?array
    {
        if ($target['kind'] === 'household') {
            $pin = DB::table('geotagged_locations')
                ->where('household_id', $target['id'])
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->orderByDesc('updated_at')->orderByDesc('location_id')
                ->first(['latitude', 'longitude']);
        } else {
            $pin = DB::table('evacuation_centers')
                ->where('evacuation_center_id', $target['id'])
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->first(['latitude', 'longitude']);
        }

        return $pin ? ['latitude' => (float) $pin->latitude, 'longitude' => (float) $pin->longitude] : null;
    }
}