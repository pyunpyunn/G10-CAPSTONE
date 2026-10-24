<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HouseholdAddressQuery
{
    public function forHouseholds(array $ids): Collection
    {
        if (! $ids || ! Schema::hasTable('addresses') || ! Schema::hasColumn('households', 'address_id')) return collect();
        $query = DB::table('households as h')->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id');
        $columns = ['h.household_id'];
        if (Schema::hasTable('puroks') && Schema::hasColumn('addresses', 'purok_id')) {
            $query->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id');
            $columns[] = 'p.purok_name';
            if (Schema::hasTable('sitios')) {
                $sitioId = Schema::hasColumn('addresses', 'sitio_id') ? DB::raw('COALESCE(p.sitio_id, a.sitio_id)') : 'p.sitio_id';
                $query->leftJoin('sitios as s', 's.sitio_id', '=', $sitioId);
                $columns[] = 's.sitio_name';
            }
        } elseif (Schema::hasTable('sitios') && Schema::hasColumn('addresses', 'sitio_id')) {
            $query->leftJoin('sitios as s', 's.sitio_id', '=', 'a.sitio_id');
            $columns[] = 's.sitio_name';
        }

        return $query->whereIn('h.household_id', $ids)->get($columns)->mapWithKeys(fn ($row) => [
            $row->household_id => implode(', ', array_filter([$row->purok_name ?? null, $row->sitio_name ?? null])) ?: null,
        ]);
    }
}
