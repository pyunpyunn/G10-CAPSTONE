<?php

namespace App\Services\Web;

use App\Jobs\SendDispatchPush;

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

    public function index(Request $request): JsonResponse
    {
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
                    'teams' => $teamCards,
                    'responders' => $this->query->responders(null),
                    'risk_areas' => collect(),
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

        return response()->json([
            'data' => [
                'area_label' => $this->coverage->label(),
                'active_event' => $activeEvent ? $this->presenter->activeEvent($activeEvent) : null,
                'summary' => $this->query->summary($eventId, $teamCards),
                'teams' => $teamCards,
                'responders' => $this->query->responders($eventId),
                'risk_areas' => $this->query->riskAreas($eventId),
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

    public function store(Request $request): JsonResponse
    {
        $activeEvent = $this->query->activeEvent();

        if (! $activeEvent) {
            return response()->json([
                'message' => 'Create a dispatch only after a disaster event is active.',
            ], 409);
        }

        $validated = $this->validateDispatch($request);

        $validated = $this->validateDispatch($request);
        $creation = $this->workflow->store($request, $validated, $activeEvent);
        $dispatch = $creation['dispatch'];
        $assignmentId = (int) $dispatch['assignment_id'];
        $selectedResponderIds = $creation['responder_ids'];

        $dispatch = $creation['dispatch'];
        $assignmentId = (int) $dispatch['assignment_id'];

        SendDispatchPush::dispatch($selectedResponderIds, $assignmentId, (string) $dispatch['assignment_code'],
            (string) $dispatch['assigned_area'], (string) $activeEvent->event_id)
            ->onConnection('operations_outbox')->onQueue('operations');
        $pushResult = ['status' => 'queued', 'recipient_count' => count($selectedResponderIds), 'sent_count' => 0];

        return response()->json([
            'message' => 'Dispatch assignment created.',
            'data' => array_merge($dispatch, [
                'push_delivery' => $pushResult,
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

        return $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:rescue_teams,team_id'],
            'responder_id' => ['nullable', 'integer', 'exists:responders,responder_id'],
            'selected_responder_ids' => ['nullable', 'array'],
            'selected_responder_ids.*' => ['integer', 'exists:responders,responder_id'],
            'household_id' => ['nullable', 'string', 'max:255'],
            'assigned_area' => [$required, 'string', 'max:150'],
            'households_to_cover' => ['nullable', 'integer', 'min:0'],
            'responder_count' => ['nullable', 'integer', 'min:1'],
            'priority_level' => [$required, 'string', 'in:critical,high,watch,monitor'],
            'status' => [$required, 'string', 'in:standby,dispatched,accepted,en_route,on_scene,onscene,returning,completed,cancelled'],
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

    private function getDispatchById(int $assignmentId): array { return $this->presenter->dispatch($this->query->dispatchRecord($assignmentId)); }

}







