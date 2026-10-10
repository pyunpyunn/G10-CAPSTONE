<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Resources\WelfareCheckResource;
use App\Http\Resources\MemberCheckResource;
use App\Http\Resources\RescuePriorityResource;
use App\Actions\UpdateRescuePrioritySettings;
use App\Models\RescuePrioritySetting;
use App\Queries\RescuePriorityQuery;
use App\Queries\RescueCriteriaQuery;
use App\Queries\SitioPriorityQuery;
use App\Queries\WelfareCheckQuery;
use App\Queries\UnreportedMemberQuery;
use App\Queries\RescueDispatchQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Services\Web\RescueDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescueDispatchController extends Controller
{
    private RescueDispatchService $service;

    public function __construct(RescueDispatchService $service)
    {
        $this->service = $service;
    }

    public function teams(): JsonResponse
    {
        return $this->service->teams();
    }

    public function communications(ListRequest $request): JsonResponse
    {
        return (new \App\Http\Resources\FieldCommunicationWorkspaceResource($this->service->communications($request)))->response();
    }

    public function index(ListRequest $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function welfareChecks(ListRequest $request, WelfareCheckQuery $query, RescueDispatchQuery $dispatch): AnonymousResourceCollection
    {
        $event = $dispatch->activeEvent();

        return WelfareCheckResource::collection($event
            ? $query->households((string) $event->event_id, ListRequest::clampPerPage($request->query('per_page')))
            : collect());
    }

    public function memberCheckQueue(ListRequest $request, UnreportedMemberQuery $query, RescueDispatchQuery $dispatch): AnonymousResourceCollection
    {
        $event = $dispatch->activeEvent();

        return MemberCheckResource::collection($event
            ? $query->reminderQueue((string) $event->event_id, ListRequest::clampPerPage($request->query('per_page')))
            : collect());
    }

    public function priorities(ListRequest $request, RescuePriorityQuery $query, RescueDispatchQuery $dispatch): AnonymousResourceCollection
    {
        $event = $dispatch->activeEvent();

        return RescuePriorityResource::collection($event
            ? $query->ranked((string) $event->event_id, ListRequest::clampPerPage($request->query('per_page')))
            : collect());
    }

    public function purokPriorities(RescueCriteriaQuery $query, RescueDispatchQuery $dispatch): JsonResponse
    {
        $event = $dispatch->activeEvent();
        return response()->json(['data' => $event ? $query->puroks((string) $event->event_id) : []]);
    }

    public function sitioPriorities(SitioPriorityQuery $query, RescueDispatchQuery $dispatch): JsonResponse
    {
        $event = $dispatch->activeEvent();
        return response()->json(['data' => $event ? $query->ranked((string) $event->event_id) : []]);
    }

    public function criteriaTimeline(RescueCriteriaQuery $query, RescueDispatchQuery $dispatch, RescuePriorityQuery $priorities): JsonResponse
    {
        $event = $dispatch->activeEvent();
        return response()->json([
            'data' => $event ? $query->sitioTimeline((string) $event->event_id, $event->started_at) : [],
            'meta' => [
                'event_id' => $event?->event_id,
                'started_at' => $event?->started_at?->toIso8601String(),
                'ended_at' => $event?->ended_at?->toIso8601String(),
                'server_time' => now()->toIso8601String(),
                'weights' => RescueCriteriaQuery::SITIO_WEIGHTS,
                'settings' => $event ? $priorities->settingsForEvent((string) $event->event_id)
                    : RescuePrioritySetting::query()->orderByDesc('version')->first(),
            ],
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function prioritySettings(RescueDispatchQuery $dispatch, RescuePriorityQuery $priorities): JsonResponse
    {
        $event = $dispatch->activeEvent();
        $settings = $event
            ? $priorities->settingsForEvent((string) $event->event_id)
            : RescuePrioritySetting::query()->orderByDesc('version')->first();

        return response()->json(['data' => $settings]);
    }

    public function updatePrioritySettings(Request $request, UpdateRescuePrioritySettings $action): JsonResponse
    {
        $weights = $request->validate([
            'impact_weight' => ['required', 'integer', 'between:0,100'],
            'vulnerability_weight' => ['required', 'integer', 'between:0,100'],
            'unreported_weight' => ['required', 'integer', 'between:0,100'],
            'no_contact_weight' => ['required', 'integer', 'between:0,100'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $reason = $weights['reason'];
        unset($weights['reason']);

        return response()->json(['data' => $action->apply($weights, $request->user()?->user_id, $reason)]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function show(int $assignmentId): JsonResponse
    {
        return $this->service->show($assignmentId);
    }

    public function update(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->update($request, $assignmentId);
    }

    public function complete(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->complete($request, $assignmentId);
    }

    public function updateLocation(Request $request, int $assignmentId): JsonResponse
    {
        return $this->service->updateLocation($request, $assignmentId);
    }
}



