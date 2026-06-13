<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RescuerMobileService
{
    private RoutingService $routingService;

    public function __construct(RoutingService $routingService)
    {
        $this->routingService = $routingService;
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()?->load('role');
        $responder = $this->responderForUser($user);

        return response()->json([
            'data' => [
                'user' => $this->formatUser($user),
                'responder' => $this->formatResponder($responder),
                'active_assignment' => $this->activeAssignment($responder?->responder_id),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user()?->load('role');
        $responder = $this->responderForUser($user);

        if (! $user || ! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:1000'],
            'skills' => ['nullable', 'string', 'max:1000'],
        ], [
            'username.required' => 'Username is required.',
            'username.min' => 'Username must have at least 3 characters.',
            'username.regex' => 'Username can only use letters, numbers, dot, underscore, or dash.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'contact_number.required' => 'Mobile number is required.',
        ]);

        $username = $this->normalizeUsername($validated['username']);
        $this->ensureUniqueMobileUsername($username, $user->user_id);

        $now = now();
        $fullName = $this->fullNameFromProfile($validated);

        DB::transaction(function () use ($request, $user, $responder, $validated, $username, $fullName, $now): void {
            DB::table('users')
                ->where('user_id', $user->user_id)
                ->update($this->filterColumns('users', [
                    'first_name' => trim($validated['first_name']),
                    'last_name' => trim($validated['last_name']),
                    'name' => $fullName,
                    'username' => $username,
                    'email' => $validated['email'] ?? null,
                    'contact_number' => $validated['contact_number'],
                    'updated_at' => $now,
                ]));

            DB::table('responders')
                ->where('responder_id', $responder->responder_id)
                ->update($this->filterColumns('responders', [
                    'full_name' => $fullName,
                    'contact_number' => $validated['contact_number'],
                    'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                    'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
                    'blood_type' => $validated['blood_type'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'skills' => $validated['skills'] ?? null,
                    'updated_at' => $now,
                ]));

            $this->writeAuditLog($request, 'mobile_update_profile', 'responders', (string) $responder->responder_id, [
                'username' => $username,
                'full_name' => $fullName,
            ]);
        });

        $freshUser = $request->user()?->fresh('role');
        $freshResponder = $this->responderForUser($freshUser);

        return response()->json([
            'message' => 'Profile updated.',
            'data' => [
                'user' => $this->formatUser($freshUser),
                'responder' => $this->formatResponder($freshResponder),
                'active_assignment' => $this->activeAssignment($freshResponder?->responder_id),
            ],
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user()?->load('role');
        $responder = $this->responderForUser($user);
        $assignments = $this->assignmentList($responder?->responder_id, 10);

        return response()->json([
            'data' => [
                'profile' => [
                    'user' => $this->formatUser($user),
                    'responder' => $this->formatResponder($responder),
                ],
                'active_event' => $this->activeEvent(),
                'summary' => $this->summary($assignments),
                'assignments' => $assignments,
                'field_reports' => $this->fieldReportList($responder?->responder_id, $user?->user_id),
                'resource_requests' => $this->resourceRequestList($user?->user_id, 8),
                'status_options' => $this->householdStatusOptions(),
                'category_options' => $this->resourceCategoryOptions(),
            ],
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $responder = $this->responderForUser($request->user());

        return response()->json([
            'data' => [
                'assignments' => $this->assignmentList($responder?->responder_id, 30),
            ],
        ]);
    }

    public function assignment(Request $request, int $assignmentId): JsonResponse
    {
        $responder = $this->responderForUser($request->user());
        $assignment = $this->findAssignment($assignmentId, $responder?->responder_id);

        if (! $assignment) {
            return response()->json([
                'message' => 'Assignment was not found for your rescuer account.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'assignment' => $this->formatAssignment($assignment),
                'route' => $this->routeForAssignment($assignmentId),
            ],
        ]);
    }

    public function updateAssignmentStatus(Request $request, int $assignmentId): JsonResponse
    {
        if (! Schema::hasTable('responder_assignments')) {
            return $this->missingTableResponse('responder_assignments');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'en_route', 'on_scene', 'completed', 'cancelled'])],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ], [
            'status.required' => 'Select the mission status.',
            'status.in' => 'Select a valid mission status.',
        ]);

        $responder = $this->responderForUser($request->user());
        $assignment = $this->findAssignment($assignmentId, $responder?->responder_id);

        if (! $assignment) {
            return response()->json([
                'message' => 'Assignment was not found for your rescuer account.',
            ], 404);
        }

        $status = $validated['status'];
        $now = now();
        $updates = [
            'status' => $status,
            'updated_at' => $now,
        ];

        if ($status === 'accepted' && ! $assignment->accepted_at) {
            $updates['accepted_at'] = $now;
        }

        if ($status === 'en_route' && ! $assignment->en_route_at) {
            $updates['en_route_at'] = $now;
        }

        if ($status === 'on_scene' && ! $assignment->arrived_at) {
            $updates['arrived_at'] = $now;
        }

        if ($status === 'completed' && ! $assignment->completed_at) {
            $updates['completed_at'] = $now;
        }

        if (array_key_exists('outcome_notes', $validated)) {
            $updates['outcome_notes'] = $this->mergeJson($assignment->outcome_notes, [
                'mobile_notes' => $validated['outcome_notes'],
            ]);
        }

        DB::table('responder_assignments')
            ->where('assignment_id', $assignmentId)
            ->update($updates);

        if ($this->hasPoint($validated)) {
            $this->saveResponderLocation($responder, $validated, $now);
            $this->saveRouteCoordinate($assignmentId, $validated, $now);
            $this->savePlannedRoadRoute($assignmentId, $assignment, $validated, $now);
        }

        $this->updateResponderDuty($responder?->responder_id, $responder?->team_id, $status);
        $this->writeAuditLog($request, 'mobile_update_assignment', 'responder_assignments', (string) $assignmentId, $updates);

        return response()->json([
            'message' => 'Assignment status updated.',
            'data' => [
                'assignment' => $this->formatAssignment($this->findAssignment($assignmentId, $responder?->responder_id)),
                'route' => $this->routeForAssignment($assignmentId),
            ],
        ]);
    }

    public function storeLocation(Request $request, int $assignmentId): JsonResponse
    {
        if (! Schema::hasTable('responder_location_logs')) {
            return $this->missingTableResponse('responder_location_logs');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ], [
            'latitude.required' => 'Latitude is required.',
            'longitude.required' => 'Longitude is required.',
        ]);

        $responder = $this->responderForUser($request->user());
        $assignment = $this->findAssignment($assignmentId, $responder?->responder_id);

        if (! $assignment) {
            return response()->json([
                'message' => 'Assignment was not found for your rescuer account.',
            ], 404);
        }

        $now = now();
        $logId = $this->saveResponderLocation($responder, $validated, $now);
        $this->saveRouteCoordinate($assignmentId, $validated, $now);
        $this->savePlannedRoadRoute($assignmentId, $assignment, $validated, $now);
        $this->updateResponderDuty($responder->responder_id, $responder->team_id, 'deployed');
        $this->writeAuditLog($request, 'mobile_location_update', 'responder_location_logs', (string) $logId, $validated);

        return response()->json([
            'message' => 'Location updated.',
        ]);
    }

    public function fieldReports(Request $request): JsonResponse
    {
        $user = $request->user();
        $responder = $this->responderForUser($user);

        return response()->json([
            'data' => [
                'reports' => $this->fieldReportList($responder?->responder_id, $user?->user_id),
                'status_options' => $this->householdStatusOptions(),
            ],
        ]);
    }

    public function storeFieldReport(Request $request): JsonResponse
    {
        if (! Schema::hasTable('household_status_logs')) {
            return $this->missingTableResponse('household_status_logs');
        }

        $activeEvent = $this->activeEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'Field reports can only be submitted after HQ declares an active disaster event.',
            ], 409);
        }

        $validated = $request->validate([
            'household_id' => ['required', 'string', 'max:255'],
            'household_head' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'status_key' => ['required', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'members' => ['nullable', 'array'],
        ], [
            'household_id.required' => 'Household ID is required.',
            'status_key.required' => 'Select the household status.',
        ]);

        $statusId = $this->resolveHouseholdStatusId($validated['status_key']);

        if (! $statusId) {
            throw ValidationException::withMessages([
                'status_key' => ['Selected status is not available in the database.'],
            ]);
        }

        if (Schema::hasTable('households') && ! DB::table('households')->where('household_id', $validated['household_id'])->exists()) {
            throw ValidationException::withMessages([
                'household_id' => ['Household ID was not found in the shared database.'],
            ]);
        }

        $user = $request->user();
        $responder = $this->responderForUser($user);
        $now = now();
        $statusLogId = $this->nextId('household_status_logs', 'status_log_id');

        DB::table('household_status_logs')->insert($this->filterColumns('household_status_logs', [
            'status_log_id' => $statusLogId,
            'disaster_id' => $activeEvent['event_id'],
            'household_id' => $validated['household_id'],
            'status_id' => $statusId,
            'source' => 'responder_field_report',
            'submitted_by_user_id' => $user?->user_id,
            'responder_id' => $responder?->responder_id,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'battery_level' => $validated['battery_level'] ?? null,
            'notes' => $this->fieldReportNotes($validated),
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->writeAuditLog($request, 'mobile_field_report', 'household_status_logs', (string) $statusLogId, $validated);

        return response()->json([
            'message' => 'Field report submitted to HQ.',
            'data' => [
                'status_log_id' => $statusLogId,
            ],
        ], 201);
    }

    public function resourceRequests(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'requests' => $this->resourceRequestList($request->user()?->user_id, 25),
                'category_options' => [
                    ['key' => 'resource', 'label' => 'Resource'],
                    ['key' => 'personnel', 'label' => 'Personnel'],
                    ['key' => 'vehicle', 'label' => 'Vehicle / Transport'],
                ],
            ],
        ]);
    }

    public function storeResourceRequest(Request $request): JsonResponse
    {
        if (! Schema::hasTable('resource_requests')) {
            return $this->missingTableResponse('resource_requests');
        }

        $validated = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'cluster' => ['nullable', 'string', 'max:100'],
            'request_category' => ['required', Rule::in(['resource', 'personnel', 'vehicle'])],
            'resource_type' => ['required', 'string', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'urgency_key' => ['nullable', 'string', 'max:50'],
        ], [
            'location.required' => 'Location is required.',
            'request_category.required' => 'Request type is required.',
            'resource_type.required' => 'Need/category is required.',
            'quantity.required' => 'Quantity is required.',
        ]);

        $user = $request->user();
        $responder = $this->responderForUser($user);
        $activeEvent = $this->activeEvent();
        $now = now();
        $requestId = $this->nextResourceRequestId();

        DB::table('resource_requests')->insert($this->filterColumns('resource_requests', [
            'request_id' => $requestId,
            'request_source' => 'rescuer_mobile',
            'source_reference' => $activeEvent['event_id'] ?? null,
            'request_category' => $validated['request_category'],
            'evacuation_center_id' => null,
            'requested_by' => $responder?->full_name ?? $user?->full_name ?? $user?->username ?? 'Rescuer mobile',
            'handled_by' => $user?->user_id,
            'resource_type' => $validated['resource_type'],
            'item_name' => $validated['item_name'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'] ?? null,
            'description' => $this->resourceDescription($validated),
            'urgency_id' => $this->urgencyId($validated['urgency_key'] ?? 'medium'),
            'status_id' => $this->resourceStatusId('needs_validation'),
            'validation_status' => 'needs_validation',
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->writeAuditLog($request, 'mobile_resource_request', 'resource_requests', $requestId, $validated);

        return response()->json([
            'message' => 'Resource request submitted for HQ validation.',
            'data' => [
                'request_id' => $requestId,
            ],
        ], 201);
    }

    public function cancelResourceRequest(Request $request, string $requestId): JsonResponse
    {
        if (! Schema::hasTable('resource_requests')) {
            return $this->missingTableResponse('resource_requests');
        }

        $query = DB::table('resource_requests')
            ->where('request_id', $requestId);

        if (Schema::hasColumn('resource_requests', 'handled_by')) {
            $query->where('handled_by', $request->user()?->user_id);
        }

        $resourceRequest = $query->first();

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found for your account.',
            ], 404);
        }

        if (($resourceRequest->validation_status ?? '') !== 'needs_validation') {
            return response()->json([
                'message' => 'Only requests still waiting for validation can be cancelled from mobile.',
            ], 409);
        }

        DB::table('resource_requests')
            ->where('request_id', $requestId)
            ->update($this->filterColumns('resource_requests', [
                'validation_status' => 'cancelled',
                'status_id' => $this->resourceStatusId('cancelled'),
                'updated_at' => now(),
            ]));

        $this->writeAuditLog($request, 'mobile_cancel_resource_request', 'resource_requests', $requestId, [
            'validation_status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Resource request cancelled.',
        ]);
    }

    public function radioFeed(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['nullable', Rule::in(['command', 'team', 'event'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $user = $request->user();
        $responder = $this->responderForUser($user);

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $channel = $validated['channel'] ?? 'team';
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = min(20, max(1, (int) ($validated['per_page'] ?? 5)));
        $logs = collect($this->radioRowsForScope($responder))
            ->filter(fn (array $log): bool => ($log['channel'] ?? 'team') === $channel)
            ->values();
        $total = $logs->count();
        $items = $logs->forPage($page, $perPage)->values()->all();
        $activeTransmission = $this->activeRadioTransmission($responder);

        return response()->json([
            'data' => [
                'channel' => $channel,
                'active_transmission' => $activeTransmission,
                'team_members' => $this->radioTeamMembers($responder, $logs, $activeTransmission),
                'logs' => [
                    'data' => $items,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'has_more' => ($page * $perPage) < $total,
                ],
                'audio_note' => 'Voice clips are saved and played through the team radio feed.',
            ],
        ]);
    }

    public function startRadioTransmission(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'assignment_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $responder = $this->responderForUser($user);

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $transmissionId = 'PTT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5));
        $message = $this->radioMessage($request, $responder, [
            'type' => 'ptt_start',
            'channel' => $validated['channel'],
            'transmission_id' => $transmissionId,
            'assignment_id' => $validated['assignment_id'] ?? null,
            'audio_status' => 'metadata_only_until_live_audio_server_is_connected',
        ]);

        $this->insertRadioLog($responder, $message);

        return response()->json([
            'message' => 'PTT transmission started.',
            'data' => [
                'transmission_id' => $transmissionId,
                'active_transmission' => $this->activeRadioTransmission($responder),
            ],
        ], 201);
    }

    public function heartbeatRadioTransmission(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'transmission_id' => ['required', 'string', 'max:80'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $responder = $this->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $this->insertRadioLog($responder, $this->radioMessage($request, $responder, [
            'type' => 'ptt_heartbeat',
            'channel' => $validated['channel'],
            'transmission_id' => $validated['transmission_id'],
            'duration_seconds' => $validated['duration_seconds'] ?? 0,
            'audio_status' => 'metadata_only_until_live_audio_server_is_connected',
        ]));

        return response()->json([
            'message' => 'PTT heartbeat saved.',
            'data' => [
                'active_transmission' => $this->activeRadioTransmission($responder),
            ],
        ]);
    }

    public function stopRadioTransmission(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'transmission_id' => ['required', 'string', 'max:80'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $responder = $this->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $this->insertRadioLog($responder, $this->radioMessage($request, $responder, [
            'type' => 'ptt_end',
            'channel' => $validated['channel'],
            'transmission_id' => $validated['transmission_id'],
            'duration_seconds' => $validated['duration_seconds'] ?? 0,
            'audio_status' => 'metadata_only_until_live_audio_server_is_connected',
        ]));

        return response()->json([
            'message' => 'PTT transmission stopped.',
            'data' => [
                'active_transmission' => $this->activeRadioTransmission($responder),
            ],
        ]);
    }

    public function storeRadioClip(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'assignment_id' => ['nullable', 'integer'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
            'audio' => ['required', 'file', 'max:15360'],
        ], [
            'audio.required' => 'Record a voice message before sending.',
            'audio.file' => 'The voice message file is invalid.',
        ]);

        $responder = $this->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $file = $request->file('audio');
        $transmissionId = 'PTT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5));
        $extension = strtolower($file->getClientOriginalExtension() ?: 'm4a');
        $safeExtension = in_array($extension, ['m4a', 'mp4', 'aac', 'wav', 'mp3', 'webm'], true) ? $extension : 'm4a';
        $path = $file->storeAs(
            'radio-ptt/' . now()->format('Y/m/d'),
            $transmissionId . '.' . $safeExtension,
            'public'
        );

        $message = $this->radioMessage($request, $responder, [
            'type' => 'ptt_audio',
            'channel' => $validated['channel'],
            'transmission_id' => $transmissionId,
            'assignment_id' => $validated['assignment_id'] ?? null,
            'duration_seconds' => (int) ($validated['duration_seconds'] ?? 0),
            'audio_path' => $path,
            'audio_status' => 'stored_voice_clip',
        ]);

        $communicationId = $this->insertRadioLog($responder, $message);
        $log = DB::table('responder_communication_logs')
            ->where('communication_id', $communicationId)
            ->first();

        return response()->json([
            'message' => 'Voice transmission sent.',
            'data' => [
                'log' => $log ? $this->formatRadioLog($log) : null,
                'active_transmission' => $this->activeRadioTransmission($responder),
            ],
        ], 201);
    }

    public function storeRadioSignal(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'signal' => ['required', Rule::in(['Copy', 'Need backup', 'On-scene', 'Clear'])],
        ]);

        $responder = $this->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $this->insertRadioLog($responder, $this->radioMessage($request, $responder, [
            'type' => 'quick_signal',
            'channel' => $validated['channel'],
            'signal' => $validated['signal'],
        ]));

        return response()->json([
            'message' => 'Radio signal saved.',
            'data' => [
                'active_transmission' => $this->activeRadioTransmission($responder),
            ],
        ], 201);
    }

    private function responderForUser($user): ?object
    {
        if (! $user || ! Schema::hasTable('responders')) {
            return null;
        }

        $query = DB::table('responders as r')
            ->where('r.user_id', $user->user_id);

        $columns = ['r.*'];

        if (Schema::hasTable('rescue_teams')) {
            $query->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id');
            $columns[] = 'rt.team_name';
            $columns[] = 'rt.team_code';
            $columns[] = 'rt.team_type';
        } else {
            $columns[] = DB::raw('NULL as team_name');
            $columns[] = DB::raw('NULL as team_code');
            $columns[] = DB::raw('NULL as team_type');
        }

        return $query->select($columns)->first();
    }

    private function activeEvent(): ?array
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DB::table('disaster_events as de');
        $columns = [
            'de.event_id',
            'de.name',
            'de.started_at',
        ];

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
            $columns[] = 'sl.severity_label';
            $columns[] = 'sl.severity_key';
        } else {
            $columns[] = DB::raw('NULL as severity_label');
            $columns[] = DB::raw('NULL as severity_key');
        }

        $event = $query
            ->orderByDesc('de.started_at')
            ->first($columns);

        if (! $event) {
            return null;
        }

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Monitoring',
            'severity_key' => $event->severity_key ?? 'medium',
            'started_at' => $event->started_at,
        ];
    }

    private function assignmentList(?int $responderId, int $limit): array
    {
        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
            return [];
        }

        return $this->assignmentQuery($responderId)
            ->orderByRaw("CASE WHEN ra.status IN ('accepted', 'dispatched', 'en_route', 'on_scene') THEN 0 ELSE 1 END")
            ->orderByDesc('ra.assigned_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => $this->formatAssignment($row))
            ->values()
            ->all();
    }

    private function findAssignment(int $assignmentId, ?int $responderId): ?object
    {
        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
            return null;
        }

        return $this->assignmentQuery($responderId)
            ->where('ra.assignment_id', $assignmentId)
            ->first();
    }

    private function assignmentQuery(int $responderId)
    {
        $query = DB::table('responder_assignments as ra')
            ->where('ra.responder_id', $responderId);

        $columns = ['ra.*'];

        if (Schema::hasTable('rescue_teams')) {
            $query->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'ra.team_id');
            $columns[] = 'rt.team_name';
            $columns[] = 'rt.team_code';
        } else {
            $columns[] = DB::raw('NULL as team_name');
            $columns[] = DB::raw('NULL as team_code');
        }

        if (Schema::hasTable('disaster_events')) {
            $query->leftJoin('disaster_events as de', 'de.event_id', '=', 'ra.disaster_id');
            $columns[] = 'de.name as event_name';
        } else {
            $columns[] = DB::raw('NULL as event_name');
        }

        if (Schema::hasTable('geotagged_locations')) {
            $query->leftJoin('geotagged_locations as gl', 'gl.household_id', '=', 'ra.household_id');
        }

        if (Schema::hasTable('geotagged_locations')) {
            $columns[] = 'gl.latitude as household_latitude';
            $columns[] = 'gl.longitude as household_longitude';
        } else {
            $columns[] = DB::raw('NULL as household_latitude');
            $columns[] = DB::raw('NULL as household_longitude');
        }

        return $query->select($columns);
    }

    private function activeAssignment(?int $responderId): ?array
    {
        return collect($this->assignmentList($responderId, 10))
            ->first(fn (array $assignment): bool => in_array($assignment['status_key'], ['accepted', 'dispatched', 'en_route', 'on_scene'], true));
    }

    private function routeForAssignment(int $assignmentId): ?array
    {
        if (! Schema::hasTable('responder_routes')) {
            return null;
        }

        $route = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('created_at')
            ->first();

        if (! $route) {
            return null;
        }

        $plannedCoordinates = $this->plannedRouteCoordinates($route->route_polyline ?? null);
        $trailCoordinates = Schema::hasTable('route_coordinates')
            ? DB::table('route_coordinates')
                ->where('route_id', $route->route_id)
                ->orderBy('sequence_order')
                ->get(['latitude', 'longitude', 'sequence_order', 'recorded_at', 'accuracy_m'])
                ->values()
                ->all()
            : [];

        return [
            'route_id' => $route->route_id,
            'route_status' => $route->route_status ?? 'active',
            'route_name' => $route->route_name ?? 'Road route',
            'distance_km' => $route->estimated_distance_km ?? null,
            'duration_min' => $route->estimated_duration_min ?? null,
            'coordinates' => $plannedCoordinates,
            'trail_coordinates' => $trailCoordinates,
        ];
    }

    private function fieldReportList(?int $responderId, ?string $userId): array
    {
        if (! Schema::hasTable('household_status_logs')) {
            return [];
        }

        $query = DB::table('household_status_logs as hsl');
        $columns = [
            $this->optionalColumnSelect('household_status_logs', 'status_log_id', 'status_log_id', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'household_id', 'household_id', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'latitude', 'latitude', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'longitude', 'longitude', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'battery_level', 'battery_level', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'notes', 'notes', 'hsl'),
            $this->optionalColumnSelect('household_status_logs', 'submitted_at', 'submitted_at', 'hsl'),
        ];

        if (Schema::hasColumn('household_status_logs', 'source')) {
            $query->where('hsl.source', 'responder_field_report');
        }

        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id');
            $columns[] = 'hs.status_key';
            $columns[] = $this->householdStatusLabelSelect('hs', 'status_label');
        } else {
            $columns[] = DB::raw('NULL as status_key');
            $columns[] = DB::raw('NULL as status_label');
        }

        if (Schema::hasTable('households')) {
            $query->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id');
            $columns[] = $this->optionalColumnSelect('households', 'household_code', 'household_code', 'h');
            $columns[] = $this->firstExistingColumnSelect('households', ['household_head_name', 'head_name', 'household_name'], 'household_head_name', 'h');
        } else {
            $columns[] = DB::raw('NULL as household_code');
            $columns[] = DB::raw('NULL as household_head_name');
        }

        if ($responderId && Schema::hasColumn('household_status_logs', 'responder_id')) {
            $query->where('hsl.responder_id', $responderId);
        } elseif ($userId && Schema::hasColumn('household_status_logs', 'submitted_by_user_id')) {
            $query->where('hsl.submitted_by_user_id', $userId);
        }

        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
            ? 'hsl.submitted_at'
            : 'hsl.status_log_id';

        return $query
            ->orderByDesc($orderColumn)
            ->limit(20)
            ->get($columns)
            ->map(fn (object $row): array => [
                'status_log_id' => $row->status_log_id,
                'household_id' => $row->household_id,
                'household_code' => $row->household_code ?? null,
                'household_head' => $row->household_head_name ?? 'Household',
                'status_key' => $row->status_key ?? 'reported',
                'status_label' => $row->status_label ?? 'Reported',
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'battery_level' => $row->battery_level,
                'notes' => $row->notes,
                'submitted_at' => $row->submitted_at,
            ])
            ->values()
            ->all();
    }

    private function resourceRequestList(?string $userId, int $limit): array
    {
        if (! Schema::hasTable('resource_requests')) {
            return [];
        }

        $query = DB::table('resource_requests');

        if (Schema::hasColumn('resource_requests', 'request_source')) {
            $query->where('request_source', 'rescuer_mobile');
        }

        if ($userId && Schema::hasColumn('resource_requests', 'handled_by')) {
            $query->where('handled_by', $userId);
        }

        return $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'request_id' => $row->request_id,
                'location' => ($row->evacuation_center_id ?? null) ?: $this->extractLocationFromDescription($row->description ?? null),
                'resource_type' => $row->resource_type,
                'item_name' => $row->item_name,
                'quantity' => $row->quantity,
                'unit' => $row->unit,
                'description' => $row->description,
                'validation_status' => $row->validation_status ?? 'needs_validation',
                'tracking_reference' => $row->tracking_reference ?? null,
                'created_at' => $row->created_at,
            ])
            ->values()
            ->all();
    }

    private function formatUser($user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'user_id' => $user->user_id,
            'full_name' => $user->full_name ?? $user->name ?? $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'display_username' => $user->username,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'role' => [
                'role_key' => $user->role?->role_key,
                'role_name' => $user->role?->role_name,
            ],
        ];
    }

    private function formatResponder(?object $responder): ?array
    {
        if (! $responder) {
            return null;
        }

        return [
            'responder_id' => $responder->responder_id,
            'account_id' => $responder->responder_code,
            'responder_code' => $responder->responder_code,
            'full_name' => $responder->full_name,
            'title' => $responder->title ?: 'Responder',
            'contact_number' => $responder->contact_number,
            'team_id' => $responder->team_id,
            'team_name' => $responder->team_name ?: 'Unassigned',
            'team_code' => $responder->team_code,
            'team_type' => $responder->team_type,
            'duty_status' => $responder->duty_status ?: 'standby',
            'is_deployed' => (bool) ($responder->is_deployed ?? false),
            'skills' => $responder->skills,
            'blood_type' => $responder->blood_type ?: 'Unknown',
            'address' => $responder->address,
            'emergency_contact_name' => $responder->emergency_contact_name,
            'emergency_contact_number' => $responder->emergency_contact_number,
        ];
    }

    private function normalizeUsername(string $value): string
    {
        return strtolower(trim($value));
    }

    private function ensureUniqueMobileUsername(string $username, string $currentUserId): void
    {
        if (preg_match('/^BDRRM-[A-Z0-9]{2,8}-[0-9]{3}$/i', $username) === 1) {
            throw ValidationException::withMessages([
                'username' => ['Use a personal username, not the BDRRM account ID.'],
            ]);
        }

        $existingUser = DB::table('users')
            ->whereRaw('LOWER(username) = ?', [$username])
            ->where('user_id', '<>', $currentUserId)
            ->exists();

        $existingResponderLogin = Schema::hasTable('responders')
            && DB::table('responders')
                ->where(function ($query) use ($username): void {
                    $query->whereRaw('LOWER(username) = ?', [$username])
                        ->orWhereRaw('LOWER(responder_code) = ?', [$username]);
                })
                ->exists();

        if ($existingUser || $existingResponderLogin) {
            throw ValidationException::withMessages([
                'username' => ['This username is already used. Choose another username.'],
            ]);
        }
    }

    private function fullNameFromProfile(array $validated): string
    {
        $middle = $this->formatMiddleInitial($validated['middle_initial'] ?? '');

        return trim(collect([
            trim((string) $validated['first_name']),
            $middle,
            trim((string) $validated['last_name']),
        ])->filter()->join(' '));
    }

    private function formatMiddleInitial(?string $value): string
    {
        $middle = strtoupper(trim((string) $value));
        $middle = str_replace('.', '', $middle);

        return $middle === '' ? '' : substr($middle, 0, 1).'.';
    }

    private function formatAssignment(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        $routeNotes = $this->decodeJson($row->route_notes ?? null);
        $outcomes = $this->decodeJson($row->outcome_notes ?? null);
        $status = $this->statusKey($row->status ?? 'dispatched');

        return [
            'assignment_id' => $row->assignment_id,
            'assignment_code' => $row->assignment_code,
            'event_id' => $row->disaster_id,
            'event_name' => $row->event_name ?? 'Active event',
            'household_id' => $row->household_id,
            'assigned_area' => $row->assigned_area,
            'priority_level' => $row->priority_level ?: 'medium',
            'status_key' => $status,
            'status_label' => $this->statusLabel($status),
            'team_name' => $row->team_name ?: 'Assigned team',
            'team_code' => $row->team_code,
            'dispatch_notes' => $row->dispatch_notes,
            'route_notes' => $routeNotes,
            'outcomes' => $outcomes,
            'households_to_cover' => (int) ($routeNotes['households_to_cover'] ?? 0),
            'latitude' => $row->household_latitude ?? null,
            'longitude' => $row->household_longitude ?? null,
            'assigned_at' => $row->assigned_at,
            'accepted_at' => $row->accepted_at,
            'en_route_at' => $row->en_route_at,
            'arrived_at' => $row->arrived_at,
            'completed_at' => $row->completed_at,
            'route' => $this->routeForAssignment((int) $row->assignment_id),
        ];
    }

    private function summary(array $assignments): array
    {
        $active = collect($assignments)->filter(fn (array $assignment): bool => in_array($assignment['status_key'], ['accepted', 'dispatched', 'en_route', 'on_scene'], true));

        return [
            'total_assignments' => count($assignments),
            'active_assignments' => $active->count(),
            'completed_assignments' => collect($assignments)->where('status_key', 'completed')->count(),
            'urgent_assignments' => collect($assignments)->where('priority_level', 'urgent')->count(),
        ];
    }

    private function householdStatusOptions(): array
    {
        if (! Schema::hasTable('household_statuses')) {
            return [
                ['key' => 'safe', 'label' => 'Safe'],
                ['key' => 'evacuated', 'label' => 'Evacuated'],
                ['key' => 'unsafe', 'label' => 'Unsafe'],
            ];
        }

        return DB::table('household_statuses')
            ->orderBy('status_id')
            ->get([
                'status_id',
                'status_key',
                $this->householdStatusLabelSelect('household_statuses', 'status_label'),
            ])
            ->map(fn (object $status): array => [
                'status_id' => $status->status_id,
                'key' => $status->status_key,
                'label' => $status->status_label,
            ])
            ->values()
            ->all();
    }

    private function resourceCategoryOptions(): array
    {
        return [
            ['key' => 'resource', 'label' => 'Resource'],
            ['key' => 'personnel', 'label' => 'Personnel'],
            ['key' => 'vehicle', 'label' => 'Vehicle / Transport'],
        ];
    }

    private function resolveHouseholdStatusId(string $statusKey): ?int
    {
        if (! Schema::hasTable('household_statuses')) {
            return null;
        }

        $query = DB::table('household_statuses')->where('status_key', $statusKey);

        if (Schema::hasColumn('household_statuses', 'status_label')) {
            $query->orWhere('status_label', $statusKey);
        }

        if (Schema::hasColumn('household_statuses', 'status_name')) {
            $query->orWhere('status_name', $statusKey);
        }

        return $query->value('status_id');
    }

    private function urgencyId(string $urgencyKey): ?int
    {
        if (! Schema::hasTable('urgency_levels')) {
            return null;
        }

        return DB::table('urgency_levels')
            ->where('urgency_key', $urgencyKey)
            ->value('urgency_id')
            ?: DB::table('urgency_levels')->orderBy('urgency_id')->value('urgency_id');
    }

    private function resourceStatusId(string $statusKey): ?int
    {
        if (! Schema::hasTable('resource_request_status')) {
            return null;
        }

        return DB::table('resource_request_status')
            ->where('status_key', $statusKey)
            ->value('status_id');
    }

    private function saveRouteCoordinate(int $assignmentId, array $validated, $now): void
    {
        if (! Schema::hasTable('responder_routes') || ! Schema::hasTable('route_coordinates')) {
            return;
        }

        $route = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('created_at')
            ->first();

        if (! $route) {
            $routeId = $this->nextId('responder_routes', 'route_id');

            DB::table('responder_routes')->insert($this->filterColumns('responder_routes', [
                'route_id' => $routeId,
                'assignment_id' => $assignmentId,
                'route_name' => 'Responder mobile route',
                'route_status' => 'active',
                'start_latitude' => $validated['latitude'],
                'start_longitude' => $validated['longitude'],
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $route = (object) ['route_id' => $routeId];
        }

        $nextOrder = ((int) DB::table('route_coordinates')->where('route_id', $route->route_id)->max('sequence_order')) + 1;

        DB::table('route_coordinates')->insert($this->filterColumns('route_coordinates', [
            'coordinate_id' => $this->nextId('route_coordinates', 'coordinate_id'),
            'route_id' => $route->route_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'sequence_order' => $nextOrder,
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'recorded_at' => $now,
        ]));
    }

    private function saveResponderLocation(?object $responder, array $validated, $now): int
    {
        if (! $responder || ! Schema::hasTable('responder_location_logs')) {
            return 0;
        }

        $logId = $this->nextId('responder_location_logs', 'log_id');

        DB::table('responder_location_logs')->insert($this->filterColumns('responder_location_logs', [
            'log_id' => $logId,
            'responder_id' => $responder->responder_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'logged_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        return $logId;
    }

    private function savePlannedRoadRoute(int $assignmentId, object $assignment, array $start, $now): void
    {
        if (! Schema::hasTable('responder_routes') || ! $this->hasPoint($start)) {
            return;
        }

        $target = $this->assignmentTarget($assignment);

        if (! $target) {
            return;
        }

        $route = $this->routingService->drivingRoute(
            (float) $start['latitude'],
            (float) $start['longitude'],
            $target['latitude'],
            $target['longitude'],
        );

        if (! $route) {
            return;
        }

        $existingRoute = DB::table('responder_routes')
            ->where('assignment_id', $assignmentId)
            ->orderByDesc('created_at')
            ->first();

        $data = $this->filterColumns('responder_routes', [
            'assignment_id' => $assignmentId,
            'route_name' => 'Road route to '.($assignment->assigned_area ?: $assignment->household_id ?: 'assigned location'),
            'route_status' => 'active',
            'start_latitude' => $start['latitude'],
            'start_longitude' => $start['longitude'],
            'end_latitude' => $target['latitude'],
            'end_longitude' => $target['longitude'],
            'estimated_distance_km' => $route['distance_km'],
            'estimated_duration_min' => $route['duration_min'],
            'route_polyline' => json_encode($route['coordinates'], JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ]);

        if ($existingRoute) {
            DB::table('responder_routes')
                ->where('route_id', $existingRoute->route_id)
                ->update($data);

            return;
        }

        DB::table('responder_routes')->insert(array_merge($data, $this->filterColumns('responder_routes', [
            'route_id' => $this->nextId('responder_routes', 'route_id'),
            'created_at' => $now,
        ])));
    }

    private function assignmentTarget(object $assignment): ?array
    {
        if ($assignment->household_latitude === null || $assignment->household_longitude === null) {
            return null;
        }

        return [
            'latitude' => (float) $assignment->household_latitude,
            'longitude' => (float) $assignment->household_longitude,
        ];
    }

    private function hasPoint(array $data): bool
    {
        return array_key_exists('latitude', $data)
            && array_key_exists('longitude', $data)
            && $data['latitude'] !== null
            && $data['longitude'] !== null;
    }

    private function plannedRouteCoordinates(?string $polyline): array
    {
        if (! $polyline) {
            return [];
        }

        $decoded = json_decode($polyline, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn (array $point): array => [
                'latitude' => (float) ($point['lat'] ?? $point['latitude'] ?? $point[0] ?? 0),
                'longitude' => (float) ($point['lng'] ?? $point['longitude'] ?? $point[1] ?? 0),
            ])
            ->filter(fn (array $point): bool => $point['latitude'] !== 0.0 && $point['longitude'] !== 0.0)
            ->values()
            ->all();
    }

    private function updateResponderDuty(?int $responderId, ?int $teamId, string $status): void
    {
        if (! $responderId || ! Schema::hasTable('responders')) {
            return;
        }

        $isActive = in_array($status, ['accepted', 'dispatched', 'en_route', 'on_scene', 'deployed'], true);
        $dutyStatus = $isActive ? 'deployed' : 'available';

        DB::table('responders')
            ->where('responder_id', $responderId)
            ->update($this->filterColumns('responders', [
                'is_deployed' => $isActive ? 1 : 0,
                'duty_status' => $dutyStatus,
                'last_active_at' => now(),
                'updated_at' => now(),
            ]));

        if ($teamId && Schema::hasTable('rescue_teams')) {
            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->update($this->filterColumns('rescue_teams', [
                    'duty_status' => $dutyStatus,
                    'updated_at' => now(),
                ]));
        }
    }

    private function nextId(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 1;
        }

        return ((int) DB::table($table)->max($column)) + 1;
    }

    private function nextResourceRequestId(): string
    {
        do {
            $requestId = 'RR-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (Schema::hasTable('resource_requests') && DB::table('resource_requests')->where('request_id', $requestId)->exists());

        return $requestId;
    }

    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, string $key): bool => in_array($key, $columns, true))
            ->all();
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

    private function decodeJson(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mergeJson(?string $existing, array $data): string
    {
        return json_encode(array_merge($this->decodeJson($existing), $data), JSON_UNESCAPED_SLASHES);
    }

    private function statusKey(?string $status): string
    {
        return str_replace('-', '_', strtolower((string) ($status ?: 'dispatched')));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'accepted' => 'Accepted',
            'en_route' => 'En route',
            'on_scene' => 'On-scene',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Dispatched',
        };
    }

    private function fieldReportNotes(array $validated): string
    {
        $parts = [];

        if (! empty($validated['household_head'])) {
            $parts[] = 'Household head: ' . $validated['household_head'];
        }

        if (! empty($validated['address'])) {
            $parts[] = 'Address: ' . $validated['address'];
        }

        if (! empty($validated['notes'])) {
            $parts[] = 'Notes: ' . $validated['notes'];
        }

        if (! empty($validated['members'])) {
            $parts[] = 'Members: ' . json_encode($validated['members'], JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $parts);
    }

    private function resourceDescription(array $validated): ?string
    {
        $description = trim((string) ($validated['description'] ?? ''));
        $cluster = trim((string) ($validated['cluster'] ?? ''));
        $location = trim((string) ($validated['location'] ?? ''));
        $parts = [];

        if ($location !== '') {
            $parts[] = 'Location: ' . $location;
        }

        if ($cluster !== '') {
            $parts[] = 'Cluster: ' . $cluster;
        }

        if ($description !== '') {
            $parts[] = $description;
        }

        return $parts ? implode("\n", $parts) : null;
    }

    private function extractLocationFromDescription(?string $description): string
    {
        if (! $description) {
            return 'Field location';
        }

        foreach (preg_split('/\r\n|\r|\n/', $description) as $line) {
            if (str_starts_with($line, 'Location: ')) {
                return trim(str_replace('Location: ', '', $line)) ?: 'Field location';
            }
        }

        return 'Field location';
    }

    private function insertRadioLog(object $responder, array $message): int
    {
        $communicationId = $this->nextId('responder_communication_logs', 'communication_id');
        $activeEvent = $this->activeEvent();
        $now = now();

        DB::table('responder_communication_logs')->insert($this->filterColumns('responder_communication_logs', [
            'communication_id' => $communicationId,
            'responder_id' => $responder->responder_id,
            'team_id' => $responder->team_id,
            'team_name' => $responder->team_name,
            'disaster_id' => $activeEvent['event_id'] ?? null,
            'message' => json_encode($message, JSON_UNESCAPED_SLASHES),
            'timestamp' => $now,
        ]));

        return $communicationId;
    }

    private function radioMessage(Request $request, object $responder, array $values): array
    {
        $activeEvent = $this->activeEvent();
        $channel = (string) ($values['channel'] ?? 'team');

        return array_merge([
            'channel' => $channel,
            'channel_label' => $this->radioChannelLabel($channel),
            'responder_id' => $responder->responder_id,
            'responder_name' => $responder->full_name ?: 'Responder',
            'responder_code' => $responder->responder_code,
            'team_id' => $responder->team_id,
            'team_name' => $responder->team_name ?: 'Unassigned team',
            'event_id' => $activeEvent['event_id'] ?? null,
            'event_name' => $activeEvent['name'] ?? 'No active event',
            'saved_by_user_id' => $request->user()?->user_id,
        ], $values);
    }

    private function radioRowsForScope(object $responder): array
    {
        $activeEvent = $this->activeEvent();
        $query = DB::table('responder_communication_logs');

        if ($responder->team_id) {
            $query->where('team_id', $responder->team_id);
        } else {
            $query->where('responder_id', $responder->responder_id);
        }

        if ($activeEvent) {
            $query->where('disaster_id', $activeEvent['event_id']);
        } else {
            $query->whereNull('disaster_id');
        }

        return $query
            ->orderByDesc('timestamp')
            ->orderByDesc('communication_id')
            ->limit(120)
            ->get()
            ->map(fn (object $row): array => $this->formatRadioLog($row))
            ->values()
            ->all();
    }

    private function radioTeamMembers(object $responder, $logs, ?array $activeTransmission): array
    {
        if (! Schema::hasTable('responders')) {
            return [];
        }

        $query = DB::table('responders as r');
        $columns = [
            'r.responder_id',
            'r.responder_code',
            'r.full_name',
            'r.team_id',
        ];

        if ($responder->team_id) {
            $query->where('r.team_id', $responder->team_id);
        } else {
            $query->where('r.responder_id', $responder->responder_id);
        }

        if (Schema::hasTable('rescue_teams')) {
            $query->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id');
            $columns[] = 'rt.team_name';
            $columns[] = 'rt.team_code';
        } else {
            $columns[] = DB::raw('NULL as team_name');
            $columns[] = DB::raw('NULL as team_code');
        }

        return $query
            ->orderBy('r.full_name')
            ->get($columns)
            ->map(function (object $member) use ($logs, $activeTransmission, $responder): array {
                $memberId = (string) $member->responder_id;
                $queuedCount = $logs
                    ->filter(fn (array $log): bool => $log['type'] === 'ptt_audio'
                        && (string) $log['responder_id'] === $memberId)
                    ->count();

                return [
                    'responder_id' => $member->responder_id,
                    'responder_code' => $member->responder_code,
                    'full_name' => $member->full_name ?: 'Responder',
                    'initials' => $this->responderInitials($member->full_name ?: 'Responder'),
                    'team_id' => $member->team_id,
                    'team_name' => $member->team_name ?: 'Assigned team',
                    'team_code' => $member->team_code,
                    'is_self' => (int) $member->responder_id === (int) $responder->responder_id,
                    'is_transmitting' => $activeTransmission
                        && (string) ($activeTransmission['responder_id'] ?? '') === $memberId,
                    'queued_count' => $queuedCount,
                ];
            })
            ->values()
            ->all();
    }

    private function responderInitials(string $name): string
    {
        $parts = collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
            ->join('');

        return $parts ?: 'R';
    }

    private function activeRadioTransmission(object $responder): ?array
    {
        $recentLogs = collect($this->radioRowsForScope($responder))
            ->filter(fn (array $log): bool => in_array($log['type'], ['ptt_start', 'ptt_heartbeat', 'ptt_end'], true))
            ->values();

        $endedIds = $recentLogs
            ->where('type', 'ptt_end')
            ->pluck('transmission_id')
            ->filter()
            ->unique()
            ->all();

        $latestOpen = $recentLogs
            ->filter(fn (array $log): bool => in_array($log['type'], ['ptt_start', 'ptt_heartbeat'], true))
            ->first(fn (array $log): bool => $log['transmission_id'] && ! in_array($log['transmission_id'], $endedIds, true));

        if (! $latestOpen) {
            return null;
        }

        $secondsSinceLastSignal = now()->diffInSeconds(
            \Illuminate\Support\Carbon::parse($latestOpen['raw_timestamp'])
        );

        if ($secondsSinceLastSignal > 12) {
            return null;
        }

        return [
            'transmission_id' => $latestOpen['transmission_id'],
            'channel' => $latestOpen['channel'],
            'channel_label' => $latestOpen['channel_label'],
            'responder_id' => $latestOpen['responder_id'],
            'responder_name' => $latestOpen['responder_name'],
            'responder_code' => $latestOpen['responder_code'],
            'team_name' => $latestOpen['team_name'],
            'duration_seconds' => $latestOpen['duration_seconds'],
            'is_self' => (int) $latestOpen['responder_id'] === (int) $responder->responder_id,
            'audio_status' => $latestOpen['audio_status'],
            'last_seen_at' => $latestOpen['timestamp'],
        ];
    }

    private function formatRadioLog(object $row): array
    {
        $message = $this->decodeJson($row->message ?? null);
        $type = (string) ($message['type'] ?? 'message');
        $channel = (string) ($message['channel'] ?? 'team');

        return [
            'id' => $row->communication_id,
            'communication_id' => $row->communication_id,
            'type' => $type,
            'type_label' => $this->radioTypeLabel($type, $message),
            'channel' => $channel,
            'channel_label' => (string) ($message['channel_label'] ?? $this->radioChannelLabel($channel)),
            'transmission_id' => $message['transmission_id'] ?? null,
            'signal' => $message['signal'] ?? null,
            'duration_seconds' => (int) ($message['duration_seconds'] ?? 0),
            'responder_id' => $row->responder_id,
            'responder_name' => (string) ($message['responder_name'] ?? 'Responder'),
            'responder_code' => $message['responder_code'] ?? null,
            'team_id' => $row->team_id,
            'team_name' => $row->team_name ?: ($message['team_name'] ?? 'Assigned team'),
            'event_id' => $row->disaster_id,
            'event_name' => (string) ($message['event_name'] ?? 'No active event'),
            'audio_status' => (string) ($message['audio_status'] ?? 'metadata_only'),
            'audio_path' => $message['audio_path'] ?? null,
            'audio_url' => ! empty($message['audio_path']) ? Storage::disk('public')->url($message['audio_path']) : null,
            'message' => $this->radioDisplayMessage($type, $message),
            'timestamp' => $this->formatMobileDateTime($row->timestamp),
            'raw_timestamp' => $row->timestamp,
        ];
    }

    private function radioDisplayMessage(string $type, array $message): string
    {
        $name = (string) ($message['responder_name'] ?? 'Responder');

        return match ($type) {
            'ptt_start' => $name . ' started PTT',
            'ptt_heartbeat' => $name . ' is transmitting',
            'ptt_end' => $name . ' ended PTT after ' . ((int) ($message['duration_seconds'] ?? 0)) . 's',
            'ptt_audio' => $name . ' sent a voice message',
            'quick_signal' => $name . ' sent "' . ((string) ($message['signal'] ?? 'Signal')) . '"',
            default => (string) ($message['text'] ?? $name . ' sent a radio log'),
        };
    }

    private function radioTypeLabel(string $type, array $message): string
    {
        return match ($type) {
            'ptt_start' => 'PTT started',
            'ptt_heartbeat' => 'PTT active',
            'ptt_end' => 'PTT ended',
            'ptt_audio' => 'Voice message',
            'quick_signal' => (string) ($message['signal'] ?? 'Signal'),
            default => 'Radio log',
        };
    }

    private function radioChannelLabel(string $channel): string
    {
        return match ($channel) {
            'command' => 'HQ Command',
            'event' => 'Event',
            default => 'Team',
        };
    }

    private function formatMobileDateTime(mixed $value): string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->timezone('Asia/Manila')->format('M d, g:i A') : 'Not recorded';
    }

    private function writeAuditLog(Request $request, string $action, string $table, string $referenceId, array $values): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert($this->filterColumns('audit_logs', [
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'rescuer_mobile',
            'action' => $action,
            'reference_table' => $table,
            'reference_id' => $referenceId,
            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]));
    }

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => "The {$table} table is not available yet. Connect to the shared database or ask the DB member to apply the approved schema.",
        ], 503);
    }
}
