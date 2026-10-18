<?php

namespace App\Queries;

use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

/** Registered purok ownership is authoritative; address text and GPS do not establish residency. */
class HouseholdEligibilityQuery
{
    public static function puroks(int|string|null $barangayId = null)
    {
        $barangayId ??= app(BarangayProfileService::class)->current()['barangay_id'] ?? null;
        return DB::table('puroks as eligible_purok')
            ->join('sitios as eligible_sitio', 'eligible_sitio.sitio_id', '=', 'eligible_purok.sitio_id')
            ->where('eligible_sitio.barangay_id', $barangayId)
            ->when($barangayId === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->select('eligible_purok.purok_id');
    }

    public static function ids(int|string|null $barangayId = null)
    {
        return DB::table('households as eligible_household')
            ->join('addresses as eligible_address', 'eligible_address.address_id', '=', 'eligible_household.address_id')
            ->whereIn('eligible_address.purok_id', self::puroks($barangayId))
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($q) => $q->whereNull('eligible_household.deleted_at'))
            ->select('eligible_household.household_id');
    }
}
