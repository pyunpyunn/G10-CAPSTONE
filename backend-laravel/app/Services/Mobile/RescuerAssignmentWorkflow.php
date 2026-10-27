<?php

namespace App\Services\Mobile;

use App\Jobs\RefreshResponderRoadRoute;
use App\Queries\RescuerAssignmentQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;

class RescuerAssignmentWorkflow
{
    public function __construct(private \App\Services\Shared\RoutingService $routingService, private \App\Services\Mobile\RescuerMobileSupport $support, private RescuerAssignmentQuery $assignmentQuery) {}

    public function updateAssignmentStatus(Request $request, int $assignmentId): JsonResponse|array
    {
        if (! Schema::hasTable('responder_assignments')) {
            return $this->missingTableResponse('responder_assignments');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::exists('dispatch_statuses', 'status_key')->where('is_active', true)->where('allow_mobile', true)],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
            'household_id' => ['nullable', 'string', 'max:100'],
            'household_status_key' => ['nullable', Rule::in(['safe', 'evacuated'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ], [
            'status.required' => 'Select the mission status.',
            'status.in' => 'Select a valid mission status.',
        ]);

        $responder = $this->support->responderForUser($request->user());
        $assignment = $this->assignmentQuery->find($assignmentId, $responder?->responder_id);

        if (! $assignment) {
            return response()->json([
                'message' => 'Assignment was not found for your rescuer account.',
            ], 404);
        }

        if (Schema::hasTable('disaster_events') && isset($assignment->disaster_id)) {
            $event = $this->support->activeEvent();
            if (!$event || (string)$event->event_id !== (string)$assignment->disaster_id) abort(409, 'This dispatch is not part of the active disaster event.');
        }

        $validated['responder_id'] = (int) $responder->responder_id;
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

        DB::transaction(function () use ($assignmentId, $validated, $responder, $status, $now, $updates, $request): void {
            $current = DB::table('responder_assignments')->where('assignment_id', $assignmentId)->lockForUpdate()->first();
            if (! $current || ! $this->assignmentBelongsToResponder($current, (int) $responder?->responder_id)
                || in_array($current->status, ['cancelled', 'completed'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => ['This assignment is already closed. Refresh your dispatch list before updating it.'],
                ]);
            }

            $target = \App\Support\RescuerHouseholdQueue::target($current, (int) $responder->responder_id);
            $coverage = $this->decodeJson($current->route_notes ?? null)['responder_households'] ?? [];
            if ($status === 'completed' && array_key_exists((int) $responder->responder_id, $coverage) && ! $target) {
                abort(409, 'Your household queue is complete. Other rescuers still have their own assigned households.');
            }
            if ($status === 'completed' && isset($validated['household_id']) && $validated['household_id'] !== $target) {
                abort(409, 'The current household changed. Refresh your queue before completing it.');
            }
            $notes = $this->decodeJson($current->route_notes ?? null);
            $actorId = (int) $responder->responder_id;
            $selectedIds = $notes['selected_responder_ids'] ?? [$actorId];
            $previous = \App\Support\RescuerHouseholdQueue::status($current, $actorId);
            $allowed = ['accepted' => ['dispatched', 'accepted'], 'en_route' => ['accepted', 'en_route'],
                'on_scene' => ['en_route', 'on_scene'], 'completed' => ['on_scene'],
                'cancelled' => ['dispatched', 'accepted', 'en_route', 'on_scene']];
            if (!isset($allowed[$status]) || !in_array($previous, $allowed[$status], true)) {
                abort(409, 'Invalid task transition. Accept the household, start navigation, and mark arrival before completing it.');
            }
            if ($status === 'completed' && $target) {
                if (empty($validated['household_id']) || $validated['household_id'] !== $target) {
                    abort(409, 'Confirm the current household before completing it.');
                }
                $this->recordHouseholdCompletion($current, $target, $validated, $request, $actorId, $now);
                $notes['household_outcomes'][$target] = $validated['household_status_key'];
                $outcomes = $this->decodeJson($current->outcome_notes ?? null);
                $outcomes['safe_count'] = count(array_filter($notes['household_outcomes'], fn ($key) => $key === 'safe'));
                $outcomes['evacuated_count'] = count(array_filter($notes['household_outcomes'], fn ($key) => $key === 'evacuated'));
                $updates['outcome_notes'] = json_encode($outcomes);
                $notes['completed_households'][$actorId] = array_values(array_unique([
                    ...($notes['completed_households'][$actorId] ?? []), $target,
                ]));
                if (Schema::hasTable('responder_routes')) {
                    $routes = DB::table('responder_routes')->where('assignment_id', $assignmentId);
                    if (Schema::hasColumn('responder_routes', 'responder_id')) $routes->where('responder_id', $actorId);
                    if (Schema::hasColumn('responder_routes', 'household_id')) $routes->where('household_id', $target);
                    $routes->update($this->support->filterColumns('responder_routes', ['route_status' => 'completed', 'updated_at' => $now]));
                }
            }
            foreach ($selectedIds as $id) $notes['responder_statuses'][(int)$id] ??= str_replace('-', '_', $current->status);
            $current->route_notes = json_encode($notes);
            $personalStatus = $status === 'completed' && \App\Support\RescuerHouseholdQueue::pending($current, $actorId)
                ? 'dispatched' : $status;
            $notes['responder_statuses'][$actorId] = $personalStatus;
            $current->route_notes = json_encode($notes);
            $updates['route_notes'] = $current->route_notes;
            // Shared HQ status summarizes the most advanced active rescuer;
            // each mobile account retains its own acceptance and navigation state.
            $updates['status'] = \App\Support\RescuerHouseholdQueue::overallStatus($current);
            if ($updates['status'] === 'completed') {
                $updates['completed_at'] = $current->completed_at ?? $now;
                foreach ($selectedIds as $id) $notes['responder_statuses'][(int) $id] = 'completed';
                $updates['route_notes'] = json_encode($notes);
            } else unset($updates['completed_at']);
            DB::table('responder_assignments')->where('assignment_id', $assignmentId)->update($updates);
            if ($updates['status'] === 'completed' && Schema::hasTable('responder_routes')) {
                DB::table('responder_routes')->where('assignment_id', $assignmentId)
                    ->update($this->support->filterColumns('responder_routes', ['route_status' => 'completed', 'updated_at' => $now]));
            }
            if ($this->hasPoint($validated)) {
                $this->saveResponderLocation($responder, $validated, $now);
                $this->routeWriter()->saveRouteCoordinate($assignmentId, $validated, $now);

            }
            $this->updateResponderDuty($actorId, $current->team_id ?? $responder?->team_id, $personalStatus);
            if ($updates['status'] === 'completed') {
                foreach ($selectedIds as $id) {
                    if ((int) $id === $actorId) continue;
                    $hasOtherWork = DB::table('responder_assignments')->where('assignment_id', '<>', $assignmentId)
                        ->whereIn('status', app(\App\Queries\DispatchStatusQuery::class)->keys(true))
                        ->where(fn ($query) => $query->where('responder_id', (int) $id)
                            ->orWhereJsonContains('route_notes->selected_responder_ids', (int) $id))->exists();
                    if (! $hasOtherWork) $this->updateResponderDuty((int) $id, $current->team_id, 'completed');
                }
            }
            $this->support->writeAuditLog($request, 'mobile_update_assignment', 'responder_assignments', (string) $assignmentId, $updates + ['previous_status' => $current->status]);
        }, 3);

        // The accepted response must contain a route even without a queue worker.
        // Network routing happens after the assignment transaction has committed.
        if (in_array($status, ['accepted', 'en_route'], true) && $this->hasPoint($validated)) {
            $this->refreshPlannedRoadRoute($assignmentId, $validated);
        }

        $updatedAssignment = $this->assignmentQuery->find($assignmentId, $responder?->responder_id);
        if ($updatedAssignment) {
            $updatedAssignment->mobile_route = $this->assignmentQuery->routeForAssignment($assignmentId, $responder?->responder_id);
        }

        return [
            'message' => 'Assignment status updated.',
            'assignment' => $updatedAssignment,
            'route' => $updatedAssignment?->mobile_route,
        ];
    }

    public function storeLocation(Request $request, int $assignmentId): JsonResponse
    {
        if (! Schema::hasTable('responder_location_logs')) {
            return $this->missingTableResponse('responder_location_logs');
        }

        $validated = $request->validate([
            'recorded_at' => ['nullable', 'date'],
            'refresh_route' => ['sometimes', 'boolean'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ], [
            'latitude.required' => 'Latitude is required.',
            'longitude.required' => 'Longitude is required.',
        ]);

        $responder = $this->support->responderForUser($request->user());
        $assignment = $this->assignmentQuery->find($assignmentId, $responder?->responder_id);

        if (! $assignment) {
            return response()->json([
                'message' => 'Assignment was not found for your rescuer account.',
            ], 404);
        }

        if (Schema::hasTable('disaster_events') && isset($assignment->disaster_id)) {
            $event = $this->support->activeEvent();
            if (!$event || (string)$event->event_id !== (string)$assignment->disaster_id) abort(409, 'This dispatch is not in the active event.');
        }
        $validated['responder_id'] = (int) $responder->responder_id;
        $now = now();
        DB::transaction(function () use ($assignmentId, $validated, $responder, $now, $request): void {
            $current = DB::table('responder_assignments')->where('assignment_id', $assignmentId)->lockForUpdate()->first();
            if (!$current || !in_array(\App\Support\RescuerHouseholdQueue::status($current, (int)$responder->responder_id), ['dispatched', 'accepted', 'en_route', 'on_scene'], true)) abort(409, 'This household task is not active.');
            if (!empty($validated['recorded_at'])) {
                $captured = \Illuminate\Support\Carbon::parse($validated['recorded_at']);
                $last = DB::table('responder_location_logs')->where('responder_id',$responder->responder_id)->max('logged_at');
                if ($captured->lt(now()->subSeconds(60)) || $captured->gt(now()->addSeconds(10)) || ($last && $captured->lt(\Illuminate\Support\Carbon::parse($last)))) abort(409, 'An older GPS fix cannot replace your current position.');
            }
            $logId = $this->saveResponderLocation($responder, $validated, $now);
            $this->routeWriter()->saveRouteCoordinate($assignmentId, $validated, $now);
            if (!empty($validated['refresh_route'])) {
                RefreshResponderRoadRoute::dispatch($assignmentId, $validated)
                    ->onConnection('operations_outbox')->onQueue('operations');
            }
            $this->updateResponderDuty($responder->responder_id, $responder->team_id, 'deployed');
            $this->support->writeAuditLog($request, 'mobile_location_update', 'responder_location_logs', (string) $logId, $validated);
        }, 3);

        $route = $this->assignmentQuery->routeForAssignment($assignmentId, (int)$responder->responder_id);
        return response()->json([
            'message' => 'Location updated.',
            'data' => ['route' => $route ? (new \App\Http\Resources\RescuerMobileRouteResource($route))->resolve($request) : null],
        ]);
    }

    private function recordHouseholdCompletion(object $assignment, string $householdId, array $data, Request $request, int $responderId, $now): void
    {
        if (empty($data['household_status_key'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['household_status_key' => ['Confirm Safe or Evacuated to complete this household. A detailed field report is optional.']]);
        }
        $key = $data['household_status_key'];
        $statusId = $this->support->resolveHouseholdStatusId($key);
        if (!$statusId) abort(503, 'The selected household status is not configured.');
        $notes = $data['outcome_notes'] ?? 'Household confirmed '.$key.' by the assigned rescuer.';
        DB::table('household_status_logs')->insert($this->support->filterColumns('household_status_logs', [
            'status_log_id' => $this->support->nextId('household_status_logs', 'status_log_id'),
            'household_id' => $householdId, 'disaster_id' => $assignment->disaster_id,
            'status_id' => $statusId, 'source' => 'responder_completion', 'responder_id' => $responderId,
            'submitted_by_user_id' => $request->user()?->user_id, 'notes' => $notes,
            'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]));
        app(\App\Actions\UpdateHouseholdStatus::class)->apply(
            (string)$assignment->disaster_id, $householdId, (int)$statusId, $key, 'responder_completion',
            $notes, $request->user()?->user_id, null, [], false, false,
        );
    }

    private function saveResponderLocation(?object $responder, array $validated, $now): int
    {
        if (! $responder || ! Schema::hasTable('responder_location_logs')) {
            return 0;
        }

        $logId = $this->support->nextId('responder_location_logs', 'log_id');

        DB::table('responder_location_logs')->insert($this->support->filterColumns('responder_location_logs', [
            'log_id' => $logId,
            'responder_id' => $responder->responder_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'logged_at' => !empty($validated['recorded_at']) ? \Illuminate\Support\Carbon::parse($validated['recorded_at'])->setTimezone(config('app.timezone')) : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        return $logId;
    }

    private function hasPoint(array $data): bool
    {
        return array_key_exists('latitude', $data)
            && array_key_exists('longitude', $data)
            && $data['latitude'] !== null
            && $data['longitude'] !== null;
    }

    private function assignmentBelongsToResponder(object $assignment, int $responderId): bool
    {
        if ((int) ($assignment->responder_id ?? 0) === $responderId) {
            return true;
        }

        $routeNotes = json_decode((string) ($assignment->route_notes ?? ''), true);
        $selectedIds = is_array($routeNotes) ? ($routeNotes['selected_responder_ids'] ?? []) : [];

        return in_array($responderId, array_map('intval', $selectedIds), true);
    }

    public function refreshPlannedRoadRoute(int $assignmentId, array $start): void
    {
        $this->routeWriter()->refreshPlannedRoadRoute($assignmentId, $start);
    }

    private function routeWriter(): RescuerRouteWriter
    {
        return new RescuerRouteWriter($this->routingService, $this->support, $this->assignmentQuery);
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
            ->update($this->support->filterColumns('responders', [
                'is_deployed' => $isActive ? 1 : 0,
                'duty_status' => $dutyStatus,
                'last_active_at' => now(),
                'updated_at' => now(),
            ]));

        if ($teamId && Schema::hasTable('rescue_teams')) {
            $hasAvailable = DB::table('responders')->where('team_id', $teamId)
                ->where('duty_status', 'available')->where('is_deployed', false)->exists();
            $hasDeployed = DB::table('responders')->where('team_id', $teamId)
                ->where('is_deployed', true)->exists();
            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->update($this->support->filterColumns('rescue_teams', [
                    'duty_status' => $hasAvailable ? 'available' : ($hasDeployed ? 'deployed' : 'available'),
                    'updated_at' => now(),
                ]));
        }
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

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => 'The required '.$table.' table is not available in the current database.',
        ], 503);
    }
}







