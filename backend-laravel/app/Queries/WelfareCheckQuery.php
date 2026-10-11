<?php

namespace App\Queries;

use App\Models\Household;
use Illuminate\Database\Eloquent\Builder;

class WelfareCheckQuery
{
    public function households(string $eventId, int $perPage = 15): object
    {
        return $this->baseQuery($eventId)->with('address')->withExists('geotags as has_geotag')
            ->orderBy('household_id')
            ->paginate(min(100, max(1, $perPage)));
    }

    public function qualifies(string $householdId, string $eventId): bool
    {
        return $this->baseQuery($eventId)->where('household_id', $householdId)->exists();
    }

    private function baseQuery(string $eventId): Builder
    {
        return Household::query()
            ->whereDoesntHave('disasterSnapshots', fn (Builder $query) => $query
                ->where('disaster_id', $eventId)->whereNotNull('current_status_id'))
            ->where(fn (Builder $query) => $query->whereNull('contact_number')->orWhere('contact_number', ''))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('device_tokens as own_device')
                ->whereColumn('own_device.household_id', 'households.household_id')
                ->where('own_device.is_active', true))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('trusted_households as trust')
                ->join('households as trusted', 'trusted.household_id', '=', 'trust.trusted_household_id')
                ->whereColumn('trust.requesting_household_id', 'households.household_id')
                ->whereIn('trust.validation_status', ['validated', 'approved'])
                ->where(fn ($inner) => $inner->whereNotNull('trusted.contact_number')
                    ->where('trusted.contact_number', '<>', '')
                    ->orWhereExists(fn ($devices) => $devices->selectRaw('1')->from('device_tokens as trusted_device')
                        ->whereColumn('trusted_device.household_id', 'trusted.household_id')
                        ->where('trusted_device.is_active', true))))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('trusted_households as trust')
                ->join('households as trusted', 'trusted.household_id', '=', 'trust.requesting_household_id')
                ->whereColumn('trust.trusted_household_id', 'households.household_id')
                ->whereIn('trust.validation_status', ['validated', 'approved'])
                ->where(fn ($inner) => $inner->whereNotNull('trusted.contact_number')
                    ->where('trusted.contact_number', '<>', '')
                    ->orWhereExists(fn ($devices) => $devices->selectRaw('1')->from('device_tokens as trusted_device')
                        ->whereColumn('trusted_device.household_id', 'trusted.household_id')
                        ->where('trusted_device.is_active', true))))
            ;
    }
}
