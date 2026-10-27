<?php

namespace App\Services\Web;

use App\Jobs\SendDispatchPush;
use App\Jobs\SendDispatchChangePush;
use App\Queries\WelfareCheckQuery;

use App\Models\AuditLog;
use App\Models\DisasterEvent;
use App\Models\GeotaggedLocation;
use App\Models\Household;
use App\Models\HouseholdDisaster;
use App\Models\HouseholdStatus;
use App\Models\HouseholdStatusLog;
use App\Models\Responder;
use App\Models\ResponderAssignment;
use App\Models\ResponderLocationLog;
use App\Models\ResponderRoute;
use App\Models\RescueTeam;
use App\Models\RouteCoordinate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class RescueDispatchService
{
    public function __construct(private \App\Services\Shared\OneSignalNotificationService $oneSignal, private \App\Presenters\RescueDispatchPresenter $presenter, private \App\Queries\RescueDispatchQuery $query, private \App\Services\Web\RescueDispatchWorkflow $workflow, private \App\Queries\AreaCoverageQuery $coverage) {}

    public function teams(): JsonResponse
    {
        $activeEvent = $this->query->activeEvent();
        $teamCards = $this->query->teamCards($activeEvent?->event_id);

        return response()->json([
            'data' => [
                'area_label' => $this->coverage->label(),
                'active_event' => $activeEvent ? $this->presenter->activeEvent($activeEvent) : null,
                'summary' => $this->query->summary($activeEvent?->event_id, $teamCards),
                'teams' => $teamCards,
                'responders' => $this->query->responders($activeEvent?->event_id),
            ],
        ]);
    }

    public function communications(Request $request): array
    {
        $request->validate([
            'channel' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'command', 'team', 'event'])],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $event = $this->query->activeEvent();
        $logs = app(\App\Queries\FieldCommunicationQuery::class)->paginate($request, $event?->event_id);
        $compact = $request->boolean('compact');
        return [
            'active_event' => $event ? $this->presenter->activeEvent($event) : null,
            'logs' => $logs,
            'responders' => $this->query->responders($event?->event_id, $compact ? collect($logs->items())->pluck('responder_id')->filter()->unique()->all() : null),
            'teams' => $compact ? RescueTeam::query()->orderBy('team_name')->get(['team_id','team_code','team_name'])
                : $this->query->teamCards($event?->event_id)->filter(fn ($team) => $team['team_id'])->values(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['view' => ['sometimes', \Illuminate\Validation\Rule::in(['overview', 'management', 'coverage'])]]);
        if ($request->filled('view')) return $this->pageView($request);
        $request->validate(['sitio_id' => ['sometimes', 'integer', 'min:1']]);
        if ($request->has('sitio_id')) app(\App\Queries\HouseholdStatusQuery::class)->listScope($request);
        $activeEvent = $this->query->activeEvent();
        $eventId = $request->query('event_id') ?: $activeEvent?->event_id;
        $perPage = \App\Http\Requests\ListRequest::clampPerPage($request->query('per_page'));
        $teamCards = $this->query->teamCards($eventId);

        if (! $eventId) {
            return response()->json([
                'data' => [
                    'area_label' => $this->coverage->label(),
                    'active_event' => null,
                    'summary' => $this->query->summary(null, $teamCards),
                    'team_coverage' => $this->query->teamCoverage(null),
                    'teams' => $teamCards,
                    'responders' => $this->query->responders(null),
                    'risk_areas' => collect(),
                    'active_dispatches' => [],
                    'sitio_coverage' => [],
                    'dispatches' => [
                        'data' => [],
                        'meta' => [
                            'current_page' => 1,
                            'per_page' => $perPage,
                            'total' => 0,
                            'last_page' => 1,
                            'from' => null,
                            'to' => null,
                        ],
                    ],
                    'activity_log' => $this->query->activity(null),
                    'dispatch_history' => $this->query->activity(null, 50),
                ],
            ]);
        }

        $dispatches = $this->query->dispatches($request, (string) $eventId);
        $riskAreas = $this->query->riskAreas($eventId);
        $activeAssignments = $this->query->activeDispatches((string) $eventId);
        $sitioCoverage = app(\App\Queries\SitioDispatchCoverageQuery::class)->forEvent((string) $eventId, $riskAreas, $activeAssignments);

        return response()->json([
            'data' => [
                'area_label' => $this->coverage->label(),
                'active_event' => $activeEvent ? $this->presenter->activeEvent($activeEvent) : null,
                'summary' => $this->query->summary($eventId, $teamCards),
                'team_coverage' => $this->query->teamCoverage($eventId),
                'teams' => $teamCards,
                'responders' => $this->query->responders($eventId),
                'risk_areas' => $riskAreas,
                'active_dispatches' => $activeAssignments,
                'sitio_coverage' => $sitioCoverage,
                'dispatches' => [
                    'data' => collect($dispatches->items())
                        ->map(fn (object $dispatch): array => $this->presenter->dispatch($dispatch))
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
                'activity_log' => $this->query->activity($eventId),
                'dispatch_history' => $this->query->activity(null, 50),
            ],
        ]);
    }

    private function pageView(Request $request): JsonResponse
    {
        $event = $this->query->activeEvent();
        $eventId = $request->query('event_id') ?: $event?->event_id;
        $data = ['active_event' => $event ? $this->presenter->activeEvent($event) : null, 'dispatch_statuses' => app(\App\Queries\DispatchStatusQuery::class)->options()];
        if ($request->query('view') === 'coverage') {
            return response()->json(['data' => $data + ['teams' => $this->query->teamCoverage($eventId), 'responders' => $this->query->responders($eventId)]]);
        }
        if ($request->query('view') === 'management') {
            $page = $eventId ? $this->query->dispatches($request, (string) $eventId) : null;
            return response()->json(['data' => $data + ['dispatches' => [
                'data' => $page ? collect($page->items())->map(fn ($row) => $this->presenter->managementDispatch($row))->values() : [],
                'meta' => ['current_page' => $page?->currentPage() ?? 1, 'per_page' => $page?->perPage() ?? 20,
                    'total' => $page?->total() ?? 0, 'last_page' => $page?->lastPage() ?? 1,
                    'from' => $page?->firstItem(), 'to' => $page?->lastItem()],
            ]]]);
        }
        $teams = $this->query->teamCards($eventId);
        $priorities = $eventId ? app(\App\Queries\SitioPriorityQuery::class)->ranked((string) $eventId) : [];
        $active = $eventId ? $this->query->activeDispatches((string) $eventId) : [];
        return response()->json(['data' => $data + [
            'summary' => $this->query->summary($eventId, $teams),
            'priorities' => app(\App\Queries\SitioDispatchCoverageQuery::class)->withActiveAssignments($priorities, $active),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('assignments')) {
            $payload = $request->validate(['assignments' => ['required', 'array', 'min:1', 'max:20'], 'assignments.*' => ['required', 'array']]);
            $responses = DB::transaction(function () use ($payload, $request): array {
                return array_map(function (array $assignment) use ($request): array {
                    if (array_key_exists('assignments', $assignment)) throw ValidationException::withMessages(['assignments' => ['Nested dispatch batches are not supported.']]);
                    $child = Request::create($request->path(), 'POST', $assignment);
                    $child->setUserResolver($request->getUserResolver());
                    $response = $this->store($child);
                    if ($response->getStatusCode() >= 400) abort($response);
                    return $response->getData(true)['data'];
                }, $payload['assignments']);
            }, 3);
            return response()->json(['message' => 'Dispatch assignments created.', 'data' => ['assignments' => $responses]], 201);
        }
        $activeEvent = $this->query->activeEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'Create a dispatch only after a disaster event is active.',
            ], 409);
        }

        $validated = $this->validateDispatch($request);
        if (($validated['dispatch_type'] ?? 'rescue') === 'welfare_check') {
            if ((int) ($validated['responder_count'] ?? 0) !== 2 || empty($validated['household_id'])
                || ! app(WelfareCheckQuery::class)->qualifies($validated['household_id'], (string) $activeEvent->event_id)) {
                throw ValidationException::withMessages(['household_id' => ['A welfare check needs an eligible no-contact household and exactly two rescuers.']]);
            }
        }
        $creation = $this->workflow->store($request, $validated, $activeEvent);
        $dispatch = $creation['dispatch'];
        $assignmentId = (int) $dispatch['assignment_id'];
        $selectedResponderIds = $creation['responder_ids'];
        $leaderId = RescueTeam::query()->where('team_id', $validated['team_id'])->value('leader_responder_id');
        if ($leaderId) $selectedResponderIds = array_values(array_unique([...$selectedResponderIds, (int) $leaderId]));

        $pushJob = new SendDispatchPush($selectedResponderIds, $assignmentId, (string) $dispatch['assignment_code'],
            (string) $dispatch['assigned_area'], (string) $activeEvent->event_id,
            count(array_unique($dispatch['household_ids'] ?? [])) ?: (int) ($dispatch['households_to_cover'] ?? 0));
        // Prepare the assigned household routes before announcing the assignment.
        $jobs = ! empty($validated['team_id']) && Schema::hasTable('cached_routes')
            ? array_map(fn ($householdId) => new \App\Jobs\PrecomputeRoutesForTeam((int) $validated['team_id'], (string) $householdId), $dispatch['household_ids'] ?? []) : [];
        $jobs[] = $pushJob;
        \Illuminate\Support\Facades\Bus::chain($jobs)->onConnection('operations_outbox')->onQueue('operations')->dispatch();
        $pushResult = ['status' => 'queued', 'recipient_count' => count($selectedResponderIds), 'sent_count' => 0];

        return response()->json([
            'message' => 'Dispatch assignment created.',
            'data' => array_merge($dispatch, [
                'push_delivery' => $pushResult,
                'requested_responder_count' => $creation['requested_count'],
                'available_responder_count' => $creation['available_count'],
                'assigned_responder_count' => $creation['assigned_count'],
                'quantity_capped' => $creation['assigned_count'] < $creation['requested_count'],
            ]),
        ], 201);
    }

    public function show(int $assignmentId): JsonResponse
    {
        $dispatch = $this->query->dispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'dispatch' => $this->presenter->dispatch($dispatch),
                'report_outcomes' => $this->query->reportOutcomes($dispatch),
                'covered_households' => $this->query->coverageHouseholds($dispatch),
                'route' => $this->query->route($assignmentId),
            ],
        ]);
    }

    public function update(Request $request, int $assignmentId): JsonResponse
    {
        $dispatch = $this->query->dispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        $this->workflow->authorize($request, $dispatch);

        $validated = $this->validateDispatch($request, false);
        $this->workflow->update($request, $assignmentId, $dispatch, $validated);

        $selectedIds = $this->presenter->dispatch($dispatch)['selected_responder_ids'];
        SendDispatchChangePush::dispatch($selectedIds, $assignmentId, (string) $dispatch->assignment_code,
            (string) $dispatch->disaster_id, 'updated')
            ->onConnection('operations_outbox')->onQueue('operations')->afterCommit();

        return response()->json([
            'message' => 'Dispatch assignment updated.',
            'data' => $this->getDispatchById($assignmentId),
        ]);
    }

    public function complete(Request $request, int $assignmentId): JsonResponse
    {
        $dispatch = $this->query->dispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'safe_count' => ['nullable', 'integer', 'min:0'], 'evacuated_count' => ['nullable', 'integer', 'min:0'],
            'unsafe_count' => ['nullable', 'integer', 'min:0'], 'injured_count' => ['nullable', 'integer', 'min:0'],
            'missing_count' => ['nullable', 'integer', 'min:0'], 'pending_count' => ['nullable', 'integer', 'min:0'],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->workflow->complete($request, $assignmentId, $dispatch, $validated);
        SendDispatchChangePush::dispatch($this->presenter->dispatch($dispatch)['selected_responder_ids'],
            $assignmentId, (string) $dispatch->assignment_code, (string) $dispatch->disaster_id, 'completed')
            ->onConnection('operations_outbox')->onQueue('operations')->afterCommit();
        return response()->json([
            'message' => 'Dispatch assignment completed.',
            'data' => $this->getDispatchById($assignmentId),
        ]);
    }

    public function updateLocation(Request $request, int $assignmentId): JsonResponse
    {
        $dispatch = $this->query->dispatchRecord($assignmentId);

        if (! $dispatch) {
            return response()->json([
                'message' => 'Dispatch assignment was not found.',
            ], 404);
        }

        $this->workflow->authorize($request, $dispatch);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->workflow->updateLocation($request, $assignmentId, $dispatch, $validated);

        return response()->json([
            'message' => 'Rescuer location updated.',
        ]);
    }

    private function validateDispatch(Request $request, bool $isCreate = true): array
    {
        $required = $isCreate ? 'required' : 'sometimes';

        $validated = $request->validate([
            'team_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:rescue_teams,team_id'],
            'sitio_id' => ['sometimes', 'integer', 'min:1', 'exists:sitios,sitio_id'],
            'purok_id' => ['sometimes', 'integer', 'min:1', 'exists:puroks,purok_id'],
            'household_id' => ['nullable', 'string', 'max:255'],
            'household_ids' => [$isCreate ? 'required_with:household_groups' : 'prohibited', 'array', 'list', 'min:1', 'max:1000'],
            'household_ids.*' => ['required', 'string', 'distinct', 'max:255'],
            'household_groups' => [$isCreate ? 'sometimes' : 'prohibited', 'array', 'list', 'max:100'],
            'household_groups.*' => ['present', 'array', 'list'],
            'household_groups.*.*' => ['required', 'string', 'max:255'],
            'selected_responder_ids' => [$isCreate ? 'sometimes' : 'prohibited', 'array', 'list', 'min:1', 'max:100'],
            'selected_responder_ids.*' => ['required', 'integer', 'distinct', 'exists:responders,responder_id'],
            'assigned_area' => [$required, 'string', 'max:150'],
            'households_to_cover' => ['nullable', 'integer', 'min:0'],
            'responder_count' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1', 'max:100'],
            'dispatch_type' => ['sometimes', 'string', 'in:rescue,welfare_check'],
            'priority_level' => [$required, 'string', 'in:critical,high,watch,monitor'],
            'status' => [$required, 'string', \Illuminate\Validation\Rule::exists('dispatch_statuses', 'status_key')->where(function ($query) use ($isCreate): void {
                $query->where('is_active', true);
                if ($isCreate) $query->where('allow_create', true);
            })],
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
        // Nested wildcard validation may insert empty groups before populated ones.
        // Preserve the numeric positions that pair groups with selected responder IDs.
        if (isset($validated['household_groups'])) {
            ksort($validated['household_groups'], SORT_NUMERIC);
            $validated['household_groups'] = array_values($validated['household_groups']);
        }
        return $validated;
    }

    private function getDispatchById(int $assignmentId): array { return $this->presenter->dispatch($this->query->dispatchRecord($assignmentId)); }

}







