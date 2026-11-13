<?php

namespace App\Queries;

use App\Models\ResourceRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerMobileReadQuery
{
    public function resourceRequests(?string $userId, int $limit = 50): Collection
    {
        if (! Schema::hasTable('resource_requests')) return collect();
        $query = ResourceRequest::query();
        return $query->orderByDesc('created_at')->limit($limit)->get()->values();
    }

    public function checkIns(?int $responderId, int $limit = 20): Collection
    {
        if (! Schema::hasTable('responder_check_ins')) return collect();
        $query = DB::table('responder_check_ins');
        if ($responderId) $query->where('responder_id', $responderId);
        return $query->orderByDesc('checked_in_at')->limit($limit)->get()->values();
    }

    public function evacuationCenters(): Collection
    {
        if (! Schema::hasTable('evacuation_centers') || ! Schema::hasColumn('evacuation_centers', 'latitude')) return collect();
        $query = DB::table('evacuation_centers')->whereNotNull('latitude')->whereNotNull('longitude');
        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) $query->whereNull('deleted_at');
        return $query->orderBy('name')->limit(20)->get([
            'evacuation_center_id', 'name', 'latitude', 'longitude', 'capacity', 'current_occupancy', 'status',
        ])->values();
    }
}


