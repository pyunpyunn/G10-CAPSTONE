<?php

namespace App\Queries;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveEventDetailQuery
{
    /** Load one export batch with a fixed number of queries, regardless of event count. */
    public function forEvents(array $eventIds): array
    {
        if ($eventIds === []) return [];

        $weather = DB::table('weather_logs as wl')->whereIn('wl.disaster_id', $eventIds)
            ->whereRaw('wl.weather_log_id = (select w2.weather_log_id from weather_logs as w2 where w2.disaster_id = wl.disaster_id order by w2.observed_at desc, w2.created_at desc, w2.weather_log_id desc limit 1)')
            ->get(['wl.disaster_id', 'wl.condition_name', 'wl.advisory_title', 'wl.wind_speed', 'wl.wind_direction', 'wl.rainfall_mm', 'wl.temperature', 'wl.source_name'])
            ->keyBy('disaster_id');
        $broadcastCounts = DB::table('disaster_broadcasts')->whereIn('disaster_id', $eventIds)
            ->selectRaw('disaster_id, count(*) as total')->groupBy('disaster_id')->pluck('total', 'disaster_id');
        $latestBroadcasts = DB::table('disaster_broadcasts as b')->whereIn('b.disaster_id', $eventIds)
            ->whereRaw('b.broadcast_id = (select b2.broadcast_id from disaster_broadcasts as b2 where b2.disaster_id = b.disaster_id order by b2.sent_at desc, b2.broadcast_id desc limit 1)')
            ->get(['b.disaster_id', 'b.broadcast_title', 'b.sent_at'])->keyBy('disaster_id');
        $householdCounts = DB::table('household_disasters')->whereIn('disaster_id', $eventIds)
            ->selectRaw('disaster_id, count(*) as total')->groupBy('disaster_id')->pluck('total', 'disaster_id');
        $categoryCounts = $this->categoryCounts($eventIds);
        $puroks = DB::table('household_disasters as hd')->join('households as h', 'h.household_id', '=', 'hd.household_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')->whereIn('hd.disaster_id', $eventIds)
            ->whereNotNull('a.purok_sitio')->where('a.purok_sitio', '<>', '')
            ->distinct()->orderBy('hd.disaster_id')->orderBy('a.purok_sitio')
            ->get(['hd.disaster_id', 'a.purok_sitio'])->groupBy('disaster_id');
        $archives = DB::table('incident_archives as ia')->whereIn('ia.disaster_id', $eventIds)
            ->whereRaw('ia.archive_id = (select ia2.archive_id from incident_archives as ia2 where ia2.disaster_id = ia.disaster_id order by ia2.archived_at desc, ia2.archive_id desc limit 1)')
            ->get(['ia.disaster_id', 'ia.archive_note'])->keyBy('disaster_id');

        $result = [];
        foreach ($eventIds as $eventId) {
            $scope = $puroks->get($eventId);
            $result[$eventId] = [
                'weather' => $this->weatherSummary($weather->get($eventId)),
                'broadcast_count' => (int) ($broadcastCounts->get($eventId) ?? 0),
                'latest_broadcast' => $latestBroadcasts->get($eventId),
                'household_scope' => (int) ($householdCounts->get($eventId) ?? 0),
                'purok_scope' => $scope ? $scope->pluck('purok_sitio')->implode(', ') : 'Barangay scope',
                'archive' => $archives->get($eventId),
                'category_counts' => $categoryCounts[$eventId] ?? [],
            ];
        }

        return $result;
    }

    private function categoryCounts(array $eventIds): array
    {
        $counts = [];
        $savedIds = [];
        if (\App\Support\RequestSchema::hasTable('incident_archives')) {
            $groupManager = app(\App\Services\Archive\ArchiveGroupManager::class);
            foreach (['field-reports', 'household-status-logs', 'dispatch-logs', 'resource-requests', 'situation-reports'] as $category) $savedIds[$category] = $groupManager->savedRecordIds($category);
        }
        $fields = \App\Support\RequestSchema::hasTable('household_status_logs')
            ? DB::table('household_status_logs')->whereIn('disaster_id', $eventIds)->selectRaw('disaster_id, count(*) as total')->where('notes', 'like', '%"report_type":"rescuer_field_report"%')->when(($savedIds['field-reports'] ?? []) !== [], fn ($q) => $q->whereNotIn('status_log_id', $savedIds['field-reports']))->groupBy('disaster_id')->pluck('total', 'disaster_id')
            : collect();
        $households = \App\Support\RequestSchema::hasTable('household_status_logs')
            ? DB::table('household_status_logs')->whereIn('disaster_id', $eventIds)->selectRaw('disaster_id, count(*) as total')->when(($savedIds['household-status-logs'] ?? []) !== [], fn ($q) => $q->whereNotIn('status_log_id', $savedIds['household-status-logs']))->groupBy('disaster_id')->pluck('total', 'disaster_id')
            : collect();
        $dispatch = \App\Support\RequestSchema::hasTable('responder_assignments')
            ? DB::table('responder_assignments')->whereIn('disaster_id', $eventIds)->selectRaw('disaster_id, count(*) as total')->when(($savedIds['dispatch-logs'] ?? []) !== [], fn ($q) => $q->whereNotIn('assignment_id', $savedIds['dispatch-logs']))->groupBy('disaster_id')->pluck('total', 'disaster_id')
            : collect();
        $resource = \App\Support\RequestSchema::hasTable('resource_requests')
            ? DB::table('resource_requests')->whereIn('source_reference', $eventIds)->selectRaw('source_reference, count(*) as total')->when(($savedIds['resource-requests'] ?? []) !== [], fn ($q) => $q->whereNotIn('request_id', $savedIds['resource-requests']))->groupBy('source_reference')->pluck('total', 'source_reference')
            : collect();
        $sitrep = \App\Support\RequestSchema::hasTable('situation_reports')
            ? DB::table('situation_reports')->whereIn('disaster_id', $eventIds)->selectRaw('disaster_id, count(*) as total')->when(($savedIds['situation-reports'] ?? []) !== [], fn ($q) => $q->whereNotIn('sit_rep_id', $savedIds['situation-reports']))->groupBy('disaster_id')->pluck('total', 'disaster_id')
            : collect();
        $radio = collect();
        if (\App\Support\RequestSchema::hasTable('responder_communication_logs')) {
            $radioIds = DB::table('responder_communication_logs')->whereIn('disaster_id', $eventIds)->select('disaster_id')->distinct()->pluck('disaster_id');
            foreach ($radioIds as $eventId) {
                $radio->put((string) $eventId, $this->radioTransmissionCount(Request::create('/', 'GET', ['event_id' => $eventId])));
            }
        }
        foreach ($eventIds as $eventId) {
            $counts[$eventId] = [
                'disaster-events' => 1,
                'field-reports' => (int) ($fields->get($eventId) ?? 0),
                'household-status-logs' => (int) ($households->get($eventId) ?? 0),
                'dispatch-logs' => (int) ($dispatch->get($eventId) ?? 0),
                'radio-communication-logs' => (int) ($radio->get((string) $eventId) ?? 0),
                'resource-requests' => (int) ($resource->get($eventId) ?? 0),
                'situation-reports' => (int) ($sitrep->get($eventId) ?? 0),
            ];
        }
        return $counts;
    }

    private function radioTransmissionCount(Request $request): int
    {
        $query = DB::table('responder_communication_logs as rcl');
        $id = $request->query('event_id');
        if ($id !== null && $id !== '' && $id !== 'all') $query->where('rcl.disaster_id', $id);
        $groupExpression = match (DB::connection()->getDriverName()) {
            'mysql' => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(rcl.message) THEN rcl.message ELSE '{}' END, '$.transmission_id')), 'null'), CONCAT('log:', rcl.communication_id))",
            'pgsql' => "COALESCE(NULLIF(rcl.message::jsonb ->> 'transmission_id', ''), 'log:' || rcl.communication_id::text)",
            default => "COALESCE(NULLIF(CASE WHEN json_valid(rcl.message) THEN json_extract(rcl.message, '$.transmission_id') END, ''), 'log:' || rcl.communication_id)",
        };
        $query->selectRaw($groupExpression.' as archive_group_key')
            ->selectRaw('MAX(rcl.timestamp) as latest_timestamp')
            ->groupByRaw($groupExpression);
        $savedIds = app(\App\Services\Archive\ArchiveGroupManager::class)->savedRecordIds('radio-communication-logs');
        if ($savedIds !== []) $query->whereNotIn('rcl.communication_id', $savedIds);
        return (int) DB::query()->fromSub($query, 'archive_radio_groups')->count();
    }

    public function forEvent(string $eventId): array
    {
        return [
            'weather' => $this->latestWeather($eventId),
            'broadcast_count' => DB::table('disaster_broadcasts')->where('disaster_id', $eventId)->count(),
            'latest_broadcast' => DB::table('disaster_broadcasts')->where('disaster_id', $eventId)->orderByDesc('sent_at')->first(),
            'household_scope' => DB::table('household_disasters')->where('disaster_id', $eventId)->count(),
            'purok_scope' => $this->eventPurokScope($eventId),
            'archive' => DB::table('incident_archives')->where('disaster_id', $eventId)->orderByDesc('archived_at')->first(),
        ];
    }

    private function latestWeather(string $eventId): array
    {
        $weather = DB::table('weather_logs')->where('disaster_id', $eventId)->orderByDesc('observed_at')->orderByDesc('created_at')->first();
        return $this->weatherSummary($weather);
    }

    private function weatherSummary(?object $weather): array
    {
        if (! $weather) return ['condition' => null, 'meta' => null];
        $parts = [];
        if ($weather->wind_speed !== null) $parts[] = 'Wind '.$weather->wind_speed.' km/h '.($weather->wind_direction ?: '');
        if ($weather->rainfall_mm !== null) $parts[] = 'Rainfall '.$weather->rainfall_mm.' mm';
        if ($weather->temperature !== null) $parts[] = 'Temp '.$weather->temperature.' C';
        return ['condition' => $weather->condition_name ?: $weather->advisory_title, 'meta' => trim(implode(' - ', $parts)) ?: ($weather->source_name ?: 'Weather snapshot saved')];
    }

    private function eventPurokScope(string $eventId): string
    {
        $puroks = DB::table('household_disasters as hd')->join('households as h', 'h.household_id', '=', 'hd.household_id')->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')->where('hd.disaster_id', $eventId)->whereNotNull('a.purok_sitio')->where('a.purok_sitio', '<>', '')->select('a.purok_sitio')->distinct()->orderBy('a.purok_sitio')->pluck('a.purok_sitio')->values()->all();
        return empty($puroks) ? 'Barangay scope' : implode(', ', $puroks);
    }
}


