<?php

namespace App\Queries;

use App\Models\ResourceRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerMobileReadQuery
{
    public function resourceRequests(?string $userId, int $limit): Collection
    {
        if (! Schema::hasTable('resource_requests')) return collect();
        $query = ResourceRequest::query();
        if (Schema::hasColumn('resource_requests', 'request_source')) $query->where('request_source', 'rescuer_mobile');
        if ($userId && Schema::hasColumn('resource_requests', 'handled_by')) $query->where('handled_by', $userId);
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


