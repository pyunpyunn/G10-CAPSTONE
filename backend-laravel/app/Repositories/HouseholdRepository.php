<?php

namespace App\Repositories;

use App\Models\Household;
use App\Models\HouseholdStatusLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Persistence queries for the household aggregate. */
class HouseholdRepository extends EloquentRepository
{
    public function __construct(Household $model)
    {
        parent::__construct($model);
    }

    public function detail(string $householdId): Household
    {
        return $this->model->newQuery()
            ->with([
                'address.barangay',
                'address.purok',
                'members.gender',
                'members.relationship',
                'devices',
            ])
            ->findOrFail($householdId);
    }

    public function paginatedForEvent(?string $eventId, array $filters, int $perPage = 25)
    {
        return $this->model->newQuery()
            ->with(['address', 'members', 'devices'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->search($term))
            ->when(($filters['purok'] ?? null) && ($filters['purok'] ?? null) !== 'all', fn (Builder $query, string $purok) => $query->whereHas('address', fn (Builder $address) => $address->where('purok_sitio', $purok)))
            ->when($eventId, fn (Builder $query) => $query->with(['disasterSnapshots' => fn ($snapshots) => $snapshots->where('disaster_id', $eventId)->with('status')]))
            ->orderBy('household_name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /** @return Collection<int, HouseholdStatusLog> */
    public function latestStatusLogs(string $householdId, ?string $eventId)
    {
        return HouseholdStatusLog::query()
            ->with(['status', 'submittedBy'])
            ->where('household_id', $householdId)
            ->when($eventId, fn (Builder $query) => $query->where('disaster_id', $eventId))
            ->latest('submitted_at')
            ->latest('created_at')
            ->limit(50)
            ->get();
    }
}
