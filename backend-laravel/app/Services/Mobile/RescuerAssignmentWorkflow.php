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

        $responder = $this->support->responderForUser($request->user());
        $assignment = $this->assignmentQuery->find($assignmentId, $responder?->responder_id);

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

        DB::transaction(function () use ($assignmentId, $validated, $responder, $status, $now, $updates, $request): void {
            DB::table('responder_assignments')->where('assignment_id', $assignmentId)->lockForUpdate()->first();
            DB::table('responder_assignments')->where('assignment_id', $assignmentId)->update($updates);
            if ($this->hasPoint($validated)) {
                $this->saveResponderLocation($responder, $validated, $now);
                $this->routeWriter()->saveRouteCoordinate($assignmentId, $validated, $now);
                RefreshResponderRoadRoute::dispatch($assignmentId, $validated)
                    ->onConnection('operations_outbox')->onQueue('operations');
            }
            $this->updateResponderDuty($responder?->responder_id, $responder?->team_id, $status);
            $this->support->writeAuditLog($request, 'mobile_update_assignment', 'responder_assignments', (string) $assignmentId, $updates);
        }, 3);

        $updatedAssignment = $this->assignmentQuery->find($assignmentId, $responder?->responder_id);
        if ($updatedAssignment) {
            $updatedAssignment->mobile_route = $this->assignmentQuery->routeForAssignment($assignmentId);
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

        $now = now();
        DB::transaction(function () use ($assignmentId, $validated, $responder, $now, $request): void {
            DB::table('responder_assignments')->where('assignment_id', $assignmentId)->lockForUpdate()->first();
            $logId = $this->saveResponderLocation($responder, $validated, $now);
            $this->routeWriter()->saveRouteCoordinate($assignmentId, $validated, $now);
            RefreshResponderRoadRoute::dispatch($assignmentId, $validated)
                ->onConnection('operations_outbox')->onQueue('operations');
            $this->updateResponderDuty($responder->responder_id, $responder->team_id, 'deployed');
            $this->support->writeAuditLog($request, 'mobile_location_update', 'responder_location_logs', (string) $logId, $validated);
        }, 3);

        return response()->json([
            'message' => 'Location updated.',
        ]);
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
            'logged_at' => $now,
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
            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->update($this->support->filterColumns('rescue_teams', [
                    'duty_status' => $dutyStatus,
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







