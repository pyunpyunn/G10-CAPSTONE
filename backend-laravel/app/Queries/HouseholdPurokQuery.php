<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class HouseholdPurokQuery
{
    /** @return list<string> */
    public function names(): array
    {
        if (! Schema::hasTable('households') || ! Schema::hasTable('addresses')) return [];

        $query = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($builder) => $builder->whereNull('h.deleted_at'));

        if (Schema::hasColumn('addresses', 'purok_sitio')) {
            return $query->whereNotNull('a.purok_sitio')->where('a.purok_sitio', '<>', '')
                ->distinct()->orderBy('a.purok_sitio')->pluck('a.purok_sitio')->all();
        }

        if (! Schema::hasTable('puroks') || ! Schema::hasColumn('addresses', 'purok_id')) return [];

        return $query->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->whereNotNull('p.purok_name')->where('p.purok_name', '<>', '')
            ->distinct()->orderBy('p.purok_name')->pluck('p.purok_name')->all();
    }
}
