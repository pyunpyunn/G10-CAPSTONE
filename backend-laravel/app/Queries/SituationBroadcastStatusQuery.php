<?php

namespace App\Queries;

use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class SituationBroadcastStatusQuery
{
    public function byPurok(string $eventId, ?string $sitioId = null): array
    {
        if (! Schema::hasTable('disaster_broadcasts')) {
            return ['statuses' => [], 'counts' => []];
        }

        $statusKeys = collect();
        $broadcastQuery = DB::table('disaster_broadcasts')->where('disaster_id', $eventId);
        $columns = [];
        foreach (['allowed_statuses_json', 'allowed_statuses'] as $column) {
            if (Schema::hasColumn('disaster_broadcasts', $column)) $columns[] = $column;
        }
        if ($columns === []) return ['statuses' => [], 'counts' => []];
        foreach ($broadcastQuery->get($columns) as $broadcast) {
            $keys = json_decode((string) ($broadcast->allowed_statuses_json ?? ''), true);
            if (! is_array($keys)) $keys = [];
            $metadata = json_decode((string) ($broadcast->allowed_statuses ?? ''), true);
            if (is_array($metadata)) $keys = [...$keys, ...($metadata['statuses'] ?? [])];
            $statusKeys = $statusKeys->merge($keys);
        }
        $statusKeys = $statusKeys->filter(fn ($key) => is_string($key) && $key !== '')->unique()->values();
        if ($statusKeys->isEmpty() || ! Schema::hasTable('household_statuses')) {
            return ['statuses' => [], 'counts' => []];
        }

        $labelColumn = Schema::hasColumn('household_statuses', 'status_label') ? 'status_label'
            : (Schema::hasColumn('household_statuses', 'label') ? 'label' : 'status_key');
        $statuses = DB::table('household_statuses')->whereIn('status_key', $statusKeys)
            ->get(['status_key', $labelColumn.' as label'])
            ->keyBy('status_key');
        $statusKeys = $statusKeys->filter(fn ($key) => $statuses->has($key))->values();
        if ($statusKeys->isEmpty()) return ['statuses' => [], 'counts' => []];

        $query = DB::table('household_status_logs as hsl')
            ->joinSub(HouseholdEventStatusQuery::latestReports($eventId), 'latest', 'latest.status_log_id', '=', 'hsl.status_log_id')
            ->join('household_statuses as hs', 'hs.status_id', '=', 'latest.status_id')
            ->join('households as h', 'h.household_id', '=', 'hsl.household_id')
            ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->whereIn('h.household_id', HouseholdEligibilityQuery::ids())
            ->whereIn('hs.status_key', $statusKeys)
            ->when($sitioId !== null, fn ($q) => $q->where('s.sitio_id', $sitioId));

        $counts = $query->select('s.sitio_id', 's.sitio_name', 'p.purok_id', 'p.purok_name', 'hs.status_key')
            ->selectRaw('COUNT(DISTINCT h.household_id) as households')
            ->groupBy('p.purok_id', 'hs.status_key')
            ->get()
            ->map(fn (object $row): array => [
                'sitio_id' => (string) $row->sitio_id,
                'sitio' => $row->sitio_name,
                'purok_id' => (string) $row->purok_id,
                'purok' => $row->purok_name,
                'status_key' => $row->status_key,
                'label' => $statuses->get($row->status_key)->label,
                'households' => (int) $row->households,
            ])->all();

        return [
            'statuses' => $statusKeys->map(fn (string $key): array => [
                'key' => $key, 'label' => $statuses->get($key)->label,
            ])->all(),
            'counts' => $counts,
        ];
    }
}
