<?php

namespace App\Services\Mobile;

use App\Presenters\RescuerAccountPresenter;
use App\Queries\RescuerAccountQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RescuerAccountWriteWorkflow
{
    public function __construct(private RescuerAccountSupport $support, private RescuerAccountQuery $query, private RescuerAccountPresenter $presenter, private RescuerAccountAuditLogger $audit) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->support->validatePayload($request);
        $this->support->ensureUniqueLogin($validated['account_id'], null);
        $this->support->ensureAccountTeamType($validated['account_id'], $validated);
        $selectedRole = app(\App\Services\Web\PersonnelRoleService::class)->selected($validated, str_starts_with($validated['account_id'], 'BDRRM-HQCC-'));
        if ($selectedRole && ($selectedRole->base_role_key ?: $selectedRole->role_key) === 'hq_leader') $validated['title'] = 'HQ Leader';

        $rescuer = DB::transaction(function () use ($request, $validated, $selectedRole): array {
            $now = now();
            $teamId = $this->support->teamIdFromPayload($validated);
            $teamCode = $this->support->teamCodeFromPayload($validated);
            $roleId = $this->support->roleIdForAccount($teamCode);
            $responderId = $this->support->nextResponderId();
            $fullName = $this->support->fullNameFromPayload($validated);
            $firstName = trim($validated['first_name']);
            $lastName = trim($validated['last_name']);
            $password = $validated['password'] ?? 'password';
            $passwordHash = Hash::make($password);
            $userId = ($teamCode === 'HQCC' ? 'USR-HQCC-' : 'USR-RESCUER-').$validated['account_id'];
            $displayUsername = $this->support->uniqueDisplayUsername($fullName);

            DB::table('users')->insert([
                'user_id' => $userId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $fullName,
                'username' => $displayUsername,
                'email' => $validated['email'] ?? null,
                'password' => $passwordHash,
                'role_id' => $roleId,
                'contact_number' => $validated['contact_number'],
                'is_active' => $validated['account_status'] === 'disabled' ? 0 : 1,
                'must_change_password' => ! empty($validated['password']) ? 0 : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $responderCode = ($validated['responder_code'] ?? null) ?: $validated['account_id'];

            DB::table('responders')->insert([
                'responder_id' => $responderId,
                'user_id' => $userId,
                'responder_code' => $responderCode,
                'created_by_admin_id' => $request->user()?->user_id,
                'team_id' => $teamId,
                'username' => $validated['account_id'],
                'password_hash' => $passwordHash,
                'full_name' => $fullName,
                'title' => $validated['title'],
                'contact_number' => $validated['contact_number'],
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'blood_type' => $validated['blood_type'] ?? null,
                'address' => $validated['address'] ?? null,
                'skills' => $validated['skills'] ?? null,
                'training_notes' => $validated['training_notes'] ?? null,
                'certification_reference' => $validated['certification_reference'] ?? null,
                'equipment_notes' => $validated['equipment_notes'] ?? null,
                'is_validated' => $validated['account_status'] === 'active' ? 1 : 0,
                'is_deployed' => in_array($validated['duty_status'], ['dispatched', 'on_scene'], true) ? 1 : 0,
                'duty_status' => $validated['account_status'] === 'disabled' ? 'disabled' : $validated['duty_status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($teamCode === 'HQCC' && $teamId) {
                if ($validated['account_status'] !== 'disabled' && strtolower($validated['title']) === 'hq leader') DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => $responderId]);
                $leader = DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id');
                app(\App\Services\Web\HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, $leader ? (int) $leader : null);
            }
            if ($teamId && !str_starts_with(strtoupper((string) ($validated['account_id'] ?? $existing->username ?? '')), 'BDRRM-HQCC-')) {
                $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();
                $leaderTitle = strtolower(trim($selectedRole?->role_name ?? $validated['title']));
                if ($validated['account_status'] !== 'disabled' && ($leaderTitle === 'team leader' || app(\App\Services\Web\PersonnelRoleService::class)->isRescueLeader($selectedRole))) DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => $responderId]);
                elseif ((int) $team->leader_responder_id === $responderId) DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => null]);
                $leaderId = DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id');
                app(\App\Services\Web\PersonnelRoleService::class)->synchronizeRescueLeadership($teamId, $leaderId ? (int) $leaderId : null);
            }
            app(\App\Services\Web\PersonnelRoleService::class)->apply($responderId, $selectedRole);
            $this->audit->writeAuditLog($request, 'create', $responderId, null, $validated);

            return $this->presenter->formatResponder($this->query->findResponder($responderId), true);
        });

        return response()->json([
            'message' => 'Verified rescuer account created.',
            'data' => [
                'rescuer' => $rescuer,
            ],
        ], 201);
    }

    public function update(Request $request, int $responderId): JsonResponse
    {
        $existing = $this->query->findResponder($responderId);

        if (! $existing) {
            return response()->json([
                'message' => 'Rescuer account was not found.',
            ], 404);
        }

        abort_if($existing->role_key === 'super_admin' && $request->user()?->roleKey() !== 'super_admin', 403, 'Only Super Admin can edit this account.');
        $validated = $this->support->validatePayload($request, true);
        $this->support->ensureAccountTeamType((string) $existing->username, $validated);
        $isHq = str_starts_with(strtoupper((string) $existing->username), 'BDRRM-HQCC-');
        $selectedRole = app(\App\Services\Web\PersonnelRoleService::class)->selected($validated, $isHq, (int) ($existing->access_role_id ?? 0));
        if ($selectedRole && $isHq && ($selectedRole->base_role_key ?: $selectedRole->role_key) !== 'super_admin') $validated['title'] = ($selectedRole->base_role_key ?: $selectedRole->role_key) === 'hq_leader' ? 'HQ Leader' : 'HQ Personnel';

        $rescuer = DB::transaction(function () use ($request, $validated, $existing, $responderId, $selectedRole): array {
            $now = now();
            $teamId = $this->support->teamIdFromPayload($validated);
            $fullName = $this->support->fullNameFromPayload($validated);
            if ((int) $existing->team_id !== (int) $teamId) {
                DB::table('rescue_teams')->where('leader_responder_id', $responderId)
                    ->update(['leader_responder_id' => null, 'updated_at' => $now]);
            }
            $firstName = trim($validated['first_name']);
            $lastName = trim($validated['last_name']);
            $oldValues = $this->presenter->formatResponder($existing, true);

            $isHqAccount = str_starts_with(strtoupper((string) $existing->username), 'BDRRM-HQCC-');
            $hqLeader = $isHqAccount && $validated['account_status'] !== 'disabled'
                && strtolower(trim($validated['title'])) === 'hq leader';
            if ($hqLeader && ! $teamId) {
                throw \Illuminate\Validation\ValidationException::withMessages(['team_name' => ['Select an HQCC team before assigning its leader.']]);
            }
            if ($isHqAccount) {
                $validated['title'] = $hqLeader ? 'HQ Leader' : 'HQ Personnel';
                if (! $hqLeader) DB::table('rescue_teams')->where('leader_responder_id', $responderId)
                    ->update(['leader_responder_id' => null, 'updated_at' => $now]);
            }

            $userUpdate = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $fullName,
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'],
                'is_active' => $validated['account_status'] === 'disabled' ? 0 : 1,
                'updated_at' => $now,
            ];
            if ($isHqAccount && $existing->role_key !== 'super_admin') {
                $userUpdate['role_id'] = app(\App\Services\Web\HeadquartersTeamSupport::class)->personnelRoleId();
            }

            if (! empty($validated['password'])) {
                $passwordHash = Hash::make($validated['password']);
                $userUpdate['password'] = $passwordHash;
                $userUpdate['must_change_password'] = 0;
            }

            DB::table('users')
                ->where('user_id', $existing->user_id)
                ->update($userUpdate);

            $responderUpdate = [
                'responder_code' => ($validated['responder_code'] ?? null) ?: $existing->responder_code,
                'team_id' => $teamId,
                'full_name' => $fullName,
                'title' => $validated['title'],
                'contact_number' => $validated['contact_number'],
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'blood_type' => $validated['blood_type'] ?? null,
                'address' => $validated['address'] ?? null,
                'skills' => $validated['skills'] ?? null,
                'training_notes' => $validated['training_notes'] ?? null,
                'certification_reference' => $validated['certification_reference'] ?? null,
                'equipment_notes' => $validated['equipment_notes'] ?? null,
                'is_validated' => $validated['account_status'] === 'active' ? 1 : 0,
                'is_deployed' => in_array($validated['duty_status'], ['dispatched', 'on_scene'], true) ? 1 : 0,
                'duty_status' => $validated['account_status'] === 'disabled' ? 'disabled' : $validated['duty_status'],
                'updated_at' => $now,
            ];

            if (! empty($validated['password'])) {
                $responderUpdate['password_hash'] = $passwordHash;
            }

            DB::table('responders')
                ->where('responder_id', $responderId)
                ->update($responderUpdate);

            if ($isHqAccount && $teamId) {
                if ($hqLeader) DB::table('rescue_teams')->where('team_id', $teamId)
                    ->update(['leader_responder_id' => $responderId, 'updated_at' => $now]);
                $leaderId = DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id');
                app(\App\Services\Web\HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, $leaderId ? (int) $leaderId : null);
            }
            if ($isHqAccount && $existing->team_id && (int) $existing->team_id !== (int) $teamId) {
                $oldTeamLeaderId = DB::table('rescue_teams')->where('team_id', $existing->team_id)->value('leader_responder_id');
                app(\App\Services\Web\HeadquartersTeamSupport::class)->synchronizeLeadership(
                    (int) $existing->team_id,
                    $oldTeamLeaderId ? (int) $oldTeamLeaderId : null,
                );
            }
            app(\App\Services\Web\PersonnelAccessNotifier::class)->notify([$existing->user_id]);

            if ($teamId && !str_starts_with(strtoupper((string) ($validated['account_id'] ?? $existing->username ?? '')), 'BDRRM-HQCC-')) {
                $team = DB::table('rescue_teams')->where('team_id', $teamId)->first();
                $leaderTitle = strtolower(trim($selectedRole?->role_name ?? $validated['title']));
                if ($validated['account_status'] !== 'disabled' && ($leaderTitle === 'team leader' || app(\App\Services\Web\PersonnelRoleService::class)->isRescueLeader($selectedRole))) DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => $responderId]);
                elseif ((int) $team->leader_responder_id === $responderId) DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => null]);
                $leaderId = DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id');
                app(\App\Services\Web\PersonnelRoleService::class)->synchronizeRescueLeadership($teamId, $leaderId ? (int) $leaderId : null);
            }
            app(\App\Services\Web\PersonnelRoleService::class)->apply($responderId, $selectedRole);
            $updated = $this->presenter->formatResponder($this->query->findResponder($responderId), true);
            $this->audit->writeAuditLog($request, 'update', $responderId, $oldValues, $updated);

            return $updated;
        });

        return response()->json([
            'message' => 'Rescuer account updated.',
            'data' => [
                'rescuer' => $rescuer,
            ],
        ]);
    }

    public function delete(Request $request, int $responderId): JsonResponse
    {
        return DB::transaction(function () use ($request, $responderId): JsonResponse {
            $record = DB::table('responders')->where('responder_id', $responderId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $record) return response()->json(['message' => 'Rescuer account was not found.'], 404);
            abort_if(\App\Models\User::query()->find($record->user_id)?->roleKey() === 'super_admin', 422, 'The Super Admin account cannot be deleted here.');
            $activeAssignment = DB::table('responder_assignments')->where('responder_id', $responderId)
                ->whereNotIn('status', ['completed', 'cancelled'])->exists();
            if ($record->is_deployed || $activeAssignment) {
                return response()->json(['message' => 'Finish the active dispatch for this rescuer before deleting the account.'], 422);
            }
            $now = now();
            $teamId = $record->team_id ? (int) $record->team_id : null;
            if ($teamId && (int) DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id') === $responderId) {
                DB::table('rescue_teams')->where('team_id', $teamId)->update(['leader_responder_id' => null, 'updated_at' => $now]);
                app(\App\Services\Web\HeadquartersTeamSupport::class)->demoteLeader($record->user_id);
            }
            DB::table('users')->where('user_id', $record->user_id)->update(['is_active' => 0, 'updated_at' => $now]);
            DB::table('responders')->where('responder_id', $responderId)->update([
                'deleted_at' => $now, 'updated_at' => $now, 'is_validated' => 0,
                'duty_status' => 'disabled', 'team_id' => null,
            ]);
            DB::table('rescue_teams')->where('leader_responder_id', $responderId)->update(['leader_responder_id' => null]);
            app(\App\Services\Web\PersonnelAccessNotifier::class)->notify([$record->user_id]);
            $this->audit->writeAuditLog($request, 'delete', $responderId, ['user_id' => $record->user_id], ['deleted_at' => $now->toDateTimeString()]);
            if ($teamId) {
                $leaderId = DB::table('rescue_teams')->where('team_id', $teamId)->value('leader_responder_id');
                app(\App\Services\Web\HeadquartersTeamSupport::class)->synchronizeLeadership($teamId, $leaderId ? (int) $leaderId : null);
            }
            return response()->json(['message' => 'Rescuer account deleted from the roster. Dispatch history is retained.', 'data' => ['responder_id' => $responderId]]);
        });
    }

    public function deactivate(Request $request, int $responderId): JsonResponse
    {
        $existing = $this->query->findResponder($responderId);

        if (! $existing) {
            return response()->json([
                'message' => 'Rescuer account was not found.',
            ], 404);
        }

        abort_if($existing->role_key === 'super_admin', 422, 'The Super Admin account cannot be deactivated here.');
        DB::transaction(function () use ($request, $existing, $responderId): void {
            $now = now();
            $oldValues = $this->presenter->formatResponder($existing, true);
            if ($existing->team_id && (int) DB::table('rescue_teams')->where('team_id', $existing->team_id)->value('leader_responder_id') === $responderId) {
                DB::table('rescue_teams')->where('team_id', $existing->team_id)->update(['leader_responder_id' => null, 'updated_at' => $now]);
                app(\App\Services\Web\HeadquartersTeamSupport::class)->demoteLeader($existing->user_id);
                app(\App\Services\Web\HeadquartersTeamSupport::class)->synchronizeLeadership((int) $existing->team_id, null);
            }

            DB::table('users')
                ->where('user_id', $existing->user_id)
                ->update([
                    'is_active' => 0,
                    'updated_at' => $now,
                ]);

            DB::table('responders')
                ->where('responder_id', $responderId)
                ->update([
                    'is_validated' => 0,
                    'is_deployed' => 0,
                    'duty_status' => 'disabled',
                    'updated_at' => $now,
                ]);

            app(\App\Services\Web\PersonnelAccessNotifier::class)->notify([$existing->user_id]);
            $this->audit->writeAuditLog($request, 'deactivate', $responderId, $oldValues, [
                'account_status' => 'disabled',
                'duty_status' => 'disabled',
            ]);
        });

        return response()->json([
            'message' => 'Rescuer account deactivated. The roster record is retained for audit and dispatch history.',
            'data' => [
                'rescuer' => $this->presenter->formatResponder($this->query->findResponder($responderId), true),
            ],
        ]);
    }
}







