<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HouseholdStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();
        $eventId = $activeEvent?->event_id;
        $perPage = min(max((int) $request->query('per_page', 20), 10), 50);

        $query = $this->householdListQuery($eventId);
        $this->applyListFilters($query, $request);

        $households = $query
            ->orderByRaw('CASE WHEN hd.needs_dispatch = 1 THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN hs.status_key IN ("not_evacuated", "displaced", "unsafe", "missing", "injured") THEN 0 ELSE 1 END')
            ->orderByDesc('hd.last_reported_at')
            ->orderBy('h.household_name')
            ->paginate($perPage);

        $items = collect($households->items());
        $householdIds = $items->pluck('household_id')->filter()->values();

        $latestLogs = $this->getLatestLogs($householdIds, $eventId);
        $latestDevices = $this->getLatestDevices($householdIds);
        $accountUsers = $this->getHouseholdAccountUsers($householdIds);

        $rows = $items
            ->map(function (object $row) use ($latestLogs, $latestDevices, $accountUsers, $activeEvent): array {
                return $this->formatHouseholdRow(
                    $row,
                    $latestLogs->get($row->household_id),
                    $latestDevices->get($row->household_id),
                    $accountUsers->get($row->household_id),
                    (bool) $activeEvent
                );
            })
            ->values();

        return response()->json([
            'data' => [
                'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                'summary' => $this->getSummary($eventId),
                'filters' => [
                    'puroks' => $this->getPurokOptions(),
                ],
                'households' => [
                    'data' => $rows,
                    'meta' => [
                        'current_page' => $households->currentPage(),
                        'per_page' => $households->perPage(),
                        'total' => $households->total(),
                        'last_page' => $households->lastPage(),
                        'from' => $households->firstItem(),
                        'to' => $households->lastItem(),
                    ],
                ],
                'purok_summary' => $this->getPurokSummary($eventId),
                'recent_activity' => $this->getRecentActivity($eventId),
            ],
        ]);
    }

    public function show(string $householdId): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();
        $eventId = $activeEvent?->event_id;
        $household = $this->getHouseholdRecord($householdId, $eventId);

        if (! $household) {
            return response()->json([
                'message' => 'Household record was not found.',
            ], 404);
        }

        $latestLog = $this->getLatestLogs(collect([$householdId]), $eventId)->get($householdId);
        $latestDevice = $this->getLatestDevices(collect([$householdId]))->get($householdId);
        $accountUser = $this->getHouseholdAccountUsers(collect([$householdId]))->get($householdId);
        $devices = $this->getDevices($householdId);

        return response()->json([
            'data' => [
                'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                'household' => $this->formatHouseholdDetail(
                    $household,
                    $latestLog,
                    $latestDevice,
                    $accountUser,
                    $devices,
                    (bool) $activeEvent
                ),
                'members' => $this->getMembers($householdId, $devices),
                'devices' => $devices,
            ],
        ]);
    }

    public function statusLogs(string $householdId): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();

        $logs = DB::table('household_status_logs as hsl')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'hsl.submitted_by_user_id')
            ->where('hsl.household_id', $householdId)
            ->when($activeEvent, fn ($query) => $query->where('hsl.disaster_id', $activeEvent->event_id))
            ->orderByDesc('hsl.submitted_at')
            ->orderByDesc('hsl.created_at')
            ->limit(50)
            ->get([
                'hsl.status_log_id',
                'hsl.status_id',
                'hs.status_key',
                'hs.status_label',
                'hsl.source',
                'hsl.submitted_by_user_id',
                'u.name as user_name',
                'u.first_name',
                'u.last_name',
                'hsl.location_label',
                'hsl.location_accuracy_m',
                'hsl.battery_level',
                'hsl.signal_strength',
                'hsl.notes',
                'hsl.submitted_at',
                'hsl.created_at',
            ])
            ->map(fn (object $log): array => [
                'status_log_id' => $log->status_log_id,
                'status' => $this->formatStatus($log->status_key, $log->status_label),
                'source' => $this->sourceLabel($log->source),
                'submitted_by' => $this->personName($log->user_name, $log->first_name, $log->last_name, $log->submitted_by_user_id),
                'location_label' => $log->location_label,
                'location_accuracy_m' => $log->location_accuracy_m,
                'battery_level' => $log->battery_level,
                'signal_strength' => $log->signal_strength,
                'notes' => $log->notes,
                'submitted_at' => $this->formatDateTime($log->submitted_at ?? $log->created_at),
                'submitted_time' => $this->formatTime($log->submitted_at ?? $log->created_at),
            ])
            ->values();

        return response()->json([
            'data' => [
                'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                'logs' => $logs,
            ],
        ]);
    }

    public function storeStatusLog(Request $request, string $householdId): JsonResponse
    {
        $user = $request->user()?->load('role');
        $roleKey = $user?->role?->role_key;

        if ($roleKey === 'household_resident' && $user->household_id !== $householdId) {
            return response()->json([
                'message' => 'You can only submit a report for your own household account.',
            ], 403);
        }

        $activeEvent = $this->getActiveEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'A household status report can only be submitted during an active disaster event.',
            ], 409);
        }

        $household = DB::table('households')
            ->where('household_id', $householdId)
            ->whereNull('deleted_at')
            ->first();

        if (! $household) {
            return response()->json([
                'message' => 'Household record was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'exists:household_statuses,status_id'],
            'device_token_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'needs_dispatch' => ['nullable', 'boolean'],
        ], [
            'status_id.required' => 'Please choose the household status.',
            'status_id.exists' => 'The selected household status is not valid.',
            'latitude.between' => 'Latitude must be a valid map coordinate.',
            'longitude.between' => 'Longitude must be a valid map coordinate.',
            'battery_level.between' => 'Battery level must be from 0 to 100.',
        ]);

        $deviceId = $validated['device_token_id'] ?? null;

        if ($deviceId && ! $this->deviceBelongsToHousehold($deviceId, $householdId)) {
            throw ValidationException::withMessages([
                'device_token_id' => ['This mobile device is not linked to the selected household.'],
            ]);
        }

        $source = $roleKey === 'rescuer' ? 'responder_field_report' : 'household_mobile';
        $now = now();

        $statusLogId = DB::transaction(function () use ($validated, $activeEvent, $householdId, $user, $source, $now, $deviceId, $request): int {
            $statusLogId = DB::table('household_status_logs')->insertGetId([
                'disaster_id' => $activeEvent->event_id,
                'household_id' => $householdId,
                'status_id' => $validated['status_id'],
                'source' => $source,
                'submitted_by_user_id' => $user?->user_id,
                'device_token_id' => $deviceId,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'location_label' => $validated['location_label'] ?? null,
                'location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->saveLatestHouseholdDisaster($activeEvent->event_id, $householdId, $validated, $user?->user_id, $source, $now, $deviceId);
            $this->saveLatestDeviceData($householdId, $validated, $now, $deviceId);
            $this->writeAuditLog($request, $statusLogId, $householdId, $activeEvent->event_id);

            return $statusLogId;
        });

        return response()->json([
            'message' => 'Household status report saved.',
            'data' => [
                'status_log_id' => $statusLogId,
            ],
        ], 201);
    }

    private function householdListQuery(?string $eventId)
    {
        $memberCounts = DB::table('household_members')
            ->select('household_id', DB::raw('COUNT(*) as member_total'))
            ->whereNull('deleted_at')
            ->groupBy('household_id');

        $deviceSummary = DB::table('device_tokens')
            ->select(
                'household_id',
                DB::raw('COUNT(*) as device_total'),
                DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_device_total'),
                DB::raw('MIN(battery_level) as lowest_battery'),
                DB::raw('MAX(COALESCE(last_seen_at, logged_at, updated_at, created_at)) as latest_device_seen_at')
            )
            ->groupBy('household_id');

        return DB::table('households as h')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_disasters as hd', function ($join) use ($eventId): void {
                $join->on('hd.household_id', '=', 'h.household_id');

                if ($eventId) {
                    $join->where('hd.disaster_id', '=', $eventId);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->leftJoin('users as reporter', 'reporter.user_id', '=', 'hd.last_reported_by_user_id')
            ->leftJoinSub($memberCounts, 'members', 'members.household_id', '=', 'h.household_id')
            ->leftJoinSub($deviceSummary, 'devices', 'devices.household_id', '=', 'h.household_id')
            ->whereNull('h.deleted_at')
            ->whereNotNull('h.household_id')
            ->select([
                'h.household_id',
                'h.household_code',
                'h.household_number',
                'h.household_name',
                'h.contact_number',
                'h.emergency_contact',
                'h.member_count',
                'a.full_address',
                'a.purok_sitio',
                'a.barangay_name',
                DB::raw("COALESCE(NULLIF(a.purok_sitio, ''), NULLIF(a.barangay_name, ''), 'Unassigned') as purok"),
                'hd.current_status_id',
                'hd.last_status_source',
                'hd.last_status_notes',
                'hd.last_reported_by_user_id',
                'hd.last_latitude',
                'hd.last_longitude',
                'hd.last_battery_level',
                'hd.last_reported_at',
                'hd.priority_level',
                'hd.needs_dispatch',
                'hs.status_key',
                'hs.status_label',
                'reporter.name as reporter_name',
                'reporter.first_name as reporter_first_name',
                'reporter.last_name as reporter_last_name',
                DB::raw('COALESCE(members.member_total, h.member_count, 0) as member_total'),
                DB::raw('COALESCE(devices.device_total, 0) as device_total'),
                DB::raw('COALESCE(devices.active_device_total, 0) as active_device_total'),
                'devices.lowest_battery',
                'devices.latest_device_seen_at',
            ]);
    }

    private function applyListFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $purok = trim((string) $request->query('purok', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $deviceRisk = trim((string) $request->query('device_risk', 'all'));

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('h.household_name', 'like', "%{$search}%")
                    ->orWhere('h.household_id', 'like', "%{$search}%")
                    ->orWhere('h.household_code', 'like', "%{$search}%")
                    ->orWhere('h.contact_number', 'like', "%{$search}%")
                    ->orWhere('a.full_address', 'like', "%{$search}%")
                    ->orWhere('a.purok_sitio', 'like', "%{$search}%");
            });
        }

        if ($purok !== '' && $purok !== 'all') {
            $query->where('a.purok_sitio', $purok);
        }

        if ($status === 'unchecked') {
            $query->whereNull('hd.current_status_id');
        }

        if ($status === 'safe') {
            $query->whereIn('hs.status_key', ['active', 'returned', 'safe', 'evacuated', 'relocated']);
        }

        if ($status === 'safe_only') {
            $query->whereIn('hs.status_key', ['active', 'returned', 'safe']);
        }

        if ($status === 'evacuated') {
            $query->whereIn('hs.status_key', ['evacuated', 'relocated']);
        }

        if ($status === 'unsafe') {
            $query->whereIn('hs.status_key', ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured']);
        }

        if ($status === 'device' || $deviceRisk === 'watch') {
            $query->where(function ($deviceQuery): void {
                $deviceQuery
                    ->where(function ($lowBattery): void {
                        $lowBattery
                            ->where('devices.device_total', '>', 0)
                            ->where('devices.lowest_battery', '<=', 25);
                    })
                    ->orWhere(function ($staleDevice): void {
                        $staleDevice
                            ->where('devices.device_total', '>', 0)
                            ->where(function ($staleCheck): void {
                                $staleCheck
                                    ->whereNull('devices.latest_device_seen_at')
                                    ->orWhere('devices.latest_device_seen_at', '<', now()->subHours(6));
                            });
                    });
            });
        }

        if ($status === 'urgent' || $deviceRisk === 'critical') {
            $query->where(function ($urgentQuery): void {
                $urgentQuery
                    ->where('hd.needs_dispatch', true)
                    ->orWhereIn('hs.status_key', ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured'])
                    ->orWhere(function ($criticalDevice): void {
                        $criticalDevice
                            ->where('devices.device_total', '>', 0)
                            ->where('devices.lowest_battery', '<=', 15);
                    });
            });
        }
    }

    private function getSummary(?string $eventId): array
    {
        $total = DB::table('households')
            ->whereNull('deleted_at')
            ->whereNotNull('household_id')
            ->count();

        if (! $eventId) {
            return [
                'total' => $total,
                'reported' => 0,
                'reporting_percent' => 0,
                'unchecked' => 0,
                'safe_total' => 0,
                'safe_only' => 0,
                'evacuated' => 0,
                'unsafe' => 0,
                'device_alerts' => $this->getDeviceAlertCount(),
                'urgent' => 0,
            ];
        }

        $counts = DB::table('household_disasters as hd')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->where('hd.disaster_id', $eventId)
            ->select('hs.status_key', DB::raw('COUNT(*) as total'))
            ->groupBy('hs.status_key')
            ->pluck('total', 'status_key');

        $safeOnly = $this->sumStatusKeys($counts, ['active', 'returned', 'safe']);
        $evacuated = $this->sumStatusKeys($counts, ['evacuated', 'relocated']);
        $unsafe = $this->sumStatusKeys($counts, ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured']);
        $safeTotal = $safeOnly + $evacuated;

        $reported = DB::table('household_disasters')
            ->where('disaster_id', $eventId)
            ->whereNotNull('current_status_id')
            ->distinct()
            ->count('household_id');

        $unchecked = max($total - $reported, 0);
        $reportingPercent = $total > 0 ? round(($reported / $total) * 100) : 0;

        return [
            'total' => $total,
            'reported' => $reported,
            'reporting_percent' => $reportingPercent,
            'unchecked' => $unchecked,
            'safe_total' => $safeTotal,
            'safe_only' => $safeOnly,
            'evacuated' => $evacuated,
            'unsafe' => $unsafe,
            'device_alerts' => $this->getDeviceAlertCount(),
            'urgent' => DB::table('household_disasters as hd')
                ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
                ->where('hd.disaster_id', $eventId)
                ->where(function ($query): void {
                    $query
                        ->where('hd.needs_dispatch', true)
                        ->orWhereIn('hs.status_key', ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured']);
                })
                ->count(),
        ];
    }

    private function getPurokSummary(?string $eventId)
    {
        $rows = $this->householdListQuery($eventId)->get();

        return $rows
            ->groupBy(fn (object $row): string => $row->purok ?: 'Unassigned')
            ->map(function ($items, string $purok): array {
                $total = $items->count();
                $reported = $items->whereNotNull('current_status_id')->count();
                $unsafe = $items->filter(fn (object $item): bool => in_array($item->status_key, ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured'], true))->count();
                $deviceRisk = $items->filter(function (object $item): bool {
                    return (int) $item->device_total > 0 && ((int) $item->lowest_battery <= 25 || $this->isStale($item->latest_device_seen_at));
                })->count();

                return [
                    'purok' => $purok,
                    'total' => $total,
                    'reported' => $reported,
                    'unchecked' => max($total - $reported, 0),
                    'unsafe' => $unsafe,
                    'device_risk' => $deviceRisk,
                    'next_action' => $unsafe > 0 ? 'Dispatch focus' : ($deviceRisk > 0 ? 'Check devices' : 'Monitor'),
                    'priority' => $unsafe > 0 ? 'urgent' : ($deviceRisk > 0 ? 'watch' : 'stable'),
                ];
            })
            ->sortByDesc('unsafe')
            ->values();
    }

    private function getRecentActivity(?string $eventId)
    {
        if (! $eventId) {
            return collect();
        }

        return DB::table('household_status_logs as hsl')
            ->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->where('hsl.disaster_id', $eventId)
            ->orderByDesc('hsl.submitted_at')
            ->limit(10)
            ->get([
                'hsl.status_log_id',
                'hsl.household_id',
                'h.household_name',
                'hs.status_key',
                'hs.status_label',
                'hsl.source',
                'hsl.location_label',
                'hsl.battery_level',
                'hsl.submitted_at',
            ])
            ->map(fn (object $activity): array => [
                'status_log_id' => $activity->status_log_id,
                'household_id' => $activity->household_id,
                'household_name' => $activity->household_name ?? $activity->household_id,
                'status' => $this->formatStatus($activity->status_key, $activity->status_label),
                'source' => $this->sourceLabel($activity->source),
                'location_label' => $activity->location_label,
                'battery_level' => $activity->battery_level,
                'time' => $this->formatTime($activity->submitted_at),
            ])
            ->values();
    }

    private function getHouseholdRecord(string $householdId, ?string $eventId): ?object
    {
        return $this->householdListQuery($eventId)
            ->where('h.household_id', $householdId)
            ->first();
    }

    private function getLatestLogs($householdIds, ?string $eventId)
    {
        if ($householdIds->isEmpty()) {
            return collect();
        }

        return DB::table('household_status_logs as hsl')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->whereIn('hsl.household_id', $householdIds)
            ->when($eventId, fn ($query) => $query->where('hsl.disaster_id', $eventId))
            ->orderByDesc('hsl.submitted_at')
            ->orderByDesc('hsl.created_at')
            ->get([
                'hsl.*',
                'hs.status_key',
                'hs.status_label',
            ])
            ->groupBy('household_id')
            ->map(fn ($logs) => $logs->first());
    }

    private function getLatestDevices($householdIds)
    {
        if ($householdIds->isEmpty()) {
            return collect();
        }

        return DB::table('device_tokens')
            ->whereIn('household_id', $householdIds)
            ->orderByDesc(DB::raw('COALESCE(last_seen_at, logged_at, updated_at, created_at)'))
            ->get()
            ->groupBy('household_id')
            ->map(fn ($devices) => $devices->first());
    }

    private function getHouseholdAccountUsers($householdIds)
    {
        if ($householdIds->isEmpty()) {
            return collect();
        }

        return DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->whereIn('u.household_id', $householdIds)
            ->where('r.role_key', 'household_resident')
            ->whereNull('u.deleted_at')
            ->orderBy('u.created_at')
            ->get([
                'u.household_id',
                'u.user_id',
                'u.username',
                'u.name',
                'u.first_name',
                'u.last_name',
                'u.contact_number',
            ])
            ->groupBy('household_id')
            ->map(fn ($users) => $users->first());
    }

    private function getDevices(string $householdId)
    {
        $devices = DB::table('device_tokens as dt')
            ->leftJoin('household_members as hm', 'hm.member_id', '=', 'dt.member_id')
            ->where('dt.household_id', $householdId)
            ->orderByDesc(DB::raw('COALESCE(dt.last_seen_at, dt.logged_at, dt.updated_at, dt.created_at)'))
            ->get([
                'dt.id',
                'dt.device_uuid',
                'dt.member_id',
                'dt.device_name',
                'dt.platform',
                'dt.app_role',
                'dt.battery_level',
                'dt.signal_strength',
                'dt.location_permission_status',
                'dt.notification_permission_status',
                'dt.last_location_label',
                'dt.last_location_accuracy_m',
                'dt.last_location_at',
                'dt.last_seen_at',
                'dt.is_active',
                'hm.name as member_name',
                'hm.first_name',
                'hm.last_name',
            ]);

        $trackingLogs = DB::table('device_tracking_logs')
            ->where('household_id', $householdId)
            ->orderByDesc('logged_at')
            ->get()
            ->groupBy('device_token_id')
            ->map(fn ($logs) => $logs->first());

        return $devices
            ->map(function (object $device) use ($trackingLogs): array {
                $tracking = $trackingLogs->get($device->id);
                $lastLocation = $device->last_location_label ?: ($tracking?->location_label);
                $battery = $device->battery_level ?? $tracking?->battery_level;

                return [
                    'id' => $device->id,
                    'device_uuid' => $device->device_uuid,
                    'device_name' => $device->device_name ?: 'Household mobile',
                    'member_id' => $device->member_id,
                    'member_name' => $this->personName($device->member_name, $device->first_name, $device->last_name, 'Household user'),
                    'platform' => $this->label($device->platform ?: 'mobile'),
                    'app_role' => $this->label($device->app_role ?: 'household'),
                    'battery_level' => $battery,
                    'battery_tone' => $this->batteryTone($battery),
                    'signal_strength' => $device->signal_strength ?? $tracking?->signal_strength,
                    'location_permission_status' => $this->label($device->location_permission_status ?: 'unknown'),
                    'notification_permission_status' => $this->label($device->notification_permission_status ?: 'unknown'),
                    'last_location_label' => $lastLocation ?: 'No location yet',
                    'last_location_accuracy_m' => $device->last_location_accuracy_m ?? $tracking?->accuracy_m,
                    'allowed_location' => $tracking ? ((bool) $tracking->is_allowed_location ? 'Allowed' : 'Outside allowed area') : 'No tracking log yet',
                    'last_seen_at' => $this->formatDateTime($device->last_seen_at ?? $tracking?->logged_at),
                    'last_seen_time' => $this->formatTime($device->last_seen_at ?? $tracking?->logged_at),
                    'is_active' => (bool) $device->is_active,
                    'risk' => $this->deviceRisk(1, (bool) $device->is_active ? 1 : 0, $battery, $device->last_seen_at ?? $tracking?->logged_at, $lastLocation),
                ];
            })
            ->values();
    }

    private function getMembers(string $householdId, $devices)
    {
        $devicesByMember = $devices
            ->filter(fn (array $device): bool => ! empty($device['member_id']))
            ->groupBy('member_id')
            ->map(fn ($memberDevices) => $memberDevices->first());

        return DB::table('household_members')
            ->where('household_id', $householdId)
            ->whereNull('deleted_at')
            ->orderByRaw('CASE WHEN relation IN ("Head", "head", "Household Head") THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get()
            ->map(function (object $member) use ($devicesByMember): array {
                $device = $devicesByMember->get($member->member_id);

                return [
                    'member_id' => $member->member_id,
                    'name' => $member->name ?: trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
                    'relation' => $member->relation ?: 'Member',
                    'age' => $member->age ?: $this->ageFromBirthDate($member->birth_date),
                    'gender' => $member->gender ?: $member->sex,
                    'risk_flags' => $this->memberRiskFlags($member),
                    'device_name' => $device['device_name'] ?? 'No assigned mobile',
                    'battery_level' => $device['battery_level'] ?? null,
                    'last_allowed_location' => $device['allowed_location'] ?? 'No device location',
                    'last_location_label' => $device['last_location_label'] ?? null,
                    'last_seen_at' => $device['last_seen_at'] ?? null,
                ];
            })
            ->values();
    }

    private function formatHouseholdRow(object $row, ?object $latestLog, ?object $latestDevice, ?object $accountUser, bool $hasActiveEvent): array
    {
        $status = $hasActiveEvent
            ? $this->formatStatus($row->status_key, $row->status_label)
            : ['key' => 'standby', 'label' => 'No active event', 'tone' => 'gray'];

        if ($hasActiveEvent && ! $row->current_status_id) {
            $status = ['key' => 'unchecked', 'label' => 'Unchecked', 'tone' => 'gray'];
        }

        $battery = $latestDevice?->battery_level ?? $row->lowest_battery ?? $row->last_battery_level ?? $latestLog?->battery_level;
        $lastSeen = $latestDevice?->last_seen_at ?? $row->latest_device_seen_at ?? $latestLog?->submitted_at ?? $row->last_reported_at;
        $locationLabel = $latestDevice?->last_location_label
            ?: $latestLog?->location_label
            ?: $row->full_address
            ?: 'No location yet';
        $deviceRisk = $this->deviceRisk((int) $row->device_total, (int) $row->active_device_total, $battery, $lastSeen, $locationLabel);
        $priority = $this->priority($status['key'], (bool) $row->needs_dispatch, $deviceRisk['key'], $hasActiveEvent);

        return [
            'id' => $row->household_id,
            'household_id' => $row->household_id,
            'household_name' => $row->household_name ?: 'Unnamed household',
            'account_holder' => $this->personName($accountUser?->name, $accountUser?->first_name, $accountUser?->last_name, $accountUser?->username ?? 'No linked account'),
            'account_id' => $accountUser?->username ?? $accountUser?->user_id,
            'household_code' => $row->household_code ?? $row->household_number,
            'contact_number' => $row->contact_number,
            'purok' => $row->purok ?: 'Unassigned',
            'address' => $row->full_address,
            'people' => (int) $row->member_total,
            'status' => $status,
            'source' => [
                'label' => $this->sourceLabel($row->last_status_source ?: $latestLog?->source),
                'submitted_by' => $this->personName($row->reporter_name, $row->reporter_first_name, $row->reporter_last_name, $row->last_reported_by_user_id),
                'time' => $this->formatTime($row->last_reported_at ?? $latestLog?->submitted_at),
                'datetime' => $this->formatDateTime($row->last_reported_at ?? $latestLog?->submitted_at),
                'notes' => $row->last_status_notes ?? $latestLog?->notes,
            ],
            'device' => [
                'total' => (int) $row->device_total,
                'active' => (int) $row->active_device_total,
                'lowest_battery' => $battery,
                'battery_tone' => $this->batteryTone($battery),
                'last_seen_at' => $this->formatDateTime($lastSeen),
                'last_seen_time' => $this->formatTime($lastSeen),
                'risk' => $deviceRisk,
            ],
            'location' => [
                'label' => $locationLabel,
                'note' => $this->locationNote($latestDevice, $latestLog, $row),
                'latitude' => $latestDevice?->last_latitude ?? $latestLog?->latitude ?? $row->last_latitude,
                'longitude' => $latestDevice?->last_longitude ?? $latestLog?->longitude ?? $row->last_longitude,
            ],
            'priority' => $priority,
            'needs_dispatch' => (bool) $row->needs_dispatch,
        ];
    }

    private function formatHouseholdDetail(object $row, ?object $latestLog, ?object $latestDevice, ?object $accountUser, $devices, bool $hasActiveEvent): array
    {
        $basic = $this->formatHouseholdRow($row, $latestLog, $latestDevice, $accountUser, $hasActiveEvent);
        $lowestBattery = $devices->pluck('battery_level')->filter(fn ($value) => $value !== null)->min();
        $basic['detail_tiles'] = [
            [
                'label' => 'Lowest battery',
                'value' => $lowestBattery !== null ? $lowestBattery.'%' : ($basic['device']['lowest_battery'] !== null ? $basic['device']['lowest_battery'].'%' : 'No battery data'),
            ],
            [
                'label' => 'Last location',
                'value' => $basic['location']['label'],
            ],
            [
                'label' => 'Devices',
                'value' => $devices->count().' synced',
            ],
            [
                'label' => 'Risk flags',
                'value' => $this->householdRiskSummary($row->household_id),
            ],
        ];

        return $basic;
    }

    private function getActiveEvent(): ?object
    {
        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->whereNull('de.ended_at')
            ->orderByDesc('de.started_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function formatActiveEvent(object $event): array
    {
        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Unspecified',
            'severity_key' => $event->severity_key ?? 'medium',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
        ];
    }

    private function formatStatus(?string $statusKey, ?string $statusLabel): array
    {
        if (! $statusKey) {
            return ['key' => 'unchecked', 'label' => 'Unchecked', 'tone' => 'gray'];
        }

        $key = str_replace('_', '-', $statusKey);
        $label = $statusLabel ?: $this->label($statusKey);

        if (in_array($statusKey, ['active', 'returned', 'safe'], true)) {
            $key = 'safe';
            $label = 'Safe';
        }

        if (in_array($statusKey, ['evacuated', 'relocated'], true)) {
            $key = 'evacuated';
            $label = 'Evacuated';
        }

        if (in_array($statusKey, ['not_evacuated', 'displaced', 'unsafe'], true)) {
            $key = 'unsafe';
            $label = 'Unsafe';
        }

        return [
            'key' => $key,
            'label' => $label,
            'tone' => $this->statusTone($key),
        ];
    }

    private function statusTone(string $statusKey): string
    {
        if (in_array($statusKey, ['safe', 'active', 'returned'], true)) {
            return 'green';
        }

        if (in_array($statusKey, ['evacuated', 'relocated'], true)) {
            return 'blue';
        }

        if (in_array($statusKey, ['unsafe', 'missing', 'not-evacuated', 'displaced'], true)) {
            return 'red';
        }

        if ($statusKey === 'injured') {
            return 'amber';
        }

        return 'gray';
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            'household_mobile', 'mobile', 'self_report' => 'Household mobile',
            'responder_field_report', 'responder', 'rescuer' => 'Responder field report',
            default => $source ? $this->label($source) : 'No event report',
        };
    }

    private function deviceRisk(int $deviceTotal, int $activeDeviceTotal, mixed $battery, ?string $lastSeen, ?string $locationLabel): array
    {
        if ($deviceTotal <= 0) {
            return ['key' => 'none', 'label' => 'No synced device'];
        }

        if ($activeDeviceTotal <= 0 || ($battery !== null && (int) $battery <= 15)) {
            return ['key' => 'critical', 'label' => 'Critical'];
        }

        if (($battery !== null && (int) $battery <= 25) || $this->isStale($lastSeen) || ! $locationLabel) {
            return ['key' => 'watch', 'label' => 'Device watch'];
        }

        return ['key' => 'stable', 'label' => 'Stable'];
    }

    private function priority(string $statusKey, bool $needsDispatch, string $deviceRisk, bool $hasActiveEvent): array
    {
        if (! $hasActiveEvent) {
            return ['key' => 'standby', 'label' => 'Standby'];
        }

        if ($needsDispatch || in_array($statusKey, ['unsafe', 'missing', 'injured'], true) || $deviceRisk === 'critical') {
            return ['key' => 'urgent', 'label' => 'Dispatch focus'];
        }

        if ($statusKey === 'unchecked' || in_array($deviceRisk, ['watch', 'none'], true)) {
            return ['key' => 'watch', 'label' => 'Follow-up'];
        }

        return ['key' => 'stable', 'label' => 'Stable'];
    }

    private function batteryTone(mixed $battery): string
    {
        if ($battery === null) {
            return 'unknown';
        }

        if ((int) $battery <= 15) {
            return 'critical';
        }

        if ((int) $battery <= 25) {
            return 'low';
        }

        return 'ok';
    }

    private function isStale(?string $dateTime): bool
    {
        if (! $dateTime) {
            return false;
        }

        return Carbon::parse($dateTime)->lt(now()->subHours(6));
    }

    private function locationNote(?object $latestDevice, ?object $latestLog, object $row): string
    {
        if ($latestDevice?->last_location_label) {
            $permission = $this->label($latestDevice->location_permission_status ?? 'unknown');
            $accuracy = $latestDevice->last_location_accuracy_m ? round((float) $latestDevice->last_location_accuracy_m).'m' : 'accuracy unknown';

            return "{$permission} location permission - {$accuracy}";
        }

        if ($latestLog?->location_label) {
            $accuracy = $latestLog->location_accuracy_m ? round((float) $latestLog->location_accuracy_m).'m' : 'accuracy unknown';

            return "Status report location - {$accuracy}";
        }

        if ($row->full_address) {
            return 'Registered address';
        }

        return 'No location saved yet';
    }

    private function memberRiskFlags(object $member): string
    {
        $flags = [];

        if ((bool) $member->is_pwd) {
            $flags[] = 'PWD';
        }

        if ((bool) $member->is_senior) {
            $flags[] = 'Senior';
        }

        if ((bool) $member->is_pregnant) {
            $flags[] = 'Pregnant';
        }

        if ($member->special_needs) {
            $flags[] = $member->special_needs;
        }

        return count($flags) > 0 ? implode(' / ', array_unique($flags)) : 'None';
    }

    private function householdRiskSummary(string $householdId): string
    {
        $members = DB::table('household_members')
            ->where('household_id', $householdId)
            ->whereNull('deleted_at')
            ->get();

        $flags = $members
            ->map(fn (object $member): string => $this->memberRiskFlags($member))
            ->filter(fn (string $flag): bool => $flag !== 'None')
            ->unique()
            ->values();

        return $flags->count() > 0 ? $flags->implode(' / ') : 'None recorded';
    }

    private function getPurokOptions()
    {
        return DB::table('addresses')
            ->whereNotNull('purok_sitio')
            ->where('purok_sitio', '<>', '')
            ->distinct()
            ->orderBy('purok_sitio')
            ->pluck('purok_sitio')
            ->values();
    }

    private function getDeviceAlertCount(): int
    {
        return DB::table('device_tokens')
            ->where(function ($query): void {
                $query
                    ->where('battery_level', '<=', 25)
                    ->orWhereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subHours(6));
            })
            ->count();
    }

    private function saveLatestHouseholdDisaster(string $eventId, string $householdId, array $validated, ?string $userId, string $source, Carbon $now, ?int $deviceId): void
    {
        $existing = DB::table('household_disasters')
            ->where('disaster_id', $eventId)
            ->where('household_id', $householdId)
            ->lockForUpdate()
            ->first();

        $data = [
            'current_status_id' => $validated['status_id'],
            'last_status_source' => $source,
            'last_status_notes' => $validated['notes'] ?? null,
            'last_reported_by_user_id' => $userId,
            'last_device_token_id' => $deviceId,
            'last_latitude' => $validated['latitude'] ?? null,
            'last_longitude' => $validated['longitude'] ?? null,
            'last_battery_level' => $validated['battery_level'] ?? null,
            'last_reported_at' => $now,
            'needs_dispatch' => (bool) ($validated['needs_dispatch'] ?? false),
            'priority_level' => (bool) ($validated['needs_dispatch'] ?? false) ? 'urgent' : null,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('household_disasters')
                ->where('household_disaster_id', $existing->household_disaster_id)
                ->update($data);

            return;
        }

        $nextId = ((int) DB::table('household_disasters')->lockForUpdate()->max('household_disaster_id')) + 1;

        DB::table('household_disasters')->insert(array_merge($data, [
            'household_disaster_id' => $nextId,
            'household_id' => $householdId,
            'disaster_id' => $eventId,
            'initial_status_id' => $validated['status_id'],
            'created_at' => $now,
        ]));
    }

    private function saveLatestDeviceData(string $householdId, array $validated, Carbon $now, ?int $deviceId): void
    {
        if (! $deviceId) {
            return;
        }

        DB::table('device_tokens')
            ->where('id', $deviceId)
            ->where('household_id', $householdId)
            ->update([
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'last_latitude' => $validated['latitude'] ?? null,
                'last_longitude' => $validated['longitude'] ?? null,
                'last_location_label' => $validated['location_label'] ?? null,
                'last_location_accuracy_m' => $validated['location_accuracy_m'] ?? null,
                'last_location_at' => $now,
                'last_seen_at' => $now,
                'logged_at' => $now,
                'updated_at' => $now,
            ]);

        if (! array_key_exists('latitude', $validated) || ! array_key_exists('longitude', $validated)) {
            return;
        }

        $nextTrackingId = ((int) DB::table('device_tracking_logs')->lockForUpdate()->max('tracking_id')) + 1;

        DB::table('device_tracking_logs')->insert([
            'tracking_id' => $nextTrackingId,
            'device_token_id' => $deviceId,
            'household_id' => $householdId,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_label' => $validated['location_label'] ?? null,
            'accuracy_m' => $validated['location_accuracy_m'] ?? null,
            'location_source' => 'status_report',
            'is_allowed_location' => true,
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'logged_at' => $now,
        ]);
    }

    private function deviceBelongsToHousehold(int $deviceId, string $householdId): bool
    {
        return DB::table('device_tokens')
            ->where('id', $deviceId)
            ->where('household_id', $householdId)
            ->exists();
    }

    private function writeAuditLog(Request $request, int $statusLogId, string $householdId, string $eventId): void
    {
        $user = $request->user();

        DB::table('audit_logs')->insert([
            'user_id' => $user?->user_id,
            'role_key' => $user?->role?->role_key,
            'module' => 'household_status',
            'action' => 'create_status_report',
            'reference_table' => 'household_status_logs',
            'reference_id' => (string) $statusLogId,
            'old_values' => null,
            'new_values' => json_encode([
                'household_id' => $householdId,
                'disaster_id' => $eventId,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    private function sumStatusKeys($counts, array $keys): int
    {
        return collect($keys)->sum(fn (string $key): int => (int) ($counts[$key] ?? 0));
    }

    private function label(?string $value): string
    {
        return str($value ?? '')
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function personName(?string $name, ?string $firstName, ?string $lastName, ?string $fallback): string
    {
        $fullName = trim((string) ($name ?: trim(($firstName ?? '').' '.($lastName ?? ''))));

        return $fullName !== '' ? $fullName : ($fallback ?: 'Not recorded');
    }

    private function ageFromBirthDate(?string $birthDate): ?int
    {
        if (! $birthDate) {
            return null;
        }

        return Carbon::parse($birthDate)->age;
    }

    private function formatDateTime(?string $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->format('M d, Y h:i A');
    }

    private function formatTime(?string $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->format('h:i A');
    }
}
