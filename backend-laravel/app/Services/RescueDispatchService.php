<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescueDispatchService
{
    public function teams(): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();

        return response()->json([
            'data' => [
                'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                'summary' => $this->getDispatchSummary($activeEvent?->event_id),
                'teams' => $this->getTeamCards($activeEvent?->event_id),
                'responders' => $this->getResponders(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();
        $eventId = $request->query('event_id') ?: $activeEvent?->event_id;
        $perPage = min(max((int) $request->query('per_page', 20), 10), 50);

        $query = DB::table('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->where('ra.disaster_id', $eventId)
            ->select([
                'ra.assignment_id',
                'ra.assignment_code',
                'ra.responder_id',
                'ra.team_id',
                'ra.disaster_id',
                'ra.household_id',
                'ra.assigned_area',
                'ra.route_notes',
                'ra.priority_level',
                'ra.dispatch_notes',
                'ra.status',
                'ra.assigned_at',
                'ra.accepted_at',
                'ra.en_route_at',
                'ra.arrived_at',
                'ra.completed_at',
                'ra.outcome_notes',
                'ra.updated_at',
                'rt.team_name',
                'rt.team_code',
                'rt.team_type',
                'r.full_name as responder_name',
                'r.contact_number as responder_contact',
            ]);

        $this->applyDispatchFilters($query, $request);

        $dispatches = $query
            ->orderByRaw('CASE WHEN ra.status IN ("on_scene", "onscene", "dispatched", "en_route") THEN 0 ELSE 1 END')
            ->orderByDesc('ra.assigned_at')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'active_event' => $activeEvent ? $this->formatActiveEvent($activeEvent) : null,
                'summary' => $this->getDispatchSummary($eventId),
                'teams' => $this->getTeamCards($eventId),
                'responders' => $this->getResponders(),
                'risk_areas' => $this->getRiskAreas($eventId),
                'dispatches' => [
                    'data' => collect($dispatches->items())
                        ->map(fn (object $dispatch): array => $this->formatDispatch($dispatch))
                        ->values(),
                    'meta' => [
                        'current_page' => $dispatches->currentPage(),
                        'per_page' => $dispatches->perPage(),
                        'total' => $dispatches->total(),
                        'last_page' => $dispatches->lastPage(),
                        'from' => $dispatches->firstItem(),
                        'to' => $dispatches->lastItem(),
                    ],
                ],
                'activity_log' => $this->getDispatchActivity($eventId),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'Create a dispatch only after a disaster event is active.',
            ], 409);
        }

        $validated = $this->validateDispatch($request);

        if (empty($validated['household_id'])) {
            throw ValidationException::withMessages([
                'household_id' => ['Select a GPS-tagged household target before creating a routed dispatch.'],
            ]);
        }

        $responderId = $this->resolveResponderId($validated);

        if (! $responderId) {
            throw ValidationException::withMessages([
                'responder_id' => ['Select an available responder or a team with at least one available responder.'],
            ]);
        }

        $assignmentId = DB::transaction(function () use ($request, $validated, $responderId, $activeEvent): int {
            $now = now();
            $assignmentId = $this->nextId('responder_assignments', 'assignment_id');
            $status = $this->dbStatus($validated['status'] ?? 'dispatched');

            $this->ensureResponderCanReceiveDispatch($responderId);
            $this->ensureHouseholdCanReceiveDispatch($validated['household_id'] ?? null);

            DB::table('responder_assignments')->insert([
                'assignment_id' => $assignmentId,
                'assignment_code' => 'DSP-'.$now->format('Ymd').'-'.str_pad((string) $assignmentId, 4, '0', STR_PAD_LEFT),
                'responder_id' => $responderId,
                'team_id' => $validated['team_id'] ?? null,
                'disaster_id' => $activeEvent->event_id,
                'household_id' => $validated['household_id'] ?? null,
                'assigned_area' => $validated['assigned_area'],
                'route_notes' => $this->routeNotesJson($validated),
                'priority_level' => $validated['priority_level'],
                'dispatch_notes' => $validated['dispatch_notes'] ?? null,
                'created_by_admin_id' => $request->user()?->user_id,
                'status' => $status,
                'assigned_at' => $now,
                'accepted_at' => null,
                'en_route_at' => in_array($status, ['dispatched', 'en_route'], true) ? $now : null,
                'arrived_at' => $status === 'on_scene' ? $now : null,
                'completed_at' => $status === 'completed' ? $now : null,
                'outcome_notes' => $this->outcomesJson($validated),
                'updated_at' => $now,
            ]);

            $this->updateResponderDuty($responderId, $validated['team_id'] ?? null, $status);
            $this->writeAuditLog($request, 'create_dispatch', 'responder_assignments', (string) $assignmentId, null, $validated);

            return $assignmentId;
        });

        return response()->json([
            'message' => 'Dispatch assignment created.',
            'data' => $this->getDispatchById($assignmentId),
        ], 201);
    }

    public function show(int $assignmentId): JsonResponse
    {
        $dispatch = $this->getDispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'dispatch' => $this->formatDispatch($dispatch),
                'route' => $this->getRoute($assignmentId),
            ],
        ]);
    }

    public function update(Request $request, int $assignmentId): JsonResponse
    {
        $dispatch = $this->getDispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        $this->authorizeRescuerForDispatch($request, $dispatch);

        $validated = $this->validateDispatch($request, false);

        DB::transaction(function () use ($request, $assignmentId, $dispatch, $validated): void {
            $status = $this->dbStatus($validated['status'] ?? $dispatch->status);
            $now = now();
            $updates = [
                'status' => $status,
                'assigned_area' => $validated['assigned_area'] ?? $dispatch->assigned_area,
                'priority_level' => $validated['priority_level'] ?? $dispatch->priority_level,
                'dispatch_notes' => $validated['dispatch_notes'] ?? $dispatch->dispatch_notes,
                'route_notes' => $this->routeNotesJson($validated, $dispatch->route_notes),
                'outcome_notes' => $this->outcomesJson($validated, $dispatch->outcome_notes),
                'updated_at' => $now,
            ];

            if (in_array($status, ['accepted', 'en_route'], true) && ! $dispatch->accepted_at) {
                $updates['accepted_at'] = $now;
            }

            if (in_array($status, ['dispatched', 'en_route'], true) && ! $dispatch->en_route_at) {
                $updates['en_route_at'] = $now;
            }

            if ($status === 'on_scene' && ! $dispatch->arrived_at) {
                $updates['arrived_at'] = $now;
            }

            if ($status === 'completed' && ! $dispatch->completed_at) {
                $updates['completed_at'] = $now;
            }

            DB::table('responder_assignments')
                ->where('assignment_id', $assignmentId)
                ->update($updates);

            $this->updateResponderDuty($dispatch->responder_id, $dispatch->team_id, $status);
            $this->writeAuditLog($request, 'update_dispatch', 'responder_assignments', (string) $assignmentId, $this->formatDispatch($dispatch), $validated);
        });

        return response()->json([
            'message' => 'Dispatch assignment updated.',
            'data' => $this->getDispatchById($assignmentId),
        ]);
    }

    public function complete(Request $request, int $assignmentId): JsonResponse
    {
        $dispatch = $this->getDispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'safe_count' => ['nullable', 'integer', 'min:0'],
            'evacuated_count' => ['nullable', 'integer', 'min:0'],
            'unsafe_count' => ['nullable', 'integer', 'min:0'],
            'injured_count' => ['nullable', 'integer', 'min:0'],
            'missing_count' => ['nullable', 'integer', 'min:0'],
            'pending_count' => ['nullable', 'integer', 'min:0'],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $assignmentId, $dispatch, $validated): void {
            DB::table('responder_assignments')
                ->where('assignment_id', $assignmentId)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'outcome_notes' => $this->outcomesJson($validated, $dispatch->outcome_notes),
                    'updated_at' => now(),
                ]);

            $this->updateResponderDuty($dispatch->responder_id, $dispatch->team_id, 'completed');
            $this->writeAuditLog($request, 'complete_dispatch', 'responder_assignments', (string) $assignmentId, $this->formatDispatch($dispatch), $validated);
        });

        return response()->json([
            'message' => 'Dispatch assignment completed.',
            'data' => $this->getDispatchById($assignmentId),
        ]);
    }

    public function updateLocation(Request $request, int $assignmentId): JsonResponse
    {
        $dispatch = $this->getDispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        $this->authorizeRescuerForDispatch($request, $dispatch);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $dispatch, $assignmentId, $validated): void {
            $now = now();
            $logId = $this->nextId('responder_location_logs', 'log_id');

            DB::table('responder_location_logs')->insert([
                'log_id' => $logId,
                'responder_id' => $dispatch->responder_id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'battery_level' => $validated['battery_level'] ?? null,
                'signal_strength' => $validated['signal_strength'] ?? null,
                'logged_at' => $now,
            ]);

            $this->saveRouteCoordinate($assignmentId, $validated, $now);

            DB::table('responders')
                ->where('responder_id', $dispatch->responder_id)
                ->update([
                    'last_active_at' => $now,
                    'duty_status' => 'deployed',
                    'updated_at' => $now,
                ]);

            $this->writeAuditLog($request, 'update_responder_location', 'responder_location_logs', (string) $logId, null, [
                'assignment_id' => $assignmentId,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ]);
        });

        return response()->json([
            'message' => 'Rescuer location updated.',
        ]);
    }

    private function applyDispatchFilters($query, Request $request): void
    {
        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        if ($status !== '' && $status !== 'all') {
            $query->where('ra.status', $this->dbStatus($status));
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('ra.assignment_code', 'like', "%{$search}%")
                    ->orWhere('ra.assigned_area', 'like', "%{$search}%")
                    ->orWhere('rt.team_name', 'like', "%{$search}%")
                    ->orWhere('r.full_name', 'like', "%{$search}%");
            });
        }
    }

    private function validateDispatch(Request $request, bool $isCreate = true): array
    {
        $required = $isCreate ? 'required' : 'sometimes';

        return $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:rescue_teams,team_id'],
            'responder_id' => ['nullable', 'integer', 'exists:responders,responder_id'],
            'household_id' => ['nullable', 'string', 'max:255'],
            'assigned_area' => [$required, 'string', 'max:150'],
            'households_to_cover' => ['nullable', 'integer', 'min:0'],
            'responder_count' => ['nullable', 'integer', 'min:1'],
            'priority_level' => [$required, 'string', 'in:critical,high,watch,monitor'],
            'status' => [$required, 'string', 'in:standby,dispatched,en_route,on_scene,onscene,returning,completed,cancelled'],
            'dispatch_notes' => ['nullable', 'string', 'max:1000'],
            'route_notes' => ['nullable', 'string', 'max:1000'],
            'safe_count' => ['nullable', 'integer', 'min:0'],
            'evacuated_count' => ['nullable', 'integer', 'min:0'],
            'unsafe_count' => ['nullable', 'integer', 'min:0'],
            'injured_count' => ['nullable', 'integer', 'min:0'],
            'missing_count' => ['nullable', 'integer', 'min:0'],
            'pending_count' => ['nullable', 'integer', 'min:0'],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'assigned_area.required' => 'Assigned area is required.',
            'priority_level.required' => 'Priority level is required.',
            'status.required' => 'Dispatch status is required.',
        ]);
    }

    private function getDispatchById(int $assignmentId): array
    {
        return $this->formatDispatch($this->getDispatchRecord($assignmentId));
    }

    private function getDispatchRecord(int $assignmentId): ?object
    {
        return DB::table('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->where('ra.assignment_id', $assignmentId)
            ->select([
                'ra.*',
                'rt.team_name',
                'rt.team_code',
                'rt.team_type',
                'r.full_name as responder_name',
                'r.contact_number as responder_contact',
                'r.user_id as responder_user_id',
            ])
            ->first();
    }

    private function formatDispatch(?object $dispatch): array
    {
        if (! $dispatch) {
            return [];
        }

        $route = $this->decodeJson($dispatch->route_notes);
        $outcomes = $this->decodeJson($dispatch->outcome_notes);
        $status = $this->formatStatus($dispatch->status);

        return [
            'assignment_id' => $dispatch->assignment_id,
            'assignment_code' => $dispatch->assignment_code,
            'team_id' => $dispatch->team_id,
            'team_name' => $dispatch->team_name ?: 'Responder assignment',
            'team_code' => $dispatch->team_code,
            'team_type' => $dispatch->team_type ?: 'Response team',
            'responder_id' => $dispatch->responder_id,
            'responder_name' => $dispatch->responder_name ?: 'Assigned responder',
            'responder_contact' => $dispatch->responder_contact,
            'household_id' => $dispatch->household_id,
            'assigned_area' => $dispatch->assigned_area,
            'priority_level' => $dispatch->priority_level ?: 'monitor',
            'priority_label' => $this->label($dispatch->priority_level ?: 'monitor'),
            'status' => $status,
            'dispatch_notes' => $dispatch->dispatch_notes,
            'route_notes' => $route['route_notes'] ?? $dispatch->route_notes,
            'households_to_cover' => (int) ($route['households_to_cover'] ?? ($dispatch->household_id ? 1 : 0)),
            'responder_count' => (int) ($route['responder_count'] ?? $this->teamResponderCount($dispatch->team_id, $dispatch->responder_id)),
            'outcomes' => $this->formatOutcomes($outcomes),
            'assigned_at' => $this->formatDateTime($dispatch->assigned_at),
            'assigned_time' => $this->formatTime($dispatch->assigned_at),
            'accepted_at' => $this->formatDateTime($dispatch->accepted_at),
            'en_route_at' => $this->formatDateTime($dispatch->en_route_at),
            'arrived_at' => $this->formatDateTime($dispatch->arrived_at),
            'completed_at' => $this->formatDateTime($dispatch->completed_at),
            'coverage_percent' => $this->coveragePercent($outcomes, (int) ($route['households_to_cover'] ?? 0)),
        ];
    }

    private function getTeamCards(?string $eventId)
    {
        $teams = DB::table('rescue_teams as rt')
            ->leftJoin('responders as leader', 'leader.responder_id', '=', 'rt.leader_responder_id')
            ->orderBy('rt.team_name')
            ->get([
                'rt.team_id',
                'rt.team_code',
                'rt.team_name',
                'rt.team_type',
                'rt.duty_status',
                'leader.full_name as leader_name',
            ]);

        $cards = $teams->map(fn (object $team): array => $this->formatTeamCard($team, $eventId))->values();

        if ($cards->isEmpty()) {
            $unassignedResponders = DB::table('responders')
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('team_id')->orWhere('team_id', 0);
                })
                ->count();

            if ($unassignedResponders > 0) {
                $availableResponderId = $this->availableResponderQuery()
                    ->where(function ($query): void {
                        $query->whereNull('r.team_id')->orWhere('r.team_id', 0);
                    })
                    ->value('r.responder_id');

                $cards->push([
                    'team_id' => null,
                    'team_code' => 'POOL',
                    'team_name' => 'Unassigned responder pool',
                    'team_type' => 'Responder pool',
                    'status_key' => 'standby',
                    'status_label' => 'Stand-by',
                    'leader_name' => 'No team leader assigned',
                    'member_count' => $unassignedResponders,
                    'assigned_households' => 0,
                    'assigned_area' => 'No dispatch area yet',
                    'active_assignment_id' => null,
                    'active_responder_id' => $availableResponderId ? (int) $availableResponderId : null,
                    'available_responder_id' => $availableResponderId ? (int) $availableResponderId : null,
                    'is_available' => (bool) $availableResponderId,
                    'outcomes' => $this->formatOutcomes([]),
                    'coverage_percent' => 0,
                    'assigned_time' => null,
                ]);
            }
        }

        return $cards;
    }

    private function formatTeamCard(object $team, ?string $eventId): array
    {
        $activeAssignment = null;

        if ($eventId) {
            $activeAssignment = DB::table('responder_assignments')
                ->where('team_id', $team->team_id)
                ->where('disaster_id', $eventId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderByDesc('assigned_at')
                ->first();
        }

        $memberCount = DB::table('responders')
            ->where('team_id', $team->team_id)
            ->whereNull('deleted_at')
            ->count();

        $outcomes = $this->decodeJson($activeAssignment?->outcome_notes);
        $route = $this->decodeJson($activeAssignment?->route_notes);
        $status = $this->formatStatus($activeAssignment?->status ?: $team->duty_status);
        $availableResponderId = $this->availableResponderQuery()
            ->where('r.team_id', $team->team_id)
            ->orderBy('r.responder_id')
            ->value('r.responder_id');

        return [
            'team_id' => $team->team_id,
            'team_code' => $team->team_code,
            'team_name' => $team->team_name ?: 'Unnamed team',
            'team_type' => $team->team_type ?: 'Response team',
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'leader_name' => $team->leader_name ?: 'No team leader assigned',
            'member_count' => $memberCount,
            'assigned_households' => (int) ($route['households_to_cover'] ?? 0),
            'assigned_area' => $activeAssignment?->assigned_area ?: 'No dispatch area yet',
            'active_assignment_id' => $activeAssignment?->assignment_id,
            'active_responder_id' => $activeAssignment?->responder_id,
            'available_responder_id' => $availableResponderId ? (int) $availableResponderId : null,
            'is_available' => ! $activeAssignment && (bool) $availableResponderId,
            'outcomes' => $this->formatOutcomes($outcomes),
            'coverage_percent' => $this->coveragePercent($outcomes, (int) ($route['households_to_cover'] ?? 0)),
            'assigned_time' => $this->formatTime($activeAssignment?->assigned_at),
        ];
    }

    private function getResponders()
    {
        return DB::table('responders as r')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id')
            ->whereNull('r.deleted_at')
            ->orderBy('r.full_name')
            ->get([
                'r.responder_id',
                'r.user_id',
                'r.team_id',
                'r.full_name',
                'r.title',
                'r.contact_number',
                'r.duty_status',
                'r.is_deployed',
                'r.last_active_at',
                'rt.team_name',
                'rt.team_code',
            ])
            ->map(function (object $responder): array {
                $activeAssignment = $this->activeAssignmentForResponder((int) $responder->responder_id);
                $status = $activeAssignment
                    ? $this->formatStatus($activeAssignment->status)
                    : $this->formatStatus($responder->duty_status);
                $dutyKey = $this->statusKey($responder->duty_status);
                $isAvailable = ! $activeAssignment
                    && ! (bool) $responder->is_deployed
                    && ! in_array($dutyKey, ['deployed', 'dispatched', 'accepted', 'en_route', 'on_scene', 'off_duty'], true);

                return [
                    'responder_id' => $responder->responder_id,
                    'user_id' => $responder->user_id,
                    'team_id' => $responder->team_id,
                    'full_name' => $responder->full_name ?: 'Unnamed responder',
                    'title' => $responder->title ?: 'Responder',
                    'contact_number' => $responder->contact_number,
                    'team_name' => $responder->team_name ?: 'Unassigned',
                    'team_code' => $responder->team_code,
                    'status' => $status,
                    'is_available' => $isAvailable,
                    'active_assignment_id' => $activeAssignment?->assignment_id,
                    'active_assignment_code' => $activeAssignment?->assignment_code,
                    'active_assigned_area' => $activeAssignment?->assigned_area,
                    'last_active_at' => $this->formatDateTime($responder->last_active_at),
                ];
            })
            ->values();
    }

    private function getDispatchSummary(?string $eventId): array
    {
        $totalTeams = DB::table('rescue_teams')->count();
        $statusCounts = collect();

        if ($eventId) {
            $statusCounts = DB::table('responder_assignments')
                ->where('disaster_id', $eventId)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
        }

        $dispatched = $this->sumKeys($statusCounts, ['dispatched', 'en_route', 'accepted']);
        $onScene = $this->sumKeys($statusCounts, ['on_scene', 'onscene']);
        $completed = $this->sumKeys($statusCounts, ['completed']);
        $standby = DB::table('rescue_teams')->where('duty_status', 'standby')->count();
        $activeUnits = $dispatched + $onScene;
        $responseRate = $totalTeams > 0 ? round(($activeUnits / $totalTeams) * 100) : 0;

        return [
            'total_teams' => $totalTeams,
            'dispatched' => $dispatched,
            'on_scene' => $onScene,
            'standby' => $standby,
            'completed' => $completed,
            'response_rate' => $responseRate,
            'active_units' => $activeUnits,
        ];
    }

    private function getRiskAreas(?string $eventId)
    {
        if (! $eventId) {
            return collect();
        }

        $rows = DB::table('households as h')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_disasters as hd', function ($join) use ($eventId): void {
                $join->on('hd.household_id', '=', 'h.household_id')
                    ->where('hd.disaster_id', '=', $eventId);
            })
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->whereNull('h.deleted_at')
            ->whereNotNull('h.household_id')
            ->select([
                'h.household_id',
                'h.household_code',
                'h.household_name',
                'a.full_address',
                'a.street_address',
                'a.house_number',
                DB::raw("COALESCE(NULLIF(a.purok_sitio, ''), NULLIF(a.barangay_name, ''), 'Unassigned') as area_name"),
                'hs.status_key',
                'hs.status_label',
                'hd.needs_dispatch',
                'hd.priority_level',
                'hd.last_battery_level',
                'hd.last_reported_at',
                DB::raw("EXISTS (
                    SELECT 1 FROM geotagged_locations gl
                    WHERE gl.household_id = h.household_id
                    AND gl.latitude IS NOT NULL
                    AND gl.longitude IS NOT NULL
                ) as has_geotag"),
            ])
            ->get();

        $busyHouseholdIds = DB::table('responder_assignments')
            ->where('disaster_id', $eventId)
            ->whereNotNull('household_id')
            ->whereIn('status', $this->activeAssignmentStatuses())
            ->pluck('assignment_id', 'household_id');

        return $rows
            ->groupBy('area_name')
            ->map(function ($items, string $area) use ($busyHouseholdIds): array {
                $total = $items->count();
                $unsafe = $items->filter(fn (object $item): bool => in_array($item->status_key, ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured'], true) || (bool) $item->needs_dispatch)->count();
                $unchecked = $items->whereNull('status_key')->count();
                $toCover = $unsafe + $unchecked;
                $priority = $unsafe > 0 ? 'critical' : ($unchecked > 0 ? 'high' : 'watch');
                $households = $items
                    ->sortBy(fn (object $item): string => sprintf(
                        '%d%d%s',
                        $item->needs_dispatch ? 0 : 1,
                        $item->has_geotag ? 0 : 1,
                        strtolower($item->household_name ?: $item->household_id)
                    ))
                    ->map(fn (object $item): array => $this->formatRiskHousehold($item, $busyHouseholdIds))
                    ->values();

                return [
                    'id' => str($area)->slug('-')->toString() ?: 'unassigned',
                    'area_name' => $area,
                    'zone' => $area,
                    'context' => $unsafe > 0 ? 'Unsafe households need dispatch focus.' : 'Unchecked households need field verification.',
                    'priority' => $priority,
                    'priority_label' => $this->label($priority),
                    'total_households' => $total,
                    'geotagged_households' => $households->where('has_geotag', true)->count(),
                    'unsafe_households' => $unsafe,
                    'unchecked_households' => $unchecked,
                    'to_cover' => $toCover,
                    'households' => $households,
                    'recommended_households' => $households
                        ->filter(fn (array $item): bool => $item['needs_dispatch'] || $item['status_key'] === 'unchecked' || in_array($item['status_key'], ['not_evacuated', 'displaced', 'unsafe', 'missing', 'injured'], true))
                        ->take(10)
                        ->values(),
                ];
            })
            ->filter(fn (array $area): bool => $area['to_cover'] > 0)
            ->sortByDesc('to_cover')
            ->values();
    }

    private function getDispatchActivity(?string $eventId)
    {
        if (! $eventId) {
            return collect();
        }

        return DB::table('responder_assignments as ra')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id')
            ->leftJoin('responders as r', 'r.responder_id', '=', 'ra.responder_id')
            ->where('ra.disaster_id', $eventId)
            ->orderByDesc('ra.updated_at')
            ->orderByDesc('ra.assigned_at')
            ->limit(10)
            ->get([
                'ra.assignment_id',
                'ra.status',
                'ra.assigned_area',
                'ra.assigned_at',
                'ra.updated_at',
                'rt.team_name',
                'r.full_name',
            ])
            ->map(fn (object $log): array => [
                'assignment_id' => $log->assignment_id,
                'time' => $this->formatTime($log->updated_at ?? $log->assigned_at),
                'team_name' => $log->team_name ?: $log->full_name ?: 'Responder',
                'status' => $this->formatStatus($log->status),
                'assigned_area' => $log->assigned_area,
            ])
            ->values();
    }

    private function getRoute(int $assignmentId): ?array
    {
        $route = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('created_at')
            ->first();

        if (! $route) {
            return null;
        }

        $coordinates = DB::table('route_coordinates')
            ->where('route_id', $route->route_id)
            ->orderBy('sequence_order')
            ->get(['latitude', 'longitude', 'sequence_order', 'recorded_at', 'accuracy_m']);

        return [
            'route_id' => $route->route_id,
            'route_name' => $route->route_name,
            'route_status' => $route->route_status,
            'estimated_distance_km' => $route->estimated_distance_km,
            'estimated_duration_min' => $route->estimated_duration_min,
            'coordinates' => $coordinates,
        ];
    }

    private function saveRouteCoordinate(int $assignmentId, array $validated, Carbon $now): void
    {
        $route = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('created_at')
            ->first();

        if (! $route) {
            $routeId = $this->nextId('responder_routes', 'route_id');

            DB::table('responder_routes')->insert([
                'route_id' => $routeId,
                'assignment_id' => $assignmentId,
                'route_name' => 'Responder mobile route',
                'route_status' => 'active',
                'start_latitude' => $validated['latitude'],
                'start_longitude' => $validated['longitude'],
                'end_latitude' => null,
                'end_longitude' => null,
                'estimated_distance_km' => null,
                'estimated_duration_min' => null,
                'route_polyline' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $route = (object) ['route_id' => $routeId];
        }

        $nextOrder = ((int) DB::table('route_coordinates')->where('route_id', $route->route_id)->max('sequence_order')) + 1;

        DB::table('route_coordinates')->insert([
            'coordinate_id' => $this->nextId('route_coordinates', 'coordinate_id'),
            'route_id' => $route->route_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'sequence_order' => $nextOrder,
            'recorded_at' => $now,
            'accuracy_m' => $validated['accuracy_m'] ?? null,
        ]);
    }

    private function resolveResponderId(array $validated): ?int
    {
        if (! empty($validated['responder_id'])) {
            return (int) $validated['responder_id'];
        }

        if (empty($validated['team_id'])) {
            return null;
        }

        $leaderId = DB::table('rescue_teams')
            ->where('team_id', $validated['team_id'])
            ->value('leader_responder_id');

        if ($leaderId) {
            $leaderIsAvailable = $this->availableResponderQuery()
                ->where('r.responder_id', $leaderId)
                ->exists();

            if ($leaderIsAvailable) {
                return (int) $leaderId;
            }
        }

        return $this->availableResponderQuery()
            ->where('r.team_id', $validated['team_id'])
            ->orderBy('r.responder_id')
            ->value('r.responder_id');
    }

    private function authorizeRescuerForDispatch(Request $request, object $dispatch): void
    {
        $roleKey = $request->user()?->role?->role_key;

        if ($roleKey !== 'rescuer') {
            return;
        }

        $responder = DB::table('responders')
            ->where('user_id', $request->user()?->user_id)
            ->orWhere('username', $request->user()?->username)
            ->first();

        if (! $responder || (int) $responder->responder_id !== (int) $dispatch->responder_id) {
            abort(response()->json([
                'message' => 'This dispatch is not assigned to your responder account.',
            ], 403));
        }
    }

    private function updateResponderDuty(int $responderId, ?int $teamId, string $status): void
    {
        $isActive = in_array($status, ['accepted', 'dispatched', 'en_route', 'on_scene'], true);
        $now = now();

        DB::table('responders')
            ->where('responder_id', $responderId)
            ->update([
                'is_deployed' => $isActive,
                'duty_status' => $isActive ? 'deployed' : 'available',
                'last_active_at' => $now,
                'updated_at' => $now,
            ]);

        if ($teamId) {
            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->update([
                    'duty_status' => $isActive ? $status : 'standby',
                    'updated_at' => $now,
                ]);
        }
    }

    private function activeAssignmentStatuses(): array
    {
        return ['dispatched', 'accepted', 'en_route', 'on_scene', 'onscene', 'returning'];
    }

    private function activeAssignmentForResponder(int $responderId): ?object
    {
        return DB::table('responder_assignments')
            ->where('responder_id', $responderId)
            ->whereIn('status', $this->activeAssignmentStatuses())
            ->orderByDesc('assigned_at')
            ->first();
    }

    private function availableResponderQuery()
    {
        return DB::table('responders as r')
            ->whereNull('r.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('r.is_deployed')->orWhere('r.is_deployed', false);
            })
            ->where(function ($query): void {
                $query->whereNull('r.duty_status')
                    ->orWhereNotIn('r.duty_status', ['deployed', 'dispatched', 'accepted', 'en_route', 'on_scene', 'off_duty']);
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('responder_assignments as active_ra')
                    ->whereColumn('active_ra.responder_id', 'r.responder_id')
                    ->whereIn('active_ra.status', $this->activeAssignmentStatuses());
            });
    }

    private function ensureResponderCanReceiveDispatch(int $responderId): void
    {
        $activeAssignment = $this->activeAssignmentForResponder($responderId);

        if (! $activeAssignment) {
            return;
        }

        throw ValidationException::withMessages([
            'responder_id' => [
                'This responder already has an active dispatch ('.$activeAssignment->assignment_code.'). Complete or cancel that assignment before assigning another one.',
            ],
        ]);
    }

    private function ensureHouseholdCanReceiveDispatch(?string $householdId): void
    {
        if (! $householdId) {
            return;
        }

        $householdExists = DB::table('households')
            ->where('household_id', $householdId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $householdExists) {
            throw ValidationException::withMessages([
                'household_id' => ['Select a valid household from the affected area list.'],
            ]);
        }

        $hasGeotag = DB::table('geotagged_locations')
            ->where('household_id', $householdId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->exists();

        if (! $hasGeotag) {
            throw ValidationException::withMessages([
                'household_id' => ['Select a household with saved GPS coordinates. Routing cannot be generated for households without a geotag.'],
            ]);
        }

        $activeAssignment = DB::table('responder_assignments')
            ->where('household_id', $householdId)
            ->whereIn('status', $this->activeAssignmentStatuses())
            ->orderByDesc('assigned_at')
            ->first();

        if (! $activeAssignment) {
            return;
        }

        throw ValidationException::withMessages([
            'household_id' => [
                'This household is already assigned to '.$activeAssignment->assignment_code.'. Complete or cancel that dispatch before creating another one for the same household.',
            ],
        ]);
    }

    private function formatRiskHousehold(object $item, $busyHouseholdIds): array
    {
        $statusKey = $item->status_key ?: 'unchecked';
        $busyAssignmentId = $busyHouseholdIds[$item->household_id] ?? null;

        return [
            'household_id' => $item->household_id,
            'household_code' => $item->household_code,
            'household_name' => $item->household_name ?: $item->household_code ?: $item->household_id,
            'address' => $item->full_address ?: trim(($item->house_number ? $item->house_number.' ' : '').($item->street_address ?: '')),
            'status_key' => $statusKey,
            'status_label' => $item->status_label ?: 'Unchecked',
            'priority_level' => $item->priority_level ?: 'watch',
            'needs_dispatch' => (bool) $item->needs_dispatch,
            'has_geotag' => (bool) $item->has_geotag,
            'last_battery_level' => $item->last_battery_level,
            'last_reported_at' => $this->formatDateTime($item->last_reported_at),
            'active_assignment_id' => $busyAssignmentId ? (int) $busyAssignmentId : null,
            'is_available_for_dispatch' => ! $busyAssignmentId,
        ];
    }

    private function routeNotesJson(array $validated, ?string $existing = null): string
    {
        $current = $this->decodeJson($existing);

        return json_encode(array_merge($current, [
            'households_to_cover' => (int) ($validated['households_to_cover'] ?? ($current['households_to_cover'] ?? 0)),
            'responder_count' => (int) ($validated['responder_count'] ?? ($current['responder_count'] ?? 1)),
            'route_notes' => $validated['route_notes'] ?? ($current['route_notes'] ?? null),
        ]));
    }

    private function outcomesJson(array $validated, ?string $existing = null): string
    {
        $current = $this->decodeJson($existing);

        return json_encode(array_merge($current, [
            'safe_count' => (int) ($validated['safe_count'] ?? ($current['safe_count'] ?? 0)),
            'evacuated_count' => (int) ($validated['evacuated_count'] ?? ($current['evacuated_count'] ?? 0)),
            'unsafe_count' => (int) ($validated['unsafe_count'] ?? ($current['unsafe_count'] ?? 0)),
            'injured_count' => (int) ($validated['injured_count'] ?? ($current['injured_count'] ?? 0)),
            'missing_count' => (int) ($validated['missing_count'] ?? ($current['missing_count'] ?? 0)),
            'pending_count' => (int) ($validated['pending_count'] ?? ($current['pending_count'] ?? 0)),
            'outcome_notes' => $validated['outcome_notes'] ?? ($current['outcome_notes'] ?? null),
        ]));
    }

    private function formatOutcomes(array $outcomes): array
    {
        return [
            'safe' => (int) ($outcomes['safe_count'] ?? 0),
            'evacuated' => (int) ($outcomes['evacuated_count'] ?? 0),
            'unsafe' => (int) ($outcomes['unsafe_count'] ?? 0),
            'injured' => (int) ($outcomes['injured_count'] ?? 0),
            'missing' => (int) ($outcomes['missing_count'] ?? 0),
            'pending' => (int) ($outcomes['pending_count'] ?? 0),
            'notes' => $outcomes['outcome_notes'] ?? null,
        ];
    }

    private function coveragePercent(array $outcomes, int $assignedHouseholds): int
    {
        if ($assignedHouseholds <= 0) {
            return 0;
        }

        $reported = (int) ($outcomes['safe_count'] ?? 0)
            + (int) ($outcomes['evacuated_count'] ?? 0)
            + (int) ($outcomes['unsafe_count'] ?? 0)
            + (int) ($outcomes['injured_count'] ?? 0)
            + (int) ($outcomes['missing_count'] ?? 0);

        return min(100, round(($reported / $assignedHouseholds) * 100));
    }

    private function formatStatus(?string $status): array
    {
        $key = $this->statusKey($status ?: 'standby');

        return [
            'key' => $key,
            'label' => match ($key) {
                'on_scene' => 'On-scene',
                'en_route' => 'En route',
                'dispatched' => 'Dispatched',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'available' => 'Available',
                'deployed' => 'Deployed',
                default => 'Stand-by',
            },
            'tone' => match ($key) {
                'on_scene', 'completed', 'available' => 'green',
                'dispatched', 'en_route', 'accepted', 'deployed' => 'purple',
                'cancelled' => 'red',
                default => 'gray',
            },
        ];
    }

    private function statusKey(?string $status): string
    {
        return match ($status) {
            'onscene', 'on-scene' => 'on_scene',
            'en-route' => 'en_route',
            'off_duty', 'off-duty', 'stand-by' => 'standby',
            null, '' => 'standby',
            default => str_replace('-', '_', $status),
        };
    }

    private function dbStatus(?string $status): string
    {
        return $this->statusKey($status);
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

    private function nextId(string $table, string $column): int
    {
        return ((int) DB::table($table)->lockForUpdate()->max($column)) + 1;
    }

    private function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sumKeys($counts, array $keys): int
    {
        return collect($keys)->sum(fn (string $key): int => (int) ($counts[$key] ?? 0));
    }

    private function teamResponderCount(?int $teamId, ?int $responderId): int
    {
        if ($teamId) {
            return DB::table('responders')
                ->where('team_id', $teamId)
                ->whereNull('deleted_at')
                ->count();
        }

        return $responderId ? 1 : 0;
    }

    private function label(?string $value): string
    {
        return str($value ?? '')
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function formatDateTime(?string $dateTime): ?string
    {
        return $dateTime ? Carbon::parse($dateTime)->format('M d, Y h:i A') : null;
    }

    private function formatTime(?string $dateTime): ?string
    {
        return $dateTime ? Carbon::parse($dateTime)->format('h:i A') : null;
    }

    private function writeAuditLog(Request $request, string $action, string $table, string $referenceId, mixed $oldValues, mixed $newValues): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'rescue_dispatch',
            'action' => $action,
            'reference_table' => $table,
            'reference_id' => $referenceId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
