<?php

namespace App\Services\Mobile;

use App\Actions\UpdateHouseholdStatus;
use App\Queries\RescuerAssignmentQuery;
use App\Queries\RescuerFieldReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RescuerFieldReportWorkflow
{
    public function __construct(
        private \App\Services\Mobile\RescuerMobileSupport $support,
        private RescuerAssignmentQuery $assignments,
        private RescuerFieldReportQuery $query,
    ) {}

    public function assignmentHousehold(Request $request, int $assignmentId): JsonResponse
    {
        foreach (['households', 'household_members', 'member_statuses'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->missingTableResponse($table);
            }
        }

        $responder = $this->support->responderForUser($request->user());
        $assignment = $this->assignments->find($assignmentId, $responder?->responder_id);

        if (! $assignment || empty($assignment->household_id) || ! $this->assignmentIsActive($assignment)) {
            return response()->json(['message' => 'Household assignment was not found for your rescuer account.'], 404);
        }
        $activeEvent = $this->support->activeEvent();
        if (! $activeEvent || (string) $assignment->disaster_id !== (string) $activeEvent->event_id) {
            return response()->json(['message' => 'This assignment does not belong to the active disaster event.'], 409);
        }

        $householdQuery = DB::table('households as h')->where('h.household_id', $assignment->household_id);
        if (Schema::hasColumn('households', 'deleted_at')) {
            $householdQuery->whereNull('h.deleted_at');
        }
        $householdColumns = [
            'h.household_id',
            $this->firstExistingColumn('households', ['household_code'], 'household_code', 'h'),
            $this->firstExistingColumn('households', ['household_number'], 'household_number', 'h'),
            $this->firstExistingColumn('households', ['household_name'], 'household_name', 'h'),
        ];

        if (Schema::hasTable('addresses') && Schema::hasColumn('households', 'address_id')) {
            $householdQuery->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id');
            $householdColumns[] = $this->firstExistingColumn('addresses', ['full_address'], 'full_address', 'a');
            $householdColumns[] = $this->firstExistingColumn('addresses', ['purok_sitio'], 'purok_sitio', 'a');
        } else {
            $householdColumns[] = DB::raw('NULL as full_address');
            $householdColumns[] = DB::raw('NULL as purok_sitio');
        }

        $household = $householdQuery->first($householdColumns);
        if (! $household) {
            return response()->json(['message' => 'The assigned household record was not found.'], 404);
        }

        $memberQuery = DB::table('household_members as hm')->where('hm.household_id', $assignment->household_id);
        if (Schema::hasColumn('household_members', 'deleted_at')) {
            $memberQuery->whereNull('hm.deleted_at');
        }
        $memberColumns = [
            'hm.member_id',
            $this->firstExistingColumn('household_members', ['first_name'], 'first_name', 'hm'),
            $this->firstExistingColumn('household_members', ['middle_name'], 'middle_name', 'hm'),
            $this->firstExistingColumn('household_members', ['last_name'], 'last_name', 'hm'),
            $this->firstExistingColumn('household_members', ['is_household_head'], 'is_household_head', 'hm'),
            DB::raw('NULL as member_status_key'),
            DB::raw('NULL as member_status_label'),
        ];

        if (Schema::hasTable('member_disaster_statuses') && Schema::hasTable('member_statuses')) {
            $memberQuery->leftJoin('member_disaster_statuses as mds', function ($join) use ($assignment): void {
                $join->on('mds.member_id', '=', 'hm.member_id')
                    ->where('mds.disaster_id', '=', $assignment->disaster_id);
            })->leftJoin('member_statuses as ms', 'ms.status_id', '=', 'mds.status_id');
            $memberColumns[5] = $this->firstExistingColumn('member_statuses', ['status_key'], 'member_status_key', 'ms');
            $memberColumns[6] = $this->firstExistingColumn('member_statuses', ['status_label'], 'member_status_label', 'ms');
        }

        if (Schema::hasColumn('household_members', 'is_household_head')) {
            $memberQuery->orderByDesc('hm.is_household_head');
        }
        if (Schema::hasColumn('household_members', 'last_name')) {
            $memberQuery->orderBy('hm.last_name');
        }
        if (Schema::hasColumn('household_members', 'first_name')) {
            $memberQuery->orderBy('hm.first_name');
        }
        $members = $memberQuery
            ->get($memberColumns)
            ->map(fn (object $member): array => [
                'member_id' => (string) $member->member_id,
                'name' => trim(implode(' ', array_filter([$member->first_name, $member->middle_name, $member->last_name]))),
                'is_household_head' => (bool) $member->is_household_head,
                'member_status' => $member->member_status_key
                    ? ['key' => $member->member_status_key, 'label' => $member->member_status_label]
                    : null,
            ])->values();

        $household->full_address = app(\App\Queries\HouseholdAddressQuery::class)->forHouseholds([$household->household_id])->get($household->household_id);
        $head = $members->firstWhere('is_household_head', true);
        $memberStatusOptions = $this->memberStatusOptions();
        $defaultStatus = collect($memberStatusOptions)->first(fn ($option) => in_array($option['key'], ['unknown','unchecked','unreported'], true));
        $members = $members->map(fn ($member) => array_merge($member, ['member_status' => $member['member_status'] ?? $defaultStatus]));
        if ($memberStatusOptions === []) {
            return response()->json(['message' => 'No active member status options are configured for field reports.'], 503);
        }

        return response()->json(['data' => [
            'assignment_id' => $assignmentId,
            'event_id' => $assignment->disaster_id,
            'household' => [
                'household_id' => $household->household_id,
                'household_code' => $household->household_code,
                'household_number' => $household->household_number,
                'household_name' => $household->household_name,
                'address' => $household->full_address,
                'purok' => $household->purok_sitio,
                'location' => ['label' => $household->full_address ?: $household->purok_sitio],
                'household_head_name' => ($head['name'] ?? null) ?: $household->household_name,
            ],
            'members' => $members,
            'member_status_options' => $memberStatusOptions,
            'saved_property_damage' => Schema::hasColumn('responder_field_reports','property_damage') ? DB::table('responder_field_reports')->where('assignment_id',$assignmentId)->where('responder_id',$responder->responder_id)->where('household_id',$household->household_id)->value('property_damage') : null,
            'property_damage_options' => Schema::hasTable('property_damage_levels') ? DB::table('property_damage_levels')->where('is_active',true)->orderBy('sort_order')->get(['damage_key as key','label'])->all() : [],
        ]]);
    }

    public function fieldReports(Request $request): array
    {
        $user = $request->user();
        $responder = $this->support->responderForUser($user);
        return [
            'reports' => $this->query->fieldReports($responder?->responder_id, $user?->user_id),
            'status_options' => $this->query->statusOptions(),
        ];
    }

    public function statusOptions(): array
    {
        return $this->query->statusOptions();
    }

    public function fieldReportsAdmin(Request $request): array
    {
        $status = strtolower(trim((string) $request->query('status', 'all')));
        $statusId = trim((string) $request->query('status_id', ''));
        $eventId = trim((string) $request->query('event_id', ''));
        $activeEvent = $this->support->activeEvent();
        if ($eventId === '' && $activeEvent) $eventId = (string) ($activeEvent->event_id ?? '');

        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $reports = $this->query->paginateAdmin(['status' => $status, 'status_id' => $statusId,
            'event_id' => $eventId, 'search' => $request->query('search', '')],
            \App\Http\Requests\ListRequest::clampPerPage($request->query('per_page')));

        return [
            'active_event' => $activeEvent,
            'status_options' => $this->query->assignmentStatusOptions(),
            'summary' => $request->boolean('include_summary', true) ? $this->query->assignmentSummary($eventId) : ['rows' => []],
            'reports' => collect($reports->items()),
            'reports_meta' => ['current_page' => $reports->currentPage(), 'per_page' => $reports->perPage(),
                'total' => $reports->total(), 'last_page' => $reports->lastPage(),
                'from' => $reports->firstItem(), 'to' => $reports->lastItem()],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->exists('assignment_id')) {
            return $this->storeAssignmentReport($request);
        }

        if (! Schema::hasTable('household_status_logs')) {
            return $this->missingTableResponse('household_status_logs');
        }

        $activeEvent = $this->support->activeEvent();
        if (! $activeEvent) {
            return response()->json([
                'message' => 'Field reports can only be submitted after HQ declares an active disaster event.',
            ], 409);
        }

        $validated = $request->validate([
            'household_id' => ['required', 'string', 'max:255'],
            'household_head' => ['required', 'string', 'min:2', 'max:150'],
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'status_key' => ['required', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
            'injured_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'death_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'property_damage' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*.name' => ['nullable', 'string', 'max:150'],
            'members.*.condition' => ['nullable', 'string', 'max:255'],
        ], [
            'household_id.required' => 'Household ID is required.',
            'household_head.required' => 'Household head name is required.',
            'address.required' => 'Location, purok, or landmark is required.',
            'status_key.required' => 'Select the household status.',
            'notes.required' => 'Field notes are required.',
            'notes.min' => 'Field notes must describe the situation clearly.',
        ]);

        $this->validateMembers($validated['members'] ?? []);
        $statusId = $this->support->resolveHouseholdStatusId($validated['status_key']);
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
        $responder = $this->support->responderForUser($user);
        $eventId = (string) $activeEvent->event_id;
        $now = now();
        $notes = $this->fieldReportNotes($validated);

        $statusLogId = DB::transaction(function () use ($request, $user, $responder, $validated, $statusId, $eventId, $now, $notes): int {
            $statusLogId = $this->support->nextId('household_status_logs', 'status_log_id');
            DB::table('household_status_logs')->insert($this->support->filterColumns('household_status_logs', [
                'status_log_id' => $statusLogId,
                'disaster_id' => $eventId,
                'household_id' => $validated['household_id'],
                'status_id' => $statusId,
                'source' => 'responder_field_report',
                'submitted_by_user_id' => $user?->user_id,
                'responder_id' => $responder?->responder_id,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'notes' => $notes,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $this->saveLatestStatus($eventId, $validated['household_id'], $statusId, $validated, $notes, $user?->user_id, $responder?->responder_id, $now);
            $this->saveResponderReport($responder?->responder_id, $eventId, $validated, $notes, $now);
            $this->support->writeAuditLog($request, 'mobile_field_report', 'household_status_logs', (string) $statusLogId, $validated);

            return $statusLogId;
        });

        return response()->json([
            'message' => 'Field report submitted to HQ.',
            'data' => ['status_log_id' => $statusLogId],
        ], 201);
    }

    private function storeAssignmentReport(Request $request): JsonResponse
    {
        foreach ([
            'household_status_logs', 'responder_field_reports', 'responder_field_report_members',
            'households', 'household_members', 'member_statuses', 'member_disaster_statuses',
            'household_statuses', 'responder_assignments', 'property_damage_levels',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->missingTableResponse($table);
            }
        }

        foreach (['assignment_id', 'idempotency_key', 'payload_hash'] as $column) {
            if (! Schema::hasColumn('responder_field_reports', $column)) {
                return response()->json(['message' => 'The field report schema is out of date. Apply the latest Laravel migrations.'], 503);
            }
        }

        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'min:1'],
            'household_id' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'incident_type' => ['nullable', 'string', 'max:80'],
            'incident_type_other' => ['nullable', 'string', 'max:255', 'required_if:incident_type,other'],
            'property_damage' => ['required', 'string', 'max:80'],
            'death_count' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'injured_count' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'members' => ['required', 'array', 'min:1'],
            'members.*.member_id' => ['required', 'string', 'max:255'],
            'members.*.status_key' => ['required', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $responder = $this->support->responderForUser($user);
        if (! $responder) {
            return response()->json(['message' => 'Rescuer profile was not found for this account.'], 404);
        }

        $activeEvent = $this->support->activeEvent();
        if (! $activeEvent) {
            return response()->json(['message' => 'Field reports can only be submitted after HQ declares an active disaster event.'], 409);
        }

        $assignment = $this->assignments->find((int) $validated['assignment_id'], (int) $responder->responder_id);
        if (! $assignment || empty($assignment->household_id)) {
            return response()->json(['message' => 'Household assignment was not found for your rescuer account.'], 404);
        }
        if ((string) $assignment->household_id !== (string) $validated['household_id']) {
            return response()->json(['message' => 'The selected household does not match this assignment.'], 422);
        }
        if ((string) $assignment->disaster_id !== (string) $activeEvent->event_id) {
            return response()->json(['message' => 'This assignment does not belong to the active disaster event.'], 409);
        }

        $householdMembers = $this->activeHouseholdMemberIds((string) $assignment->household_id);
        $submittedMembers = collect($validated['members'])->map(fn (array $member): array => [
            'member_id' => (string) $member['member_id'],
            'status_key' => $member['status_key'] === 'unchecked' ? 'unknown' : $member['status_key'],
        ])->sortBy('member_id')->values();

        if ($householdMembers === [] || $submittedMembers->count() !== count($householdMembers)
            || $submittedMembers->pluck('member_id')->unique()->count() !== count($householdMembers)
            || $submittedMembers->pluck('member_id')->diff($householdMembers)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'members' => ['The report must include one assessment for every current member of the assigned household.'],
            ]);
        }

        $memberStatusesQuery = DB::table('member_statuses')
            ->whereIn('status_key', $submittedMembers->pluck('status_key')->unique());
        if (Schema::hasColumn('member_statuses', 'is_active')) {
            $memberStatusesQuery->where('is_active', true);
        }
        $memberStatuses = $memberStatusesQuery->get(['status_id', 'status_key'])->keyBy('status_key');
        if ($submittedMembers->contains(fn (array $member): bool => ! $memberStatuses->has($member['status_key']))) {
            throw ValidationException::withMessages([
                'members' => ['One or more selected member statuses are no longer available. Refresh the household and try again.'],
            ]);
        }

        if (Schema::hasTable('property_damage_levels') && !DB::table('property_damage_levels')->where('damage_key',$validated['property_damage'])->where('is_active',true)->exists()) {
            throw ValidationException::withMessages(['property_damage'=>['Select an available damage level.']]);
        }
        $validated['incident_type'] ??= null;
        $catalog = collect($this->memberStatusOptions())->keyBy('key');
        $validated['death_count'] = $submittedMembers->filter(fn ($member) => $catalog->get($member['status_key'])['counts_as_death'] ?? false)->count();
        $validated['injured_count'] = $submittedMembers->filter(fn ($member) => $catalog->get($member['status_key'])['counts_as_injury'] ?? false)->count();
        $members = $submittedMembers->all();
        $incidentTypeOther = $validated['incident_type'] === 'other' ? trim((string) ($validated['incident_type_other'] ?? '')) : null;
        $payloadHash = hash('sha256', json_encode([
            'assignment_id' => (int) $validated['assignment_id'],
            'household_id' => (string) $validated['household_id'],
            'incident_type' => $validated['incident_type'],
            'incident_type_other' => $incidentTypeOther,
            'property_damage' => $validated['property_damage'],
            'death_count' => (int) $validated['death_count'],
            'injured_count' => (int) $validated['injured_count'],
            'notes' => trim((string) ($validated['notes'] ?? '')),
            'members' => $members,
        ], JSON_UNESCAPED_SLASHES));

        $statusKey = $this->householdStatusFromMemberAssessments($members, $validated['death_count'] > 0 || $validated['injured_count'] > 0);
        $statusId = $statusKey ? $this->support->resolveHouseholdStatusId($statusKey) : null;
        if (! $statusId) {
            throw ValidationException::withMessages([
                'members' => ['The household status for these assessments is not available in the database.'],
            ]);
        }

        $now = now();
        $reportId = $this->support->nextId('responder_field_reports', 'report_id');
        $reportNotes = json_encode([
            'report_type' => 'rescuer_field_report',
            'assignment_id' => (int) $assignment->assignment_id,
            'incident_type' => $validated['incident_type'],
            'incident_type_other' => $incidentTypeOther,
            'property_damage' => $validated['property_damage'],
            'death_count' => (int) $validated['death_count'],
            'injured_count' => (int) $validated['injured_count'],
            'user_notes' => trim((string) ($validated['notes'] ?? '')),
            'members' => $members,
        ], JSON_UNESCAPED_SLASHES);

        $result = DB::transaction(function () use (
            $request, $user, $responder, $assignment, $activeEvent, $validated, $members,
            $householdMembers, $memberStatuses, $statusKey, $statusId, $reportId,
            $payloadHash, $incidentTypeOther, $reportNotes, $now,
        ): array {
            $lockedAssignment = DB::table('responder_assignments')
                ->where('assignment_id', $assignment->assignment_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedAssignment
                || ! $this->assignmentBelongsToResponder($lockedAssignment, (int) $responder->responder_id)
                || (string) \App\Support\RescuerHouseholdQueue::target($lockedAssignment, (int) $responder->responder_id) !== (string) $assignment->household_id
                || (string) $lockedAssignment->disaster_id !== (string) $activeEvent->event_id
                || ! $this->assignmentIsActive($lockedAssignment)) {
                throw ValidationException::withMessages([
                    'assignment_id' => ['The selected assignment is no longer active. Refresh assignments and try again.'],
                ]);
            }

            if ($this->activeHouseholdMemberIds((string) $assignment->household_id) !== $householdMembers) {
                throw ValidationException::withMessages([
                    'members' => ['The household membership changed. Refresh the report before submitting again.'],
                ]);
            }

            $existing = DB::table('responder_field_reports')
                ->where('assignment_id', $assignment->assignment_id)
                ->where('household_id', $assignment->household_id)
                ->where('responder_id', $responder->responder_id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ((string) $existing->idempotency_key === (string) $validated['idempotency_key']
                    && hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    return ['report_id' => $existing->report_id, 'status_log_id' => $existing->status_log_id, 'duplicate' => true];
                }

                abort(409, 'A field report has already been saved for this assignment.');
            }

            $statusLogId = $this->support->nextId('household_status_logs', 'status_log_id');
            DB::table('household_status_logs')->insert($this->support->filterColumns('household_status_logs', [
                'status_log_id' => $statusLogId,
                'disaster_id' => $activeEvent->event_id,
                'household_id' => $assignment->household_id,
                'status_id' => $statusId,
                'source' => 'responder_field_report',
                'submitted_by_user_id' => $user?->user_id,
                'responder_id' => $responder->responder_id,
                'assignment_id' => $assignment->assignment_id,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'notes' => $reportNotes,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            DB::table('responder_field_reports')->insert($this->support->filterColumns('responder_field_reports', [
                'report_id' => $reportId,
                'responder_id' => $responder->responder_id,
                'disaster_id' => $activeEvent->event_id,
                'household_id' => $assignment->household_id,
                'assignment_id' => $assignment->assignment_id,
                'submitted_by_user_id' => $user?->user_id,
                'status_log_id' => $statusLogId,
                'incident_type' => $validated['incident_type'],
                'incident_type_other' => $incidentTypeOther,
                'property_damage' => $validated['property_damage'],
                'death_count' => (int) $validated['death_count'],
                'injured_count' => (int) $validated['injured_count'],
                'idempotency_key' => $validated['idempotency_key'],
                'payload_hash' => $payloadHash,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'notes' => $reportNotes,
                'submitted_at' => $now,
                'created_at' => $now,
            ]));

            foreach ($members as $member) {
                $memberStatusId = $memberStatuses[$member['status_key']]->status_id;
                DB::table('responder_field_report_members')->insert([
                    'report_id' => $reportId,
                    'household_id' => $assignment->household_id,
                    'disaster_id' => $activeEvent->event_id,
                    'member_id' => $member['member_id'],
                    'status_id' => $memberStatusId,
                    'status_key' => $member['status_key'],
                    'assessed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('member_disaster_statuses')->updateOrInsert(
                    ['disaster_id' => $activeEvent->event_id, 'member_id' => $member['member_id']],
                    $this->support->filterColumns('member_disaster_statuses', [
                        'household_id' => $assignment->household_id,
                        'status_id' => $memberStatusId,
                        'report_source' => 'responder',
                        'reported_by_user_id' => $user?->user_id,
                        'responder_id' => $responder->responder_id,
                        'notes' => $reportNotes,
                        'reported_at' => $now,
                        'updated_at' => $now,
                    ]),
                );
            }

            app(UpdateHouseholdStatus::class)->apply(
                (string) $activeEvent->event_id,
                (string) $assignment->household_id,
                (int) $statusId,
                $statusKey,
                'responder_field_report',
                $reportNotes,
                $user?->user_id,
                null,
                $validated,
                null,
                false,
            );
            $this->support->writeAuditLog($request, 'mobile_field_report', 'responder_field_reports', (string) $reportId, [
                'assignment_id' => $assignment->assignment_id,
                'household_id' => $assignment->household_id,
                'event_id' => $activeEvent->event_id,
                'member_count' => count($members),
            ]);

            return ['report_id' => $reportId, 'status_log_id' => $statusLogId, 'duplicate' => false];
        }, 3);

        return response()->json([
            'message' => $result['duplicate'] ? 'Field report was already saved.' : 'Field report submitted to HQ.',
            'data' => ['report_id' => $result['report_id'], 'status_log_id' => $result['status_log_id']],
        ], 201);
    }

    private function activeHouseholdMemberIds(string $householdId): array
    {
        $query = DB::table('household_members')->where('household_id', $householdId);
        if (Schema::hasColumn('household_members', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy('member_id')->pluck('member_id')->map(fn ($id): string => (string) $id)->all();
    }

    private function assignmentBelongsToResponder(object $assignment, int $responderId): bool
    {
        if ((int) $assignment->responder_id === $responderId) {
            return true;
        }

        $routeNotes = json_decode((string) ($assignment->route_notes ?? ''), true);
        $selectedIds = is_array($routeNotes) ? ($routeNotes['selected_responder_ids'] ?? []) : [];

        return in_array($responderId, array_map('intval', $selectedIds), true);
    }

    private function assignmentIsActive(object $assignment): bool
    {
        $status = strtolower(str_replace('-', '_', (string) ($assignment->status ?? '')));

        return in_array($status, ['dispatched', 'accepted', 'en_route', 'on_scene'], true);
    }

    private function householdStatusFromMemberAssessments(array $members, bool $hasCasualties = false): ?string
    {
        $keys = collect($members)->pluck('status_key');
        if ($hasCasualties || $keys->contains(fn ($key) => in_array($key, ['injured','missing','needs_help','need_help','needs_assistance','unsafe','trapped','unreachable','deceased','dead'], true))) {
            $candidates = ['needs_help','needs_assistance','unsafe','not_evacuated'];
        } elseif ($keys->contains(fn ($key) => in_array($key, ['unknown','unchecked','unreported'], true))) {
            $candidates = ['unknown','unchecked'];
        } elseif ($keys->contains(fn ($key) => in_array($key, ['evacuated','relocated'], true))) {
            $candidates = ['evacuated','relocated'];
        } else {
            $candidates = ['safe','safe_at_home','active'];
        }

        foreach ($candidates as $candidate) {
            if ($this->support->resolveHouseholdStatusId($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function memberStatusOptions(): array
    {
        if (! Schema::hasTable('member_statuses')) {
            return [];
        }

        $query = DB::table('member_statuses');
        if (Schema::hasColumn('member_statuses', 'is_active')) {
            $query->where('is_active', true);
        }
        if (Schema::hasColumn('member_statuses', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->orderBy('status_id')->get()->map(fn (object $status): array => [
            'status_id' => $status->status_id,
            'key' => $status->status_key,
            'counts_as_death' => (bool) ($status->counts_as_death ?? in_array($status->status_key, ['deceased','dead'], true)),
            'counts_as_injury' => (bool) ($status->counts_as_injury ?? $status->status_key === 'injured'),
            'label' => $status->status_key === 'unknown' ? 'Unchecked' : ($status->status_label ?: ucwords(str_replace('_', ' ', $status->status_key))),
        ])->values()->all();
    }

    private function firstExistingColumn(string $table, array $candidates, string $alias, ?string $prefix = null)
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return ($prefix ? $prefix.'.' : '').$column.' as '.$alias;
            }
        }

        return DB::raw('NULL as '.$alias);
    }

    private function validateMembers(array $members): void
    {
        foreach ($members as $index => $member) {
            $name = trim((string) ($member['name'] ?? ''));
            $condition = trim((string) ($member['condition'] ?? ''));
            if (($name === '') !== ($condition === '')) {
                throw ValidationException::withMessages([
                    'members.'.($index + 1) => ['Enter both member name and condition, or leave both blank.'],
                ]);
            }
        }
    }

    private function fieldReportNotes(array $validated): string
    {
        $parts = [];
        foreach ([
            'household_head' => 'Household head: ',
            'address' => 'Address: ',
            'notes' => 'Notes: ',
            'injured_count' => 'Injuries: ',
            'death_count' => 'Deaths: ',
            'property_damage' => 'Property damage: ',
        ] as $key => $prefix) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null && $validated[$key] !== '') {
                $parts[] = $prefix.$validated[$key];
            }
        }
        if (! empty($validated['members'])) {
            $parts[] = 'Members: '.json_encode($validated['members'], JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $parts);
    }

    private function saveResponderReport(?int $responderId, string $eventId, array $validated, string $notes, $now): void
    {
        if (! Schema::hasTable('responder_field_reports') || ! $responderId) {
            return;
        }

        DB::table('responder_field_reports')->insert($this->support->filterColumns('responder_field_reports', [
            'report_id' => (string) $this->support->nextId('responder_field_reports', 'report_id'),
            'responder_id' => $responderId,
            'disaster_id' => $eventId,
            'household_id' => $validated['household_id'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'notes' => $notes,
            'created_at' => $now,
        ]));
    }

    private function saveLatestStatus(string $eventId, string $householdId, int $statusId, array $validated, string $notes, ?string $userId, ?int $responderId, $now): void
    {
        app(UpdateHouseholdStatus::class)->apply(
            $eventId, $householdId, $statusId, $validated['status_key'],
            'responder_field_report', $notes, $userId, null, $validated,
        );

        if ($responderId && Schema::hasColumn('household_disasters', 'last_responder_id')) {
            DB::table('household_disasters')->where('disaster_id', $eventId)
                ->where('household_id', $householdId)->update(['last_responder_id' => $responderId]);
        }
    }

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json(['message' => 'The required '.$table.' table is not available in the current database.'], 503);
    }
}







