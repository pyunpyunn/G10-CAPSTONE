<?php

namespace App\Actions;

use App\Queries\DispatchDestinationQuery;
use App\Models\Responder;
use App\Models\ResponderLocationLog;
use Illuminate\Validation\ValidationException;

/** Select actual responders from a requested quantity while their rows are locked. */
class SelectDispatchResponders
{
    public function __construct(private DispatchDestinationQuery $destinations) {}
    private const ACTIVE = ['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning'];

    public function select(int $teamId, string $eventId, int $requested, ?string $householdId, ?object $destination = null): array
    {
        $candidates = Responder::query()
            ->where('team_id', $teamId)
            ->where('duty_status', 'available')
            ->where('is_deployed', false)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('responder_assignments as active_assignment')
                ->whereColumn('active_assignment.responder_id', 'responders.responder_id')
                ->where('active_assignment.disaster_id', $eventId)
                ->whereIn('active_assignment.status', self::ACTIVE))
            ->orderBy('responder_id')
            ->lockForUpdate()
            ->get(['responder_id', 'full_name', 'last_active_at']);

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages(['responder_count' => ['No available rescuer remains in this team.']]);
        }

        $destination ??= $householdId ? $this->destinations->forHousehold($householdId, $eventId) : null;

        $locations = ResponderLocationLog::query()
            ->whereIn('responder_id', $candidates->pluck('responder_id'))
            ->where('logged_at', '>=', now()->subMinutes(30))
            ->orderByDesc('logged_at')->get(['responder_id', 'latitude', 'longitude', 'logged_at'])
            ->unique('responder_id')->keyBy('responder_id');

        $selected = $candidates->sort(function ($left, $right) use ($locations, $destination): int {
            $leftDistance = $this->distance($locations->get($left->responder_id), $destination);
            $rightDistance = $this->distance($locations->get($right->responder_id), $destination);
            if ($leftDistance !== null || $rightDistance !== null) {
                if ($leftDistance === null) return 1;
                if ($rightDistance === null) return -1;
                $comparison = $leftDistance <=> $rightDistance;
                if ($comparison !== 0) return $comparison;
            }

            return ($left->last_active_at?->timestamp ?? 0) <=> ($right->last_active_at?->timestamp ?? 0)
                ?: $left->responder_id <=> $right->responder_id;
        })->take(min($requested, $candidates->count()))->values();

        return [
            'ids' => $selected->pluck('responder_id')->map(fn ($id) => (int) $id)->all(),
            'names' => $selected->pluck('full_name')->all(),
            'available_count' => $candidates->count(),
            'requested_count' => $requested,
            'assigned_count' => $selected->count(),
        ];
    }

    private function distance(?object $location, ?object $destination): ?float
    {
        if (! $location || ! $destination) return null;
        $latitude = deg2rad((float) $destination->latitude - (float) $location->latitude);
        $longitude = deg2rad((float) $destination->longitude - (float) $location->longitude);
        $angle = sin($latitude / 2) ** 2
            + cos(deg2rad((float) $location->latitude)) * cos(deg2rad((float) $destination->latitude))
            * sin($longitude / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($angle)));
    }
}
