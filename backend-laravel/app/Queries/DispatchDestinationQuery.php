<?php

namespace App\Queries;

use App\Models\GeotaggedLocation;
use Illuminate\Support\Facades\DB;

class DispatchDestinationQuery
{
    private const URGENT_STATUSES = ['unsafe', 'needs_help', 'needs_assistance', 'trapped'];

    public function forHousehold(string $householdId, string $eventId): ?object
    {
        $currentStatus = DB::table('household_disasters as hd')
            ->join('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('hd.household_id', $householdId)->where('hd.disaster_id', $eventId)
            ->value('hs.status_key');

        if (in_array($currentStatus, self::URGENT_STATUSES, true)) {
            $pin = DB::table('household_status_logs as log')
                ->join('household_statuses as reported', 'reported.status_id', '=', 'log.status_id')
                ->where('log.household_id', $householdId)->where('log.disaster_id', $eventId)
                ->where('log.source', 'trusted_household')->where('reported.status_key', 'unsafe')
                ->whereNotNull('log.latitude')->whereNotNull('log.longitude')
                ->orderByDesc('log.submitted_at')->orderByDesc('log.status_log_id')
                ->first(['log.latitude', 'log.longitude', 'log.location_label']);

            if ($pin) {
                $pin->source = 'trusted_unsafe_report';
                return $pin;
            }
        }

        $geotag = GeotaggedLocation::query()->where('household_id', $householdId)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderByDesc('updated_at')->first(['latitude', 'longitude', 'location_label']);
        if ($geotag) $geotag->source = 'household_geotag';
        return $geotag;
    }
}
