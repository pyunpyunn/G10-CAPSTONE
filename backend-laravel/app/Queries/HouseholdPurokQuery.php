<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class HouseholdPurokQuery
{
    /** @return list<string> */
    public function names(int|string|null $barangayId = null): array
    {
        if (! Schema::hasTable('households') || ! Schema::hasTable('addresses')) return [];

        $query = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->when($barangayId !== null, fn ($builder) => $builder->where('a.barangay_id', $barangayId))
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($builder) => $builder->whereNull('h.deleted_at'));

        $hasAddressLabel = Schema::hasColumn('addresses', 'purok_sitio');
        $hasPurokCatalog = Schema::hasTable('puroks') && Schema::hasColumn('addresses', 'purok_id');
        if (! $hasAddressLabel && ! $hasPurokCatalog) return [];

        if ($hasPurokCatalog) {
            $query->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id');
        }

        $label = match (true) {
            $hasAddressLabel && $hasPurokCatalog => "COALESCE(NULLIF(a.purok_sitio, ''), NULLIF(p.purok_name, ''))",
            $hasAddressLabel => "NULLIF(a.purok_sitio, '')",
            default => "NULLIF(p.purok_name, '')",
        };

        return $query->whereRaw("$label IS NOT NULL")
            ->selectRaw("DISTINCT $label AS purok_name")
            ->orderBy('purok_name')
            ->pluck('purok_name')->all();
    }
}
