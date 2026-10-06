<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class AreaCoverageQuery
{
    public function label(): string
    {
        if (! Schema::hasTable('households') || ! Schema::hasTable('addresses') || ! Schema::hasTable('barangays')) {
            return 'Shared database records';
        }

        $coverage = DB::table('households as h')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->join('barangays as b', 'b.barangay_id', '=', 'a.barangay_id')
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
            ->selectRaw('COUNT(DISTINCT b.barangay_id) as barangay_count, MIN(b.barangay_name) as barangay_name')
            ->first();

        $count = (int) ($coverage->barangay_count ?? 0);
        if ($count === 1) return 'Barangay '.$coverage->barangay_name;
        if ($count > 1) return $count.' barangays in the shared database';
        return 'Shared database records';
    }
}
