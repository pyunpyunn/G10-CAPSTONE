<?php

namespace App\Services\Mobile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RescuerAccountSupport
{
    private const ACCOUNT_PREFIX = 'BDRRM';
    private const ACCOUNT_START_SEQUENCE = 0;
    private const ACCOUNT_SEQUENCE_LENGTH = 3;
    public function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $validated = app(\App\Http\Requests\RescuerAccountPayloadValidator::class)->validate($request, $isUpdate);
        $validated['account_status'] = $validated['account_status'] ?? config('rescuers.account_defaults.account_status');

        if (! empty($validated['account_id'])) {
            $validated['account_id'] = strtoupper($validated['account_id']);
        }

        if (! empty($validated['responder_code'])) {
            $validated['responder_code'] = strtoupper($validated['responder_code']);
        }

        $teamCode = $this->teamCodeFromPayload($validated);

        if (! $isUpdate && empty($validated['account_id'])) {
            $validated['account_id'] = $this->nextAccountId($teamCode);
        }

        if (! $isUpdate && ! $this->accountIdMatchesTeam($validated['account_id'], $teamCode)) {
            throw ValidationException::withMessages([
                'account_id' => ['The generated Account ID must match the selected team code. Please reselect the team.'],
            ]);
        }

        return $validated;
    }

    public function ensureUniqueLogin(string $accountId, ?int $currentResponderId): void
    {
        $existingUser = DB::table('users')
            ->where('username', $accountId)
            ->exists();

        $existingResponder = DB::table('responders')
            ->where(function ($query) use ($accountId): void {
                $query->where('username', $accountId)
                    ->orWhere('responder_code', $accountId);
            })
            ->when($currentResponderId, fn ($query) => $query->where('responder_id', '<>', $currentResponderId))
            ->exists();

        if ($existingUser || $existingResponder) {
            throw ValidationException::withMessages([
                'account_id' => ['This Account ID is already used by another user.'],
            ]);
        }
    }

    public function ensureAccountTeamType(string $accountId, array $validated): void
    {
        $team = ! empty($validated['team_id'])
            ? DB::table('rescue_teams')->where('team_id', $validated['team_id'])->first()
            : DB::table('rescue_teams')->where('team_name', $validated['team_name'] ?? '')->first();
        if (! $team) {
            if (empty($validated['team_name'])) return;
            throw ValidationException::withMessages(['team_name' => ['Select a team that exists in team management.']]);
        }
        $hqTeam = strtoupper($team->team_code) === 'HQCC'
            || in_array(strtoupper($team->team_type), ['HQ', 'HQCC', 'HEADQUARTERS'], true);
        $hqAccount = str_starts_with(strtoupper($accountId), 'BDRRM-HQCC-');
        if ($hqAccount !== $hqTeam) {
            throw ValidationException::withMessages(['team_name' => ['HQ personnel belong to HQCC teams; rescuers belong to rescue teams.']]);
        }
    }

    public function roleIdForAccount(string $teamCode): int
    {
        if ($teamCode === 'HQCC') {
            return app(\App\Services\Web\HeadquartersTeamSupport::class)->personnelRoleId();
        }

        return $this->rescuerRoleId();
    }

    public function rescuerRoleId(): int
    {
        $roleId = DB::table('roles')
            ->where('role_key', 'rescuer')
            ->value('role_id');

        if (! $roleId) {
            throw ValidationException::withMessages([
                'role' => ['The rescuer role is missing in the roles table. Ask the DB member to check roles.'],
            ]);
        }

        return (int) $roleId;
    }

    public function adminRoleId(): int
    {
        $roleId = DB::table('roles')
            ->whereIn('role_key', ['admin', 'hq_admin'])
            ->value('role_id');

        if (! $roleId) {
            throw ValidationException::withMessages([
                'role' => ['The admin role is missing in the roles table. Ask the DB member to check roles.'],
            ]);
        }

        return (int) $roleId;
    }

    public function teamIdFromPayload(array $validated): ?int
    {
        if (! empty($validated['team_id'])) {
            return (int) $validated['team_id'];
        }

        $teamName = trim((string) ($validated['team_name'] ?? ''));

        if ($teamName === '') {
            return null;
        }

        $existingTeamId = DB::table('rescue_teams')
            ->where('team_name', $teamName)
            ->value('team_id');

        if (! $existingTeamId) {
            throw ValidationException::withMessages(['team_name' => ['Select a team that exists in team management.']]);
        }

        return (int) $existingTeamId;
    }

    public function fullNameFromPayload(array $validated): string
    {
        $middle = app(\App\Presenters\RescuerAccountPresenter::class)->formatMiddleInitial($validated['middle_initial'] ?? '');

        return trim(collect([
            trim((string) $validated['first_name']),
            $middle,
            trim((string) $validated['last_name']),
        ])->filter()->join(' '));
    }

    public function uniqueDisplayUsername(string $fullName): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $fullName));
        $base = trim((string) $base, '.');
        $base = $base !== '' ? substr($base, 0, 30) : 'rescuer';
        $username = $base;
        $counter = 2;

        while ($this->displayUsernameExists($username)) {
            $suffix = '.'.$counter;
            $username = substr($base, 0, 40 - strlen($suffix)).$suffix;
            $counter++;
        }

        return $username;
    }

    public function displayUsernameExists(string $username): bool
    {
        $userExists = DB::table('users')
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->exists();

        $responderLoginExists = DB::table('responders')
            ->where(function ($query) use ($username): void {
                $query->whereRaw('LOWER(username) = ?', [strtolower($username)])
                    ->orWhereRaw('LOWER(responder_code) = ?', [strtolower($username)]);
            })
            ->exists();

        return $userExists || $responderLoginExists;
    }

    public function nextAccountId(?string $teamCode = null): string
    {
        $teamCode = $this->normalizeTeamCode($teamCode ?: 'SAR');
        $pattern = '^'.self::ACCOUNT_PREFIX.'-'.$teamCode.'-[0-9]{'.self::ACCOUNT_SEQUENCE_LENGTH.'}$';

        $usernames = DB::table('users')
            ->where('username', 'regexp', $pattern)
            ->pluck('username');

        $responderUsernames = DB::table('responders')
            ->where('username', 'regexp', $pattern)
            ->pluck('username');

        $responderCodes = DB::table('responders')
            ->where('responder_code', 'regexp', $pattern)
            ->pluck('responder_code');

        $maxSequence = collect($usernames)
            ->merge($responderUsernames)
            ->merge($responderCodes)
            ->map(fn (?string $value): int => $this->accountSequence($value, $teamCode))
            ->max() ?: self::ACCOUNT_START_SEQUENCE;

        return self::ACCOUNT_PREFIX.'-'.$teamCode.'-'.str_pad((string) ($maxSequence + 1), self::ACCOUNT_SEQUENCE_LENGTH, '0', STR_PAD_LEFT);
    }

    public function accountSequence(?string $accountId, string $teamCode): int
    {
        if (! $accountId || ! preg_match('/^BDRRM-'.preg_quote($teamCode, '/').'-([0-9]{3})$/i', $accountId, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    public function accountIdMatchesTeam(string $accountId, string $teamCode): bool
    {
        return preg_match('/^BDRRM-'.preg_quote($teamCode, '/').'-[0-9]{3}$/i', $accountId) === 1;
    }

    public function nextResponderId(): int
    {
        return $this->nextId('responders', 'responder_id');
    }

    public function nextId(string $table, string $column): int
    {
        return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table, $column);
    }

    public function nextResponderCode(?int $teamId): string
    {
        $team = $teamId
            ? DB::table('rescue_teams')->where('team_id', $teamId)->first(['team_code'])
            : null;

        $prefix = $team?->team_code ?: 'RSC';
        $count = DB::table('responders')->count() + 1;

        return $this->nextAccountId($prefix);
    }

    public function teamCode(string $teamName): string
    {
        return $this->normalizeTeamCode(substr(preg_replace('/[^A-Za-z0-9]/', '', $teamName), 0, 5) ?: 'TEAM');
    }

    public function teamCodeFromPayload(array $validated): string
    {
        $teamName = trim((string) ($validated['team_name'] ?? ''));
        $team = DB::table('rescue_teams')
            ->when(! empty($validated['team_id']), fn ($query) => $query->where('team_id', $validated['team_id']))
            ->when(empty($validated['team_id']) && $teamName !== '', fn ($query) => $query->where('team_name', $teamName))
            ->first(['team_code']);

        if ($team) return $this->normalizeTeamCode($team->team_code);
        if ($teamName !== '') {
            throw ValidationException::withMessages(['team_name' => ['Select a team that exists in team management.']]);
        }

        return $this->normalizeTeamCode('SAR');
    }

    public function normalizeTeamCode(string $teamCode): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $teamCode));

        return substr($code ?: 'TEAM', 0, 8);
    }
}







