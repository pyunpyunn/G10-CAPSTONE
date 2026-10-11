<?php

namespace App\Services\Mobile;

use App\Queries\RescuerAccountQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescuerTeamWriteWorkflow
{
    public function __construct(private RescuerAccountSupport $support, private RescuerTeamWorkflow $teamWorkflow, private RescuerAccountQuery $query, private RescuerAccountAuditLogger $audit) {}

    public function storeTeam(Request $request): JsonResponse
    {
        $validated = $this->teamWorkflow->validateTeamPayload($request);

        $teamId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $teamId = $this->support->nextId('rescue_teams', 'team_id');
            $memberIds = $this->teamWorkflow->normalizedResponderIds($validated['member_ids'] ?? []);
            $leaderId = $validated['leader_responder_id'] ?? null;

            if ($leaderId && ! in_array((int) $leaderId, $memberIds, true)) {
                $memberIds[] = (int) $leaderId;
            }

            $this->teamWorkflow->ensureUniqueTeam($validated['team_name'], $validated['team_code'], null);
            $this->teamWorkflow->ensureRespondersCanMove($memberIds, $teamId);

            DB::table('rescue_teams')->insert([
                'team_id' => $teamId,
                'team_code' => $this->support->normalizeTeamCode($validated['team_code']),
                'team_name' => trim($validated['team_name']),
                'team_type' => trim($validated['team_type']),
                'assigned_purok_id' => $validated['assigned_purok_id'] ?? null,
                'leader_responder_id' => $leaderId,
                'duty_status' => $validated['duty_status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->teamWorkflow->syncTeamMembers($teamId, $memberIds, $leaderId, $now);
            $this->audit->writeTeamAuditLog($request, 'create_team', $teamId, null, $validated);

            return $teamId;
        });

        return response()->json([
            'message' => 'Rescue team configured.',
            'data' => $this->query->teamConfigWorkspace($teamId),
        ], 201);
    }

    public function updateTeam(Request $request, int $teamId): JsonResponse
    {
        $existing = DB::table('rescue_teams')->where('team_id', $teamId)->first();

        if (! $existing) {
            return response()->json([
                'message' => 'Rescue team was not found.',
            ], 404);
        }

        $validated = $this->teamWorkflow->validateTeamPayload($request, $teamId);

        DB::transaction(function () use ($request, $teamId, $existing, $validated): void {
            $now = now();
            $memberIds = $this->teamWorkflow->normalizedResponderIds($validated['member_ids'] ?? []);
            $leaderId = $validated['leader_responder_id'] ?? null;

            if ($leaderId && ! in_array((int) $leaderId, $memberIds, true)) {
                $memberIds[] = (int) $leaderId;
            }

            $this->teamWorkflow->ensureUniqueTeam($validated['team_name'], $validated['team_code'], $teamId);
            $this->teamWorkflow->ensureRespondersCanMove($memberIds, $teamId);
            $this->teamWorkflow->ensureCurrentTeamMembersCanBeRemoved($teamId, $memberIds);

            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->update([
                    'team_code' => $this->support->normalizeTeamCode($validated['team_code']),
                    'team_name' => trim($validated['team_name']),
                    'team_type' => trim($validated['team_type']),
                    'assigned_purok_id' => $validated['assigned_purok_id'] ?? null,
                    'leader_responder_id' => $leaderId,
                    'duty_status' => $validated['duty_status'],
                    'updated_at' => $now,
                ]);

            $this->teamWorkflow->syncTeamMembers($teamId, $memberIds, $leaderId, $now);
            $this->audit->writeTeamAuditLog($request, 'update_team', $teamId, $existing, $validated);
        });

        return response()->json([
            'message' => 'Rescue team updated.',
            'data' => $this->query->teamConfigWorkspace($teamId),
        ]);
    }

    public function deleteTeam(Request $request, int $teamId): JsonResponse
    {
        $existing = DB::table('rescue_teams')->where('team_id', $teamId)->first();

        if (! $existing) {
            return response()->json([
                'message' => 'Rescue team was not found.',
            ], 404);
        }

        $activeDispatchCount = DB::table('responder_assignments')
            ->where('team_id', $teamId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        if ($activeDispatchCount > 0) {
            return response()->json([
                'message' => 'This team has an active dispatch. Complete or cancel the dispatch before deleting the team.',
            ], 409);
        }

        $this->teamWorkflow->ensureCurrentTeamMembersCanBeRemoved($teamId, []);

        DB::transaction(function () use ($request, $teamId, $existing): void {
            $now = now();

            DB::table('responders')
                ->where('team_id', $teamId)
                ->update([
                    'team_id' => null,
                    'updated_at' => $now,
                ]);

            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->delete();

            $this->audit->writeTeamAuditLog($request, 'delete_team', $teamId, $existing, [
                'team_id' => null,
                'members_moved_to' => 'Unassigned',
            ]);
        });

        return response()->json([
            'message' => 'Rescue team deleted. Members were moved to Unassigned.',
            'data' => $this->query->teamConfigWorkspace(),
        ]);
    }
}







