<?php

namespace App\Queries;

use App\Presenters\HouseholdMobilePresenter;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;

class HouseholdMobileReadQuery
{
    public function __construct(private HouseholdMobilePresenter $presenter) {}

    public function householdRecord(string $householdId): ?object
    {
        if (! Schema::hasTable('households')) {
            return null;
        }

        $query = DB::table('households as h')
            ->where('h.household_id', $householdId);

        if (Schema::hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        $columns = [
            $this->optionalColumnSelect('households', 'household_id', 'household_id', 'h'),
            $this->optionalColumnSelect('households', 'household_code', 'household_code', 'h'),
            $this->optionalColumnSelect('households', 'household_name', 'household_name', 'h'),
            $this->optionalColumnSelect('households', 'household_number', 'household_number', 'h'),
            $this->optionalColumnSelect('households', 'contact_number', 'contact_number', 'h'),
            $this->optionalColumnSelect('households', 'emergency_contact', 'emergency_contact', 'h'),
            $this->optionalColumnSelect('households', 'member_count', 'member_count', 'h'),
        ];

        if (Schema::hasTable('addresses') && Schema::hasColumn('households', 'address_id') && Schema::hasColumn('addresses', 'address_id')) {
            $query->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id');
            $columns[] = $this->optionalColumnSelect('addresses', 'full_address', 'full_address', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'purok_sitio', 'purok_sitio', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'barangay_name', 'barangay_name', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'city_municipality', 'city_municipality', 'a');
            $columns[] = $this->optionalColumnSelect('addresses', 'province', 'province', 'a');
        } else {
            $columns[] = DB::raw('NULL as full_address');
            $columns[] = DB::raw('NULL as purok_sitio');
            $columns[] = DB::raw('NULL as barangay_name');
            $columns[] = DB::raw('NULL as city_municipality');
            $columns[] = DB::raw('NULL as province');
        }

        return $query->first($columns);
    }

    public function activeEvent(): ?array
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DB::table('disaster_events as de');
        $columns = ['de.event_id', 'de.name', 'de.started_at'];

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }

        if (Schema::hasColumn('disaster_events', 'ended_at')) {
            $query->whereNull('de.ended_at');
        }

        if (Schema::hasTable('disaster_types')) {
            $query->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id');
            $columns[] = 'dt.type_name';
        } else {
            $columns[] = DB::raw('NULL as type_name');
        }

        if (Schema::hasTable('severity_levels')) {
            $query->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id');
            $columns[] = 'sl.severity_key';
            $columns[] = 'sl.severity_label';
        } else {
            $columns[] = DB::raw('NULL as severity_key');
            $columns[] = DB::raw('NULL as severity_label');
        }

        $event = $query->orderByDesc('de.started_at')->first($columns);

        if (! $event) {
            return null;
        }

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity_key' => $event->severity_key ?? 'monitoring',
            'severity' => $event->severity_label ?? 'Monitoring',
            'started_at' => $event->started_at,
            'message' => 'Follow HQ broadcasts and update your household status when needed.',
            'additional_info' => 'Keep your evacuation QR ready and keep mobile devices charged.',
        ];
    }

    public function devices(string $householdId)
    {
        if (! Schema::hasTable('device_tokens')) {
            return collect();
        }

        $query = DB::table('device_tokens as dt')
            ->where('dt.household_id', $householdId);

        $columns = [
            $this->optionalColumnSelect('device_tokens', 'id', 'id', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'household_id', 'household_id', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'member_id', 'member_id', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'device_uuid', 'device_uuid', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'device_name', 'device_name', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'platform', 'platform', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'battery_level', 'battery_level', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'signal_strength', 'signal_strength', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_latitude', 'last_latitude', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_longitude', 'last_longitude', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_location_label', 'last_location_label', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_location_accuracy_m', 'last_location_accuracy_m', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_location_at', 'last_location_at', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'last_seen_at', 'last_seen_at', 'dt'),
            $this->optionalColumnSelect('device_tokens', 'is_active', 'is_active', 'dt'),
        ];

        $memberJoinColumn = null;

        if (Schema::hasTable('household_members')) {
            $memberJoinColumn = Schema::hasColumn('household_members', 'member_id')
                ? 'member_id'
                : (Schema::hasColumn('household_members', 'id') ? 'id' : null);
        }

        if (Schema::hasTable('household_members') && Schema::hasColumn('device_tokens', 'member_id') && $memberJoinColumn) {
            $query->leftJoin('household_members as hm', "hm.{$memberJoinColumn}", '=', 'dt.member_id');
            $columns[] = $this->firstExistingColumnSelect('household_members', ['name', 'full_name'], 'member_name', 'hm');
            $columns[] = $this->optionalColumnSelect('household_members', 'first_name', 'first_name', 'hm');
            $columns[] = $this->optionalColumnSelect('household_members', 'last_name', 'last_name', 'hm');
            $columns[] = $this->firstExistingColumnSelect('household_members', ['relation', 'relationship'], 'relation', 'hm');
        } else {
            $columns[] = DB::raw('NULL as member_name');
            $columns[] = DB::raw('NULL as first_name');
            $columns[] = DB::raw('NULL as last_name');
            $columns[] = DB::raw('NULL as relation');
        }

        $orderColumn = Schema::hasColumn('device_tokens', 'last_seen_at') ? 'dt.last_seen_at' : 'dt.id';

        return $query
            ->orderByDesc($orderColumn)
            ->get($columns)
            ->map(fn (object $device): array => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'member_id' => $device->member_id,
                'member_name' => $this->presenter->personName($device->member_name, $device->first_name, $device->last_name, 'Household user'),
                'device_name' => $device->device_name ?: 'Household mobile',
                'platform' => $this->presenter->label($device->platform ?: 'mobile'),
                'battery_level' => $device->battery_level,
                'signal_strength' => $device->signal_strength,
                'last_location_label' => $device->last_location_label ?: 'No location yet',
                'latitude' => $device->last_latitude,
                'longitude' => $device->last_longitude,
                'last_seen_at' => $device->last_seen_at,
                'last_seen_label' => $this->presenter->dateLabel($device->last_seen_at),
                'is_active' => (bool) ($device->is_active ?? true),
            ])
            ->values();
    }

    public function geotag(string $householdId): ?array
    {
        if (! Schema::hasTable('geotagged_locations')) {
            return null;
        }

        $columns = [
            $this->optionalColumnSelect('geotagged_locations', 'location_id', 'location_id'),
            $this->optionalColumnSelect('geotagged_locations', 'household_id', 'household_id'),
            $this->optionalColumnSelect('geotagged_locations', 'latitude', 'latitude'),
            $this->optionalColumnSelect('geotagged_locations', 'longitude', 'longitude'),
            $this->optionalColumnSelect('geotagged_locations', 'location_label', 'location_label'),
            $this->optionalColumnSelect('geotagged_locations', 'accuracy_m', 'accuracy_m'),
            $this->optionalColumnSelect('geotagged_locations', 'geotag_source', 'geotag_source'),
            $this->optionalColumnSelect('geotagged_locations', 'is_verified', 'is_verified'),
            $this->optionalColumnSelect('geotagged_locations', 'created_at', 'created_at'),
            $this->optionalColumnSelect('geotagged_locations', 'updated_at', 'updated_at'),
        ];

        $query = DB::table('geotagged_locations')
            ->where('household_id', $householdId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (Schema::hasColumn('geotagged_locations', 'updated_at')) {
            $query->orderByDesc('updated_at');
        } elseif (Schema::hasColumn('geotagged_locations', 'created_at')) {
            $query->orderByDesc('created_at');
        } elseif (Schema::hasColumn('geotagged_locations', 'location_id')) {
            $query->orderByDesc('location_id');
        }

        $row = $query->first($columns);

        if (! $row || ! $row->latitude || ! $row->longitude) {
            return null;
        }

        return [
            'location_id' => $row->location_id,
            'household_id' => $row->household_id,
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'location_label' => $row->location_label ?: 'Household geotag',
            'accuracy_m' => $row->accuracy_m !== null ? (float) $row->accuracy_m : null,
            'geotag_source' => $row->geotag_source ?: 'household_mobile',
            'is_verified' => (bool) ($row->is_verified ?? false),
            'updated_at' => $row->updated_at ?: $row->created_at,
            'updated_label' => $this->presenter->dateLabel($row->updated_at ?: $row->created_at),
        ];
    }

    public function evacuationCenters(?string $eventId, ?float $originLat = null, ?float $originLng = null): array
    {
        if (
            ! Schema::hasTable('evacuation_centers')
            || ! Schema::hasColumn('evacuation_centers', 'latitude')
            || ! Schema::hasColumn('evacuation_centers', 'longitude')
        ) {
            return [];
        }

        $query = DB::table('evacuation_centers')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('evacuation_centers', 'current_event_id') && $eventId) {
            $query->where(function ($eventQuery) use ($eventId): void {
                $eventQuery
                    ->whereNull('current_event_id')
                    ->orWhere('current_event_id', $eventId);
            });
        }

        $columns = [
            $this->optionalColumnSelect('evacuation_centers', 'evacuation_center_id', 'evacuation_center_id'),
            $this->optionalColumnSelect('evacuation_centers', 'name', 'name'),
            $this->optionalColumnSelect('evacuation_centers', 'latitude', 'latitude'),
            $this->optionalColumnSelect('evacuation_centers', 'longitude', 'longitude'),
            $this->optionalColumnSelect('evacuation_centers', 'capacity', 'capacity'),
            $this->optionalColumnSelect('evacuation_centers', 'current_occupancy', 'current_occupancy'),
            $this->optionalColumnSelect('evacuation_centers', 'status', 'status'),
            $this->optionalColumnSelect('evacuation_centers', 'center_type', 'center_type'),
            $this->optionalColumnSelect('evacuation_centers', 'osm_address', 'osm_address'),
            $this->optionalColumnSelect('evacuation_centers', 'contact_number', 'contact_number'),
        ];

        $centers = $query
            ->get($columns)
            ->map(function (object $center) use ($originLat, $originLng): array {
                $capacity = $center->capacity !== null ? (int) $center->capacity : null;
                $occupancy = $center->current_occupancy !== null ? (int) $center->current_occupancy : null;
                $latitude = (float) $center->latitude;
                $longitude = (float) $center->longitude;
                $vacancy = $capacity !== null && $occupancy !== null ? max($capacity - $occupancy, 0) : null;
                $status = strtolower(trim($center->status ?: 'active'));
                $distanceKm = ($originLat !== null && $originLng !== null)
                    ? $this->distanceKm($originLat, $originLng, $latitude, $longitude)
                    : null;

                return [
                    'evacuation_center_id' => $center->evacuation_center_id,
                    'name' => $center->name ?: 'Evacuation center',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'distance_km' => $distanceKm,
                    'capacity' => $capacity,
                    'current_occupancy' => $occupancy,
                    'vacancy' => $vacancy,
                    'route_available' => $vacancy !== null && $vacancy > 0
                        && in_array($status, ['active', 'open', 'available'], true)
                        && is_numeric($center->latitude) && is_numeric($center->longitude)
                        && abs($latitude) <= 90 && abs($longitude) <= 180,
                    'status' => $center->status ?: 'active',
                    'center_type' => $center->center_type ?: 'Evacuation center',
                    'address' => $center->osm_address,
                    'contact_number' => $center->contact_number,
                ];
            })
            ->values()
            ->all();

        if ($originLat !== null && $originLng !== null) {
            usort($centers, fn (array $a, array $b): int => ($a['distance_km'] ?? PHP_FLOAT_MAX) <=> ($b['distance_km'] ?? PHP_FLOAT_MAX));
        } else {
            usort($centers, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        }

        return $centers;
    }

    public function recentAlerts(?string $eventId): array
    {
        if (! $eventId || ! Schema::hasTable('disaster_broadcasts')) {
            return [];
        }

        return DB::table('disaster_broadcasts')
            ->where('disaster_id', $eventId)
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get([
                'broadcast_id',
                'broadcast_title',
                'message',
                'scope_type',
                'sent_at',
                'created_at',
            ])
            ->map(fn (object $row): array => [
                'broadcast_id' => $row->broadcast_id,
                'title' => $row->broadcast_title ?: 'Disaster alert',
                'message' => $row->message,
                'scope_type' => $row->scope_type,
                'sent_at' => $row->sent_at ? date('M d, Y g:i A', strtotime($row->sent_at)) : null,
            ])
            ->values()
            ->all();
    }

    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a))), 2);
    }

    public function members(string $householdId, $devices, ?string $eventId = null)
    {
        $devicesByMember = collect($devices)
            ->filter(fn (array $device): bool => ! empty($device['member_id']))
            ->groupBy('member_id')
            ->map(fn ($memberDevices) => $memberDevices->first());
        $statusesByMember = $this->memberStatusRows($householdId, $eventId);

        if (! Schema::hasTable('household_members')) {
            return collect();
        }

        $memberColumns = [
            $this->firstExistingColumnSelect('household_members', ['member_id', 'id'], 'member_id'),
            $this->firstExistingColumnSelect('household_members', ['name', 'full_name'], 'name'),
            $this->optionalColumnSelect('household_members', 'first_name', 'first_name'),
            $this->optionalColumnSelect('household_members', 'middle_name', 'middle_name'),
            $this->optionalColumnSelect('household_members', 'last_name', 'last_name'),
            $this->firstExistingColumnSelect('household_members', ['relation', 'relationship'], 'relation'),
            $this->optionalColumnSelect('household_members', 'relationship_id', 'relationship_id'),
            $this->optionalColumnSelect('household_members', 'age', 'age'),
            $this->optionalColumnSelect('household_members', 'birth_date', 'birth_date'),
            $this->firstExistingColumnSelect('household_members', ['gender', 'sex'], 'gender'),
            $this->optionalColumnSelect('household_members', 'special_needs', 'special_needs'),
        ];

        $membersQuery = DB::table('household_members')
            ->where('household_id', $householdId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));

        if (Schema::hasColumn('household_members', 'name')) {
            $membersQuery->orderBy('name');
        } elseif (Schema::hasColumn('household_members', 'full_name')) {
            $membersQuery->orderBy('full_name');
        }

        $members = $membersQuery
            ->get($memberColumns)
            ->map(function (object $member) use ($devicesByMember, $statusesByMember): array {
                $device = $devicesByMember->get($member->member_id);
                $memberStatus = $statusesByMember[(string) $member->member_id] ?? null;

                return [
                    'member_id' => $member->member_id,
                    'name' => $member->name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                    'first_name' => $member->first_name ?? null,
                    'middle_name' => $member->middle_name ?? null,
                    'last_name' => $member->last_name ?? null,
                    'relationship' => $member->relation ?: 'Member',
                    'relationship_id' => $member->relationship_id ?? null,
                    'age' => $member->age ?? null,
                    'birth_date' => $member->birth_date ?? null,
                    'gender' => $member->gender ?? null,
                    'special_needs' => $member->special_needs ?? null,
                    'device' => $device ?: null,
                    'current_status' => $memberStatus,
                ];
            })
            ->values();

        return $members;
    }

    public function memberStatusRows(string $householdId, ?string $eventId): array
    {
        if (! Schema::hasTable('household_status_logs')) {
            return [];
        }

        $query = DB::table('household_status_logs as hsl')
            ->where('hsl.household_id', $householdId);

        if ($eventId && Schema::hasColumn('household_status_logs', 'disaster_id')) {
            $query->where('hsl.disaster_id', $eventId);
        }

        if (Schema::hasColumn('household_status_logs', 'source')) {
            $query->where('hsl.source', 'household_member_mobile');
        }

        $columns = [
            $this->optionalColumnSelect('household_status_logs', 'status_log_id', 'status_log_id', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'notes', 'notes', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'submitted_at', 'submitted_at', 'hsl'),
        ];

        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id');
            $columns[] = 'hs.status_id';
            $columns[] = 'hs.status_key';
            $columns[] = $this->householdStatusLabelSelect('hs', 'status_label');
        } else {
            $columns[] = DB::raw('NULL as status_id');
            $columns[] = DB::raw('NULL as status_key');
            $columns[] = DB::raw('NULL as status_label');
        }

        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
            ? 'hsl.submitted_at'
            : 'hsl.status_log_id';

        return $query
            ->orderByDesc($orderColumn)
            ->limit(200)
            ->get($columns)
            ->reduce(function (array $statuses, object $log): array {
                $notes = $this->decodeJson($log->notes ?? null);
                $memberId = $notes['member_id'] ?? null;

                if (! $memberId || isset($statuses[(string) $memberId])) {
                    return $statuses;
                }

                $displayStatusKey = $this->presenter->displayStatusKey($log->status_key, $notes);

                $statuses[(string) $memberId] = [
                    'status_log_id' => $log->status_log_id,
                    'member_id' => $memberId,
                    'member_name' => $notes['member_name'] ?? null,
                    'status_id' => $log->status_id,
                    'status_key' => $displayStatusKey,
                    'status_label' => $this->presenter->displayStatusLabel($displayStatusKey, $log->status_label),
                    'notes' => $notes['member_notes'] ?? null,
                    'submitted_at' => $log->submitted_at,
                    'submitted_label' => $this->presenter->dateLabel($log->submitted_at),
                ];

                return $statuses;
            }, []);
    }

    public function currentStatus(string $householdId, ?string $eventId): ?array
    {
        if (! $eventId || ! Schema::hasTable('household_disasters')) {
            return null;
        }

        if (Schema::hasColumn('household_disasters', 'current_status_id')) {
            $statusColumn = 'hd.current_status_id';
        } elseif (Schema::hasColumn('household_disasters', 'initial_status_id')) {
            $statusColumn = 'hd.initial_status_id';
        } else {
            return null;
        }

        $query = DB::table('household_disasters as hd')
            ->where('hd.disaster_id', $eventId)
            ->where('hd.household_id', $householdId);

        $columns = [
            $this->optionalColumnSelect('household_disasters', 'household_id', 'household_id', 'hd'),
            $this->optionalColumnSelect('household_disasters', 'last_status_notes', 'last_status_notes', 'hd'),
            $this->optionalColumnSelect('household_disasters', 'last_battery_level', 'last_battery_level', 'hd'),
            $this->optionalColumnSelect('household_disasters', 'last_reported_at', 'last_reported_at', 'hd'),
        ];

        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', $statusColumn);
            $columns = array_merge($columns, [
                'hs.status_id',
                'hs.status_key',
                $this->householdStatusLabelSelect('hs', 'status_label'),
            ]);
        } else {
            $columns[] = DB::raw('NULL as status_id');
            $columns[] = DB::raw('NULL as status_key');
            $columns[] = DB::raw('NULL as status_label');
        }

        $row = $query->first($columns);

        if (! $row || ! $row->status_id) {
            return null;
        }

        $notes = $this->decodeJson($row->last_status_notes ?? null);
        $displayStatusKey = $this->presenter->displayStatusKey($row->status_key, $notes);

        return [
            'status_id' => $row->status_id,
            'status_key' => $displayStatusKey,
            'status_label' => $this->presenter->displayStatusLabel($displayStatusKey, $row->status_label),
            'notes' => $notes['user_notes'] ?? $row->last_status_notes,
            'battery_level' => $row->last_battery_level,
            'last_saved_at' => $row->last_reported_at,
            'last_saved_label' => $this->presenter->dateLabel($row->last_reported_at),
        ];
    }

    public function statusHistoryRows(string $householdId, ?string $eventId): array
    {
        if (! Schema::hasTable('household_status_logs')) {
            return [];
        }

        $query = DB::table('household_status_logs as hsl')
            ->where('hsl.household_id', $householdId)
            ->when($eventId && Schema::hasColumn('household_status_logs', 'disaster_id'), fn ($query) => $query->where('hsl.disaster_id', $eventId));

        if (Schema::hasColumn('household_status_logs', 'source')) {
            $query->where(function ($sourceQuery): void {
                $sourceQuery
                    ->whereNull('hsl.source')
                    ->orWhere('hsl.source', '!=', 'household_member_mobile');
            });
        }

        $columns = [
            $this->optionalColumnSelect('household_status_logs', 'status_log_id', 'status_log_id', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'location_label', 'location_label', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'battery_level', 'battery_level', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'notes', 'notes', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'submitted_at', 'submitted_at', 'hsl'),
        ];

        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id');
            $columns[] = 'hs.status_key';
            $columns[] = $this->householdStatusLabelSelect('hs', 'status_label');
        } else {
            $columns[] = DB::raw('NULL as status_key');
            $columns[] = DB::raw('NULL as status_label');
        }

        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
            ? 'hsl.submitted_at'
            : 'hsl.status_log_id';

        return $query
            ->orderByDesc($orderColumn)
            ->limit(30)
            ->get($columns)
            ->map(function (object $log): array {
                $notes = $this->decodeJson($log->notes ?? null);
                $displayStatusKey = $this->presenter->displayStatusKey($log->status_key, $notes);

                return [
                    'status_log_id' => $log->status_log_id,
                    'status_key' => $displayStatusKey,
                    'status_label' => $this->presenter->displayStatusLabel($displayStatusKey, $log->status_label),
                    'location_label' => $log->location_label,
                    'battery_level' => $log->battery_level,
                    'notes' => $notes['user_notes'] ?? $log->notes,
                    'submitted_at' => $log->submitted_at,
                    'submitted_label' => $this->presenter->dateLabel($log->submitted_at),
                ];
            })
            ->values()
            ->all();
    }

    public function trustedRows(string $householdId): array
    {
        if (! Schema::hasTable('trusted_households')) {
            return [];
        }

        $activeEvent = $this->activeEvent();

        return DB::table('trusted_households as th')
            ->leftJoin('households as h', 'h.household_id', '=', 'th.trusted_household_id')
            ->where('th.requesting_household_id', $householdId)
            ->orderByDesc('th.created_at')
            ->get([
                'th.connection_id',
                'th.trusted_household_id',
                'th.reason',
                'th.validation_status',
                'th.member_relationships',
                'th.created_at',
                'h.household_name',
                'h.household_code',
            ])
            ->map(function (object $row) use ($activeEvent): array {
                $trustedHouseholdId = (string) $row->trusted_household_id;
                $isValidated = in_array(strtolower((string) ($row->validation_status ?? 'pending')), ['validated', 'approved'], true);
                $devices = $isValidated ? $this->devices($trustedHouseholdId) : collect();

                return [
                    'connection_id' => $row->connection_id,
                    'household_id' => $trustedHouseholdId,
                    'family_name' => $this->presenter->familyName($row->household_name ?? $row->household_code ?? $row->trusted_household_id),
                    'reason' => $row->reason,
                    'validation_status' => $row->validation_status ?? 'pending',
                    'member_relationships' => $this->decodeJson($row->member_relationships),
                    'current_status' => $isValidated ? $this->currentStatus($trustedHouseholdId, $activeEvent['event_id'] ?? null) : null,
                    'members' => $isValidated ? $this->members($trustedHouseholdId, $devices, null, $activeEvent['event_id'] ?? null)->values() : [],
                    'devices' => $devices->values(),
                    'created_at' => $row->created_at,
                ];
            })
            ->values()
            ->all();
    }

    public function incomingTrustedRows(string $householdId): array
    {
        if (! Schema::hasTable('trusted_households')) {
            return [];
        }

        return DB::table('trusted_households as th')
            ->leftJoin('households as h', 'h.household_id', '=', 'th.requesting_household_id')
            ->where('th.trusted_household_id', $householdId)
            ->where('th.validation_status', 'pending')
            ->orderByDesc('th.created_at')
            ->get([
                'th.connection_id',
                'th.requesting_household_id',
                'th.reason',
                'th.member_relationships',
                'th.created_at',
                'h.household_name',
                'h.household_code',
            ])
            ->map(fn (object $row): array => [
                'connection_id' => $row->connection_id,
                'requesting_household_id' => $row->requesting_household_id,
                'family_name' => $this->presenter->familyName($row->household_name ?? $row->household_code ?? $row->requesting_household_id),
                'reason' => $row->reason,
                'member_relationships' => $this->decodeJson($row->member_relationships),
                'created_at' => $row->created_at,
                'created_label' => $this->presenter->dateLabel($row->created_at),
            ])
            ->values()
            ->all();
    }

    public function hasTrustedPin(string $householdId): bool
    {
        return Schema::hasTable('household_trusted_pins')
            && DB::table('household_trusted_pins')->where('household_id', $householdId)->exists();
    }

    public function statusOptions(): array
    {
        return collect([
            ['key' => 'safe', 'label' => 'Safe'],
            ['key' => 'evacuated', 'label' => 'Evacuated'],
            ['key' => 'unsafe', 'label' => 'Unsafe'],
            ['key' => 'needs_help', 'label' => 'Needs help'],
        ])->map(function (array $option): array {
            $status = $this->resolveStatus($option['key']);
            $option['status_id'] = $status['status_id'] ?? null;

            return $option;
        })->values()->all();
    }

    public function resolveStatus(string $mobileKey): ?array
    {
        if (! Schema::hasTable('household_statuses')) {
            return null;
        }

        $map = [
            'safe' => ['safe', 'active', 'returned'],
            'evacuated' => ['evacuated', 'relocated'],
            'unsafe' => ['unsafe', 'not_evacuated', 'displaced'],
            'needs_help' => ['needs_help', 'need_help', 'needs_assistance', 'injured', 'missing', 'not_evacuated', 'unsafe'],
        ];

        $candidates = $map[$mobileKey] ?? [$mobileKey];
        $orderSql = collect($candidates)
            ->values()
            ->map(fn (string $key, int $index): string => 'WHEN status_key = ? THEN '.$index)
            ->implode(' ');

        $row = DB::table('household_statuses')
            ->whereIn('status_key', $candidates)
            ->orderByRaw('CASE '.$orderSql.' ELSE 999 END', $candidates)
            ->orderBy('status_id')
            ->first([
                'status_id',
                'status_key',
                $this->householdStatusLabelSelect('household_statuses', 'status_label'),
            ]);

        if (! $row) {
            return null;
        }

        return [
            'status_id' => $row->status_id,
            'status_key' => $row->status_key,
            'status_label' => $row->status_label,
        ];
    }

    public function householdMember(string $householdId, string $memberId): ?object
    {
        $memberKeyColumn = $this->memberKeyColumn();

        if (! $memberKeyColumn) {
            return null;
        }

        return DB::table('household_members')
            ->where('household_id', $householdId)
            ->where($memberKeyColumn, $memberId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first([
                $this->firstExistingColumnSelect('household_members', ['member_id', 'id'], 'member_id'),
                $this->firstExistingColumnSelect('household_members', ['name', 'full_name'], 'name'),
                $this->optionalColumnSelect('household_members', 'first_name', 'first_name'),
                $this->optionalColumnSelect('household_members', 'last_name', 'last_name'),
            ]);
    }

    public function resolveTrustedHouseholdId(string $value): ?string
    {
        $input = trim($value);

        if ($input === '' || ! Schema::hasTable('households')) {
            return null;
        }

        $candidates = collect([$input]);

        if (! Str::startsWith(Str::upper($input), 'HH-')) {
            $candidates->push('HH-' . $input);
        }

        if (Schema::hasTable('users')) {
            $userHouseholdId = DB::table('users')
                ->where('username', $input)
                ->value('household_id');

            if ($userHouseholdId) {
                $candidates->push((string) $userHouseholdId);
            }
        }

        $candidateValues = $candidates
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = DB::table('households')
            ->whereIn('household_id', $candidateValues);

        if (Schema::hasColumn('households', 'household_code')) {
            $query->orWhereIn('household_code', $candidateValues);
        }

        return $query->value('household_id');
    }

    public function memberKeyColumn(): ?string
    {
        if (! Schema::hasTable('household_members')) {
            return null;
        }

        if (Schema::hasColumn('household_members', 'member_id')) {
            return 'member_id';
        }

        if (Schema::hasColumn('household_members', 'id')) {
            return 'id';
        }

        return null;
    }

    public function memberForUser($user): ?object
    {
        $memberKeyColumn = $this->memberKeyColumn();
        if (! $memberKeyColumn || empty($user?->member_id)) return null;

        return DB::table('household_members')
            ->where($memberKeyColumn, $user->member_id)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first([
                $this->firstExistingColumnSelect('household_members', ['name', 'full_name'], 'name'),
                $this->optionalColumnSelect('household_members', 'first_name', 'first_name'),
                $this->optionalColumnSelect('household_members', 'middle_name', 'middle_name'),
                $this->optionalColumnSelect('household_members', 'last_name', 'last_name'),
            ]);
    }

    public function hasGeotag(string $householdId): bool
    {
        return Schema::hasTable('geotagged_locations')
            && DB::table('geotagged_locations')->where('household_id', $householdId)->whereNotNull('latitude')->whereNotNull('longitude')->exists();
    }

    public function hasDevice(string $householdId, ?string $userId): bool
    {
        if (! Schema::hasTable('device_tokens')) return false;
        $query = DB::table('device_tokens')->where('household_id', $householdId);
        if ($userId && Schema::hasColumn('device_tokens', 'user_id')) $query->where('user_id', $userId);
        return $query->exists();
    }

    private function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function optionalColumnSelect(string $table, string $column, string $alias, ?string $tableAlias = null)
    {
        if (Schema::hasColumn($table, $column)) {
            $prefix = $tableAlias ?: $table;

            return "{$prefix}.{$column} as {$alias}";
        }

        return DB::raw("NULL as {$alias}");
    }

    private function firstExistingColumnSelect(string $table, array $columns, string $alias, ?string $tableAlias = null)
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $prefix = $tableAlias ?: $table;

                return "{$prefix}.{$column} as {$alias}";
            }
        }

        return DB::raw("NULL as {$alias}");
    }

    private function householdStatusLabelSelect(string $tableAlias, string $alias)
    {
        if (Schema::hasColumn('household_statuses', 'status_label')) {
            return "{$tableAlias}.status_label as {$alias}";
        }

        if (Schema::hasColumn('household_statuses', 'status_name')) {
            return "{$tableAlias}.status_name as {$alias}";
        }

        return DB::raw("NULL as {$alias}");
    }
}


