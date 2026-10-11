<?php

namespace App\Queries;

use App\Models\DeviceToken;
use App\Models\DeviceTrackingLog;
use App\Models\DisasterEvent;
use App\Models\Household;
use App\Models\HouseholdStatusLog;
use App\Models\User;
use App\Presenters\HouseholdStatusPresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class HouseholdStatusQuery
{
    public function __construct(private HouseholdStatusPresenter $presenter) {}

    public function statusLogs(string $householdId, ?string $eventId)
    {
        return HouseholdStatusLog::query()->from('household_status_logs as hsl')
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'hsl.submitted_by_user_id')
            ->where('hsl.household_id', $householdId)
            ->when($eventId, fn ($query) => $query->where('hsl.disaster_id', $eventId))
            ->orderByDesc('hsl.submitted_at')->orderByDesc('hsl.created_at')->limit(50)
            ->get(['hsl.*', 'hs.status_key', 'hs.status_label', 'u.name as submitter_name', 'u.first_name as submitter_first_name', 'u.last_name as submitter_last_name']);
    }
    public function householdListQuery(?string $eventId)
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

        return Household::query()
            ->from('households as h')
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
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
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
                DB::raw("COALESCE(NULLIF(a.purok_sitio, ''), 'Unassigned') as purok"),
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

    public function applyListFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $purok = trim((string) $request->query('purok', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $deviceRisk = trim((string) $request->query('device_risk', 'all'));

        $sitioId = (string) $request->query('sitio_id', '');
        $purokId = (string) $request->query('purok_id', '');
        if (ctype_digit($sitioId) && ctype_digit($purokId)) {
            $selected = DB::table('puroks as selected_purok')
                ->join('sitios as selected_sitio', 'selected_sitio.sitio_id', '=', 'selected_purok.sitio_id')
                ->where('selected_sitio.sitio_id', (int) $sitioId)
                ->where('selected_purok.purok_id', (int) $purokId)
                ->first(['selected_purok.purok_name', 'selected_sitio.sitio_name']);
            if (! $selected) {
                $query->whereRaw('1 = 0');
            } else {
                $composite = $selected->purok_name.', '.$selected->sitio_name;
                $query->where(function ($q) use ($sitioId, $purokId, $selected, $composite): void {
                    $q->where('a.purok_id', (int) $purokId)
                        ->orWhere(function ($named) use ($sitioId, $selected): void {
                            $named->whereNull('a.purok_id')->where('a.sitio_id', (int) $sitioId)
                                ->where('a.purok_sitio', $selected->purok_name);
                        })
                        ->orWhere(function ($named) use ($composite): void {
                            $named->whereNull('a.purok_id')->whereNull('a.sitio_id')
                                ->where('a.purok_sitio', $composite);
                        });
                });
            }
        } elseif (ctype_digit($sitioId)) {
            $query->leftJoin('puroks as filter_purok', 'filter_purok.purok_id', '=', 'a.purok_id')
                ->whereRaw('COALESCE(a.sitio_id, filter_purok.sitio_id) = ?', [(int) $sitioId]);
        } elseif (ctype_digit($purokId)) {
            $query->where('a.purok_id', (int) $purokId);
        }

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
            $query->whereIn('hs.status_key', ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased']);
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
                    ->orWhereIn('hs.status_key', ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased'])
                    ->orWhere(function ($criticalDevice): void {
                        $criticalDevice
                            ->where('devices.device_total', '>', 0)
                            ->where('devices.lowest_battery', '<=', 15);
                    });
            });
        }
    }

    public function getSummary(?string $eventId): array
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
            ->select('hs.status_key', DB::raw('COUNT(DISTINCT hd.household_id) as total'))
            ->groupBy('hs.status_key')
            ->pluck('total', 'status_key');

        $safeOnly = $this->sumStatusKeys($counts, ['active', 'returned', 'safe', 'safe_at_home']);
        $evacuated = $this->sumStatusKeys($counts, ['evacuated', 'relocated']);
        $unsafe = $this->sumStatusKeys($counts, ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased']);
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
                        ->orWhereIn('hs.status_key', ['not_evacuated', 'displaced', 'unsafe', 'needs_help', 'need_help', 'needs_assistance', 'missing', 'injured', 'trapped', 'unreachable', 'deceased']);
                })
                ->distinct()
                ->count('hd.household_id'),
        ];
    }

    public function getPurokSummary(?string $eventId)
    {
        $areaExpression = "COALESCE(NULLIF(a.purok_sitio, ''), 'Unassigned')";
        $unsafeKeys = [
            'not_evacuated',
            'displaced',
            'unsafe',
            'needs_help',
            'need_help',
            'needs_assistance',
            'missing',
            'injured',
        ];
        $deviceStaleBefore = now()->subHours(6)->toDateTimeString();
        $deviceSummary = DB::table('device_tokens')
            ->select(
                'household_id',
                DB::raw('COUNT(*) as device_total'),
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
            ->leftJoinSub($deviceSummary, 'devices', 'devices.household_id', '=', 'h.household_id')
            ->whereNull('h.deleted_at')
            ->whereNotNull('h.household_id')
            ->selectRaw($areaExpression.' as purok')
            ->selectRaw('COUNT(DISTINCT h.household_id) as total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN hd.current_status_id IS NOT NULL THEN h.household_id END) as reported')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN hd.needs_dispatch = 1 OR hs.status_key IN ('.implode(',', array_fill(0, count($unsafeKeys), '?')).') THEN h.household_id END) as unsafe',
                $unsafeKeys
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN devices.device_total > 0 AND (devices.lowest_battery <= 25 OR devices.latest_device_seen_at IS NULL OR devices.latest_device_seen_at < ?) THEN h.household_id END) as device_risk',
                [$deviceStaleBefore]
            )
            ->groupByRaw($areaExpression)
            ->orderByDesc('unsafe')
            ->get()
            ->map(function (object $row): array {
                $total = (int) $row->total;
                $reported = (int) $row->reported;
                $unsafe = (int) $row->unsafe;
                $deviceRisk = (int) $row->device_risk;

                return [
                    'purok' => $row->purok ?: 'Unassigned',
                    'total' => $total,
                    'reported' => $reported,
                    'unchecked' => max($total - $reported, 0),
                    'unsafe' => $unsafe,
                    'device_risk' => $deviceRisk,
                    'next_action' => $unsafe > 0 ? 'Dispatch focus' : ($deviceRisk > 0 ? 'Check devices' : 'Monitor'),
                    'priority' => $unsafe > 0 ? 'urgent' : ($deviceRisk > 0 ? 'watch' : 'stable'),
                ];
            })
            ->values();
    }

    public function getRecentActivity(?string $eventId)
    {
        if (! $eventId) {
            return collect();
        }

        return HouseholdStatusLog::query()
            ->from('household_status_logs as hsl')
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
                'status' => $this->presenter->formatStatus($activity->status_key, $activity->status_label),
                'source' => $this->presenter->sourceLabel($activity->source),
                'location_label' => $activity->location_label,
                'battery_level' => $activity->battery_level,
                'time' => $this->presenter->formatTime($activity->submitted_at),
            ])
            ->values();
    }

    public function getHouseholdRecord(string $householdId, ?string $eventId): ?object
    {
        return $this->householdListQuery($eventId)
            ->where('h.household_id', $householdId)
            ->first();
    }

    public function getLatestLogs($householdIds, ?string $eventId)
    {
        if ($householdIds->isEmpty()) {
            return collect();
        }

        return HouseholdStatusLog::query()
            ->from('household_status_logs as hsl')
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

    public function getLatestDevices($householdIds)
    {
        if ($householdIds->isEmpty()) {
            return collect();
        }

        return DeviceToken::query()
            ->whereIn('household_id', $householdIds)
            ->orderByDesc(DB::raw('COALESCE(last_seen_at, logged_at, updated_at, created_at)'))
            ->get()
            ->groupBy('household_id')
            ->map(fn ($devices) => $devices->first());
    }

    public function getHouseholdAccountUsers($householdIds)
    {
        if ($householdIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->from('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->whereIn('u.household_id', $householdIds)
            ->where('r.role_key', 'household_resident')
            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('u.deleted_at'))
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

    public function getDevices(string $householdId)
    {
        $devices = DeviceToken::query()
            ->from('device_tokens as dt')
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
                'hm.first_name',
                'hm.middle_name',
                'hm.last_name',
            ]);

        $trackingLogs = DeviceTrackingLog::query()
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
                    'member_name' => trim(implode(' ', array_filter([
                        $device->first_name,
                        $device->middle_name,
                        $device->last_name,
                    ]))) ?: 'Household user',
                    'platform' => $this->presenter->label($device->platform ?: 'mobile'),
                    'app_role' => $this->presenter->label($device->app_role ?: 'household'),
                    'battery_level' => $battery,
                    'battery_tone' => $this->presenter->batteryTone($battery),
                    'signal_strength' => $device->signal_strength ?? $tracking?->signal_strength,
                    'location_permission_status' => $this->presenter->label($device->location_permission_status ?: 'unknown'),
                    'notification_permission_status' => $this->presenter->label($device->notification_permission_status ?: 'unknown'),
                    'last_location_label' => $lastLocation ?: 'No location yet',
                    'last_location_accuracy_m' => $device->last_location_accuracy_m ?? $tracking?->accuracy_m,
                    'allowed_location' => $tracking ? ((bool) $tracking->is_allowed_location ? 'Allowed' : 'Outside allowed area') : 'No tracking log yet',
                    'last_seen_at' => $this->presenter->formatDateTime($device->last_seen_at ?? $tracking?->logged_at),
                    'last_seen_time' => $this->presenter->formatTime($device->last_seen_at ?? $tracking?->logged_at),
                    'is_active' => (bool) $device->is_active,
                    'risk' => $this->presenter->deviceRisk(1, (bool) $device->is_active ? 1 : 0, $battery, $device->last_seen_at ?? $tracking?->logged_at, $lastLocation),
                ];
            })
            ->values();
    }

    public function getMembers(string $householdId, ?string $eventId, $devices)
    {
        $devicesByMember = $devices
            ->filter(fn (array $device): bool => ! empty($device['member_id']))
            ->groupBy('member_id')
            ->map(fn ($memberDevices) => $memberDevices->first());

        $householdDisplayStatus = $this->householdMemberDisplayStatus($householdId, $eventId);

        // Use the query builder here because some deployed household-member
        // tables predate the soft-delete column.  The Eloquent model's
        // SoftDeletes global scope would otherwise add an invalid
        // `household_members.deleted_at` predicate before the guarded check
        // below can run.
        return DB::table('household_members as hm')
            ->from('household_members as hm')
            ->leftJoin('relationships as r', 'r.relationship_id', '=', 'hm.relationship_id')
            ->leftJoin('genders as g', 'g.gender_id', '=', 'hm.gender_id')
            ->leftJoin('member_disaster_statuses as mds', function ($join) use ($eventId): void {
                $join->on('mds.member_id', '=', 'hm.member_id')
                    ->where('mds.disaster_id', '=', $eventId);
            })
            ->leftJoin('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
            ->where('hm.household_id', $householdId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('hm.deleted_at'))
            ->orderByDesc('hm.is_household_head')
            ->orderBy('hm.last_name')
            ->orderBy('hm.first_name')
            ->get([
                'hm.member_id',
                'hm.first_name',
                'hm.middle_name',
                'hm.last_name',
                'hm.birth_date',
                'g.gender_label',
                'r.relationship_label',
                'hm.is_household_head',
                'hm.is_pwd',
                'hm.is_senior',
                'hm.is_pregnant',
                'ms.status_key',
                'ms.status_label',
                'ms.color_hex as status_color',
                'mds.updated_at as status_updated_at',
            ])
            ->map(function (object $member) use ($devicesByMember, $householdDisplayStatus): array {
                $device = $devicesByMember->get($member->member_id);
                $fullName = trim(implode(' ', array_filter([
                    $member->first_name,
                    $member->middle_name,
                    $member->last_name,
                ])));
                $status = $member->status_key
                    ? $this->presenter->formatStatus($member->status_key, $member->status_label)
                    : ($householdDisplayStatus ?? [
                        'key' => 'unchecked',
                        'label' => 'No report',
                        'tone' => 'gray',
                    ]);

                return [
                    'member_id' => $member->member_id,
                    'name' => $fullName ?: 'Unnamed member',
                    'relation' => $member->relationship_label ?: 'Not specified',
                    'age' => $this->presenter->ageFromBirthDate($member->birth_date),
                    'gender' => $member->gender_label ?: 'Not specified',
                    'is_household_head' => (bool) $member->is_household_head,
                    'is_pwd' => (bool) $member->is_pwd,
                    'is_senior' => (bool) $member->is_senior,
                    'is_pregnant' => (bool) $member->is_pregnant,
                    'status' => $status,
                    'status_updated_at' => $this->presenter->formatDateTime($member->status_updated_at),
                    'risk_flags' => $this->presenter->memberRiskFlags($member),
                    'device_name' => $device['device_name'] ?? 'No assigned mobile',
                    'device_platform' => $device['platform'] ?? null,
                    'battery_level' => $device['battery_level'] ?? null,
                    'battery_tone' => $device['battery_tone'] ?? 'unknown',
                    'signal_strength' => $device['signal_strength'] ?? null,
                    'last_allowed_location' => $device['allowed_location'] ?? 'No device location',
                    'last_location_label' => $device['last_location_label'] ?? null,
                    'last_seen_at' => $device['last_seen_at'] ?? null,
                ];
            })
            ->values();
    }

    public function householdMemberDisplayStatus(string $householdId, ?string $eventId): ?array
    {
        if (! $eventId || ! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
            return null;
        }

        $statuses = DB::table('member_disaster_statuses as mds')
            ->join('household_members as hm', 'hm.member_id', '=', 'mds.member_id')
            ->join('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id')
            ->where('mds.disaster_id', $eventId)
            ->where('mds.household_id', $householdId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('hm.deleted_at'))
            ->pluck('ms.status_key');

        $unsafeKeys = [
            'unsafe',
            'needs_help',
            'needs_assistance',
            'injured',
            'trapped',
            'missing',
            'unreachable',
            'deceased',
        ];

        if ($statuses->intersect($unsafeKeys)->isNotEmpty()) {
            return [
                'key' => 'unsafe',
                'label' => 'Unsafe',
                'color' => '#DC2626',
            ];
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn (string $status): bool => in_array($status, ['safe', 'safe_at_home', 'active', 'returned'], true))) {
            return [
                'key' => 'safe',
                'label' => 'Safe',
                'color' => '#0D9488',
            ];
        }

        return null;
    }

    public function getActiveEvent(): ?object
    {
        return DisasterEvent::query()
            ->from('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->when(Schema::hasColumn('disaster_events', 'deleted_at'), fn ($query) => $query->whereNull('de.deleted_at'))
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

    public function getPurokOptions()
    {
        return collect(app(HouseholdPurokQuery::class)->names());
    }

    public function getDeviceAlertCount(): int
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
    public function householdRiskSummary(string $householdId): string
    {
        $members = DB::table('household_members')->where('household_id', $householdId)->whereNull('deleted_at')->get();
        $flags = $members->map(fn (object $member): string => $this->presenter->memberRiskFlags($member))
            ->filter(fn (string $flag): bool => $flag !== 'None')->unique()->values();
        return $flags->count() > 0 ? $flags->implode(' / ') : 'None recorded';
    }

    private function sumStatusKeys($counts, array $keys): int
    {
        return collect($keys)->sum(fn (string $key): int => (int) ($counts[$key] ?? 0));
    }
}


