<?php

namespace App\Services\Mobile;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RescuerTeamWorkflow
{
    public function validateTeamPayload(Request $request, ?int $teamId = null): array
    {
        if (in_array($request->input('duty_status'), ['standby', 'stand-by'], true)) {
            $request->merge(['duty_status' => 'available']);
        }
        $validated = $request->validate([
            'team_code' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9]+$/i'],
            'team_name' => ['required', 'string', 'max:100'],
            'team_type' => ['required', 'string', 'max:80'],
            'duty_status' => ['required', Rule::in(['available', 'on_duty', 'off_duty', 'unavailable'])],
            'assigned_purok_id' => ['nullable', 'integer', 'exists:addresses,address_id'],
            'leader_responder_id' => ['nullable', 'integer', 'exists:responders,responder_id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:responders,responder_id'],
        ], [
            'team_code.required' => 'Team code is required.',
            'team_code.regex' => 'Team code must use letters and numbers only.',
            'team_name.required' => 'Team name is required.',
            'team_type.required' => 'Team type is required.',
            'duty_status.required' => 'Team duty status is required.',
            'duty_status.in' => 'Select a valid team duty status.',
            'leader_responder_id.exists' => 'Selected team leader does not exist.',
            'member_ids.*.exists' => 'One selected member does not exist.',
        ]);

        $validated['team_code'] = $this->normalizeTeamCode($validated['team_code']);
        $validated['team_name'] = trim($validated['team_name']);
        $validated['team_type'] = trim($validated['team_type']);

        if ($validated['team_name'] === '') {
            throw ValidationException::withMessages([
                'team_name' => ['Team name is required.'],
            ]);
        }

        $this->ensureUniqueTeam($validated['team_name'], $validated['team_code'], $teamId);

        return $validated;
    }

    public function ensureUniqueTeam(string $teamName, string $teamCode, ?int $teamId): void
    {
        $query = DB::table('rescue_teams')
            ->where(function ($inner) use ($teamName, $teamCode): void {
                $inner->whereRaw('LOWER(team_name) = ?', [strtolower(trim($teamName))])
                    ->orWhereRaw('LOWER(team_code) = ?', [strtolower($this->normalizeTeamCode($teamCode))]);
            });

        if ($teamId) {
            $query->where('team_id', '<>', $teamId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'team_name' => ['Team name or team code is already used.'],
            ]);
        }
    }

    public function normalizedResponderIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function ensureRespondersCanMove(array $memberIds, int $targetTeamId): void
    {
        if (empty($memberIds)) {
            return;
        }

        $busy = DB::table('responders')
            ->whereIn('responder_id', $memberIds)
            ->where(function ($query) use ($targetTeamId): void {
                $query->whereNull('team_id')
                    ->orWhere('team_id', '<>', $targetTeamId);
            })
            ->where(function ($query): void {
                $query->where('is_deployed', 1)
                    ->orWhereIn('duty_status', ['dispatched', 'on_scene']);
            })
            ->pluck('full_name')
            ->values()
            ->all();

        if (! empty($busy)) {
            throw ValidationException::withMessages([
                'member_ids' => ['Busy responders cannot be moved to another team: '.implode(', ', $busy).'.'],
            ]);
        }
    }

    public function ensureCurrentTeamMembersCanBeRemoved(int $teamId, array $remainingMemberIds): void
    {
        $busyRemoved = DB::table('responders')
            ->where('team_id', $teamId)
            ->when(! empty($remainingMemberIds), fn ($query) => $query->whereNotIn('responder_id', $remainingMemberIds))
            ->where(function ($query): void {
                $query->where('is_deployed', 1)
                    ->orWhereIn('duty_status', ['dispatched', 'on_scene']);
            })
            ->pluck('full_name')
            ->values()
            ->all();

        if (! empty($busyRemoved)) {
            throw ValidationException::withMessages([
                'member_ids' => ['Busy responders cannot be removed from this team: '.implode(', ', $busyRemoved).'.'],
            ]);
        }
    }

    public function syncTeamMembers(int $teamId, array $memberIds, ?int $leaderId, Carbon $now): void
    {
        DB::table('responders')
            ->where('team_id', $teamId)
            ->when(! empty($memberIds), fn ($query) => $query->whereNotIn('responder_id', $memberIds))
            ->update([
                'team_id' => null,
                'updated_at' => $now,
            ]);

        if (! empty($memberIds)) {
            DB::table('responders')
                ->whereIn('responder_id', $memberIds)
                ->update([
                    'team_id' => $teamId,
                    'updated_at' => $now,
                ]);
        }

        if ($leaderId) {
            DB::table('rescue_teams')
                ->where('team_id', $teamId)
                ->update([
                    'leader_responder_id' => $leaderId,
                    'updated_at' => $now,
                ]);
        }
    }
}







