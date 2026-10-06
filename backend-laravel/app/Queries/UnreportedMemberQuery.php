<?php

namespace App\Queries;

use App\Models\HouseholdMember;
use App\Models\StatusReminder;
use Illuminate\Database\Eloquent\Builder;

class UnreportedMemberQuery
{
    public function forEvent(string $eventId): Builder
    {
        return HouseholdMember::query()->whereDoesntHave('disasterStatuses',
            fn (Builder $query) => $query->where('disaster_id', $eventId));
    }

    public function reminderQueue(string $eventId, int $perPage = 15): object
    {
        return StatusReminder::query()->with('member:member_id,first_name,last_name')
            ->where('event_id', $eventId)->whereIn('status', ['pending', 'sending'])
            ->orderBy('scheduled_at')->paginate(min(100, max(1, $perPage)));
    }
}
