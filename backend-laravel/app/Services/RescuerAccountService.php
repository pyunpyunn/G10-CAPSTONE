<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RescuerAccountService
{
    private const ACCOUNT_PREFIX = 'BDRRM';
    private const ACCOUNT_START_SEQUENCE = 0;
    private const ACCOUNT_SEQUENCE_LENGTH = 3;

    private const TEAM_CATALOG = [
        ['team_name' => 'Search & Rescue', 'team_type' => 'SAR', 'team_code' => 'SAR'],
        ['team_name' => 'Evacuation', 'team_type' => 'Evacuation', 'team_code' => 'EVC'],
        ['team_name' => 'Medical / First Aid', 'team_type' => 'Medical', 'team_code' => 'MED'],
        ['team_name' => 'Relief & Transport', 'team_type' => 'Relief / Transport', 'team_code' => 'LOG'],
        ['team_name' => 'Communication', 'team_type' => 'Communication', 'team_code' => 'COM'],
        ['team_name' => 'Fire Brigade', 'team_type' => 'Fire Brigade', 'team_code' => 'FIR'],
        ['team_name' => 'DANA', 'team_type' => 'Damage Assessment', 'team_code' => 'DANA'],
        ['team_name' => 'Security', 'team_type' => 'Security', 'team_code' => 'SEC'],
    ];

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $team = trim((string) $request->query('team', 'all'));
        $dutyStatus = trim((string) $request->query('duty_status', 'all'));
        $purok = trim((string) $request->query('purok', 'all'));
        $perPage = min(50, max(10, (int) $request->query('per_page', 25)));

        $query = $this->responderQuery();

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('r.full_name', 'like', "%{$search}%")
                    ->orWhere('r.responder_code', 'like', "%{$search}%")
                    ->orWhere('r.username', 'like', "%{$search}%")
                    ->orWhere('r.title', 'like', "%{$search}%")
                    ->orWhere('r.skills', 'like', "%{$search}%")
                    ->orWhere('rt.team_name', 'like', "%{$search}%");
            });
        }

        if ($team !== '' && $team !== 'all') {
            $query->where(function ($inner) use ($team): void {
                $inner->where('rt.team_name', $team)
                    ->orWhere('r.team_id', $team);
            });
        }

        if ($dutyStatus !== '' && $dutyStatus !== 'all') {
            if ($dutyStatus === 'training_due') {
                $query->where(function ($inner): void {
                    $inner->where('r.training_notes', 'like', '%due%')
                        ->orWhere('r.certification_reference', 'like', '%due%')
                        ->orWhere('r.certification_reference', 'like', '%expired%');
                });
            } elseif ($dutyStatus === 'active') {
                $query->where('u.is_active', 1);
            } elseif ($dutyStatus === 'disabled') {
                $query->where(function ($inner): void {
                    $inner->where('u.is_active', 0)
                        ->orWhere('r.duty_status', 'disabled');
                });
            } else {
                $query->where('r.duty_status', $dutyStatus);
            }
        }

        if ($purok !== '' && $purok !== 'all') {
            $query->where('r.address', 'like', "%{$purok}%");
        }

        $paginator = $query
            ->orderBy('r.full_name')
            ->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatResponder($row))
            ->values()
            ->all();

        $teamOptions = $this->teamOptions();

        return response()->json([
            'data' => [
                'summary' => $this->summary(),
                'rescuers' => [
                    'data' => $items,
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'teams' => $this->teamCards(),
                'team_options' => $teamOptions,
                'account_id_options' => $this->accountIdOptions($teamOptions),
                'next_account_id' => $this->nextAccountId('SAR'),
                'account_id_format' => 'BDRRM-{TEAM_CODE}-###',
                'filters' => [
                    'puroks' => $this->purokOptions(),
                    'duty_statuses' => $this->dutyStatuses(),
                    'roles' => $this->responderRoles(),
                    'blood_types' => $this->bloodTypes(),
                ],
                'note' => 'HQ/Admin creates verified rescuer accounts manually. There is no public self-registration or pending verification queue.',
            ],
        ]);
    }

    public function show(int $responderId): JsonResponse
    {
        $responder = $this->findResponder($responderId);

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer account was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'rescuer' => $this->formatResponder($responder, true),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureUniqueLogin($validated['account_id'], null);

        $rescuer = DB::transaction(function () use ($request, $validated): array {
            $now = now();
            $teamId = $this->teamIdFromPayload($validated, $now);
            $roleId = $this->rescuerRoleId();
            $responderId = $this->nextResponderId();
            $fullName = $this->fullNameFromPayload($validated);
            $firstName = trim($validated['first_name']);
            $lastName = trim($validated['last_name']);
            $password = $validated['password'] ?? 'password';
            $passwordHash = Hash::make($password);
            $userId = 'USR-RESCUER-'.$validated['account_id'];
            $displayUsername = $this->uniqueDisplayUsername($fullName);

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
                'is_active' => 1,
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
                'is_validated' => 1,
                'is_deployed' => in_array($validated['duty_status'], ['dispatched', 'on_scene'], true) ? 1 : 0,
                'duty_status' => $validated['duty_status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->writeAuditLog($request, 'create', $responderId, null, $validated);

            return $this->formatResponder($this->findResponder($responderId), true);
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
        $existing = $this->findResponder($responderId);

        if (! $existing) {
            return response()->json([
                'message' => 'Rescuer account was not found.',
            ], 404);
        }

        $validated = $this->validatePayload($request, true);

        $rescuer = DB::transaction(function () use ($request, $validated, $existing, $responderId): array {
            $now = now();
            $teamId = $this->teamIdFromPayload($validated, $now);
            $fullName = $this->fullNameFromPayload($validated);
            $firstName = trim($validated['first_name']);
            $lastName = trim($validated['last_name']);
            $oldValues = $this->formatResponder($existing, true);

            $userUpdate = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $fullName,
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'],
                'is_active' => $validated['account_status'] === 'disabled' ? 0 : 1,
                'updated_at' => $now,
            ];

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
                'is_validated' => $validated['account_status'] === 'disabled' ? 0 : 1,
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

            $updated = $this->formatResponder($this->findResponder($responderId), true);
            $this->writeAuditLog($request, 'update', $responderId, $oldValues, $updated);

            return $updated;
        });

        return response()->json([
            'message' => 'Rescuer account updated.',
            'data' => [
                'rescuer' => $rescuer,
            ],
        ]);
    }

    public function deactivate(Request $request, int $responderId): JsonResponse
    {
        $existing = $this->findResponder($responderId);

        if (! $existing) {
            return response()->json([
                'message' => 'Rescuer account was not found.',
            ], 404);
        }

        DB::transaction(function () use ($request, $existing, $responderId): void {
            $now = now();
            $oldValues = $this->formatResponder($existing, true);

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

            $this->writeAuditLog($request, 'deactivate', $responderId, $oldValues, [
                'account_status' => 'disabled',
                'duty_status' => 'disabled',
            ]);
        });

        return response()->json([
            'message' => 'Rescuer account deactivated. The roster record is retained for audit and dispatch history.',
            'data' => [
                'rescuer' => $this->formatResponder($this->findResponder($responderId), true),
            ],
        ]);
    }

    private function responderQuery()
    {
        return DB::table('responders as r')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user_id')
            ->leftJoin('roles as role', 'role.role_id', '=', 'u.role_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id')
            ->whereNull('r.deleted_at')
            ->select([
                'r.*',
                'u.email',
                'u.first_name as user_first_name',
                'u.last_name as user_last_name',
                'u.name as user_full_name',
                'u.username as user_username',
                'u.is_active',
                'u.must_change_password',
                'role.role_key',
                'rt.team_name',
                'rt.team_code',
                'rt.team_type',
            ]);
    }

    private function findResponder(int $responderId): ?object
    {
        return $this->responderQuery()
            ->where('r.responder_id', $responderId)
            ->first();
    }

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'account_id' => ['nullable', 'string', 'max:30', 'regex:/^BDRRM-[A-Z0-9]{2,8}-[0-9]{3}$/i'],
            'responder_code' => ['nullable', 'string', 'max:80', 'regex:/^BDRRM-[A-Z0-9]{2,8}-[0-9]{3}$/i'],
            'account_status' => ['nullable', Rule::in(['active', 'reserve', 'disabled'])],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:100'],
            'full_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => [$isUpdate ? 'nullable' : 'nullable', 'string', 'min:6', 'max:100'],
            'contact_number' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:20'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:1000'],
            'team_id' => ['nullable', 'integer'],
            'team_name' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:100'],
            'team_code' => ['nullable', 'string', 'max:20'],
            'team_type' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:100'],
            'duty_status' => ['required', Rule::in(['on_duty', 'standby', 'reserve', 'off_duty', 'unavailable', 'dispatched', 'on_scene', 'disabled'])],
            'skills' => ['nullable', 'string', 'max:1000'],
            'training_notes' => ['nullable', 'string', 'max:1500'],
            'certification_reference' => ['nullable', 'string', 'max:150'],
            'equipment_notes' => ['nullable', 'string', 'max:1500'],
        ];

        $messages = [
            'account_id.regex' => 'Rescuer Account ID must follow the DB format BDRRM-SAR-001.',
            'responder_code.regex' => 'Responder code must follow the DB format BDRRM-SAR-001.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'team_name.required' => 'Team is required before creating a rescuer account.',
            'contact_number.required' => 'Mobile number is required.',
            'title.required' => 'Responder role is required.',
            'duty_status.required' => 'Duty status is required.',
            'password.min' => 'Temporary password must have at least 6 characters.',
        ];

        $validated = $request->validate($rules, $messages);
        $validated['account_status'] = $validated['account_status'] ?? 'active';

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

    private function ensureUniqueLogin(string $accountId, ?int $currentResponderId): void
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

    private function rescuerRoleId(): int
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

    private function teamIdFromPayload(array $validated, Carbon $now): ?int
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

        if ($existingTeamId) {
            return (int) $existingTeamId;
        }

        $teamId = $this->nextId('rescue_teams', 'team_id');
        $catalog = collect(self::TEAM_CATALOG)->firstWhere('team_name', $teamName);
        $teamCode = $this->teamCodeFromPayload($validated);

        DB::table('rescue_teams')->insert([
            'team_id' => $teamId,
            'team_code' => $teamCode,
            'team_name' => $teamName,
            'team_type' => $validated['team_type'] ?? $catalog['team_type'] ?? $teamName,
            'duty_status' => 'standby',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $teamId;
    }

    private function summary(): array
    {
        $rows = $this->responderQuery()->get();

        return [
            'registered' => $rows->count(),
            'on_duty' => $rows->filter(fn (object $row): bool => in_array($row->duty_status, ['on_duty', 'standby', 'dispatched', 'on_scene'], true) && (int) $row->is_active === 1)->count(),
            'deployed' => $rows->filter(fn (object $row): bool => (int) $row->is_deployed === 1 || in_array($row->duty_status, ['dispatched', 'on_scene'], true))->count(),
            'training_due' => $rows->filter(fn (object $row): bool => $this->hasTrainingDue($row))->count(),
        ];
    }

    private function teamCards(): array
    {
        $teams = DB::table('rescue_teams as rt')
            ->orderBy('rt.team_name')
            ->get([
                'rt.team_id',
                'rt.team_code',
                'rt.team_name',
                'rt.team_type',
                'rt.duty_status',
            ]);

        $cards = $teams->map(function (object $team): array {
            $members = DB::table('responders')
                ->where('team_id', $team->team_id)
                ->whereNull('deleted_at')
                ->get(['is_deployed']);

            return [
                'team_id' => $team->team_id,
                'team_code' => $team->team_code,
                'team_name' => $team->team_name,
                'team_type' => $team->team_type,
                'duty_status' => $team->duty_status,
                'member_count' => $members->count(),
                'deployed_count' => $members->where('is_deployed', 1)->count(),
            ];
        });

        foreach (self::TEAM_CATALOG as $item) {
            if (! $cards->contains('team_name', $item['team_name'])) {
                $cards->push([
                    'team_id' => null,
                    'team_code' => $item['team_code'],
                    'team_name' => $item['team_name'],
                    'team_type' => $item['team_type'],
                    'duty_status' => 'not_created',
                    'member_count' => 0,
                    'deployed_count' => 0,
                ]);
            }
        }

        return $cards->values()->all();
    }

    private function teamOptions(): array
    {
        $databaseTeams = DB::table('rescue_teams')
            ->orderBy('team_name')
            ->get(['team_id', 'team_code', 'team_name', 'team_type'])
            ->map(fn (object $team): array => [
                'team_id' => $team->team_id,
                'team_code' => $team->team_code,
                'team_name' => $team->team_name,
                'team_type' => $team->team_type,
                'source' => 'database',
            ]);

        $catalogTeams = collect(self::TEAM_CATALOG)
            ->reject(fn (array $item): bool => $databaseTeams->contains('team_name', $item['team_name']))
            ->map(fn (array $item): array => [
                'team_id' => null,
                'team_code' => $item['team_code'],
                'team_name' => $item['team_name'],
                'team_type' => $item['team_type'],
                'source' => 'catalog',
            ]);

        return $databaseTeams->merge($catalogTeams)->values()->all();
    }

    private function accountIdOptions(array $teamOptions): array
    {
        return collect($teamOptions)
            ->map(function (array $team): array {
                $teamCode = $this->normalizeTeamCode($team['team_code'] ?? $this->teamCode($team['team_name']));

                return [
                    'team_name' => $team['team_name'],
                    'team_code' => $teamCode,
                    'account_id' => $this->nextAccountId($teamCode),
                ];
            })
            ->values()
            ->all();
    }

    private function formatResponder(object $row, bool $includeDetails = false): array
    {
        $status = $this->formatStatus($row->duty_status, (int) $row->is_active === 1);
        $nameParts = $this->namePartsFromRow($row);
        $accountStatus = (int) $row->is_active === 1
            ? ((int) $row->is_validated === 1 ? 'active' : 'reserve')
            : 'disabled';

        $data = [
            'responder_id' => $row->responder_id,
            'user_id' => $row->user_id,
            'account_id' => $row->username,
            'username' => $row->user_username,
            'display_username' => $row->user_username,
            'responder_code' => $row->responder_code,
            'account_status' => $accountStatus,
            'full_name' => $row->full_name ?: 'Unnamed rescuer',
            'first_name' => $nameParts['first_name'],
            'middle_initial' => $nameParts['middle_initial'],
            'last_name' => $nameParts['last_name'],
            'title' => $row->title ?: 'Responder',
            'team_id' => $row->team_id,
            'team_name' => $row->team_name ?: 'Unassigned',
            'team_code' => $row->team_code ?: null,
            'team_type' => $row->team_type ?: null,
            'contact_number' => $row->contact_number,
            'emergency_contact_name' => $row->emergency_contact_name,
            'emergency_contact_number' => $row->emergency_contact_number,
            'address' => $row->address,
            'blood_type' => $row->blood_type ?: 'Unknown',
            'skills' => $row->skills,
            'training_notes' => $row->training_notes,
            'certification_reference' => $row->certification_reference,
            'equipment_notes' => $row->equipment_notes,
            'duty_status' => $status,
            'is_deployed' => (bool) $row->is_deployed,
            'is_validated' => (bool) $row->is_validated,
            'is_active' => (bool) $row->is_active,
            'training_due' => $this->hasTrainingDue($row),
            'last_active_at' => $this->formatDateTime($row->last_active_at),
            'created_at' => $this->formatDateTime($row->created_at),
        ];

        if ($includeDetails) {
            $data['email'] = $row->email;
            $data['date_of_birth'] = $row->date_of_birth;
            $data['gender'] = $row->gender;
            $data['must_change_password'] = (bool) $row->must_change_password;
            $data['audit_note'] = 'Create, update, and deactivate actions are retained in audit logs when available.';
        }

        return $data;
    }

    private function formatStatus(?string $status, bool $isActive = true): array
    {
        if (! $isActive || $status === 'disabled') {
            return ['key' => 'disabled', 'label' => 'Disabled', 'tone' => 'gray'];
        }

        return match ($status) {
            'on_duty' => ['key' => 'on_duty', 'label' => 'On duty', 'tone' => 'green'],
            'standby' => ['key' => 'standby', 'label' => 'Stand-by', 'tone' => 'green'],
            'reserve' => ['key' => 'reserve', 'label' => 'Reserve', 'tone' => 'blue'],
            'dispatched' => ['key' => 'dispatched', 'label' => 'Dispatched', 'tone' => 'purple'],
            'on_scene' => ['key' => 'on_scene', 'label' => 'On-scene', 'tone' => 'green'],
            'unavailable' => ['key' => 'unavailable', 'label' => 'Unavailable', 'tone' => 'amber'],
            default => ['key' => 'off_duty', 'label' => 'Off duty', 'tone' => 'gray'],
        };
    }

    private function dutyStatuses(): array
    {
        return [
            ['key' => 'all', 'label' => 'All'],
            ['key' => 'active', 'label' => 'Active accounts'],
            ['key' => 'on_duty', 'label' => 'On duty'],
            ['key' => 'standby', 'label' => 'Stand-by'],
            ['key' => 'reserve', 'label' => 'Reserve'],
            ['key' => 'dispatched', 'label' => 'Dispatched'],
            ['key' => 'on_scene', 'label' => 'On-scene'],
            ['key' => 'training_due', 'label' => 'Training due'],
            ['key' => 'disabled', 'label' => 'Disabled'],
        ];
    }

    private function responderRoles(): array
    {
        return [
            'Responder',
            'Team leader',
            'Driver',
            'Medic / first aider',
            'Radio operator',
            'Logistics officer',
            'Site coordinator',
        ];
    }

    private function bloodTypes(): array
    {
        return ['Unknown', 'O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-', 'AB-'];
    }

    private function purokOptions(): array
    {
        $puroks = DB::table('addresses')
            ->whereNotNull('purok_sitio')
            ->where('purok_sitio', '<>', '')
            ->select('purok_sitio')
            ->distinct()
            ->orderBy('purok_sitio')
            ->pluck('purok_sitio')
            ->values()
            ->all();

        return empty($puroks) ? ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5'] : $puroks;
    }

    private function hasTrainingDue(object $row): bool
    {
        $text = strtolower(trim(($row->training_notes ?? '').' '.($row->certification_reference ?? '')));

        return str_contains($text, 'due')
            || str_contains($text, 'expired')
            || str_contains($text, 'refresh');
    }

    private function namePartsFromRow(object $row): array
    {
        $firstName = trim((string) ($row->user_first_name ?? ''));
        $lastName = trim((string) ($row->user_last_name ?? ''));
        $fullName = trim((string) ($row->full_name ?? $row->user_full_name ?? ''));

        if ($firstName !== '' && $lastName !== '') {
            $middle = trim($fullName);
            $middle = preg_replace('/^'.preg_quote($firstName, '/').'\s+/i', '', $middle);
            $middle = preg_replace('/(^|\s+)'.preg_quote($lastName, '/').'$/i', '', $middle);
            $middle = trim((string) $middle);

            return [
                'first_name' => $firstName,
                'middle_initial' => $this->formatMiddleInitial($middle),
                'last_name' => $lastName,
            ];
        }

        $parts = collect(explode(' ', $fullName))->filter()->values();

        if ($parts->count() === 1) {
            return ['first_name' => $parts[0], 'middle_initial' => '', 'last_name' => ''];
        }

        $firstName = $parts->slice(0, -1)->join(' ');
        $lastName = $parts->last();

        return ['first_name' => $firstName, 'middle_initial' => '', 'last_name' => $lastName];
    }

    private function fullNameFromPayload(array $validated): string
    {
        $middle = $this->formatMiddleInitial($validated['middle_initial'] ?? '');

        return trim(collect([
            trim((string) $validated['first_name']),
            $middle,
            trim((string) $validated['last_name']),
        ])->filter()->join(' '));
    }

    private function uniqueDisplayUsername(string $fullName): string
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

    private function displayUsernameExists(string $username): bool
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

    private function formatMiddleInitial(?string $value): string
    {
        $middle = strtoupper(trim((string) $value));
        $middle = str_replace('.', '', $middle);

        if ($middle === '') {
            return '';
        }

        return substr($middle, 0, 1).'.';
    }

    private function nextAccountId(?string $teamCode = null): string
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

    private function accountSequence(?string $accountId, string $teamCode): int
    {
        if (! $accountId || ! preg_match('/^BDRRM-'.preg_quote($teamCode, '/').'-([0-9]{3})$/i', $accountId, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function accountIdMatchesTeam(string $accountId, string $teamCode): bool
    {
        return preg_match('/^BDRRM-'.preg_quote($teamCode, '/').'-[0-9]{3}$/i', $accountId) === 1;
    }

    private function nextResponderId(): int
    {
        return $this->nextId('responders', 'responder_id');
    }

    private function nextId(string $table, string $column): int
    {
        return ((int) DB::table($table)->max($column)) + 1;
    }

    private function nextResponderCode(?int $teamId): string
    {
        $team = $teamId
            ? DB::table('rescue_teams')->where('team_id', $teamId)->first(['team_code'])
            : null;

        $prefix = $team?->team_code ?: 'RSC';
        $count = DB::table('responders')->count() + 1;

        return $this->nextAccountId($prefix);
    }

    private function teamCode(string $teamName): string
    {
        return $this->normalizeTeamCode(substr(preg_replace('/[^A-Za-z0-9]/', '', $teamName), 0, 5) ?: 'TEAM');
    }

    private function teamCodeFromPayload(array $validated): string
    {
        if (! empty($validated['team_code'])) {
            return $this->normalizeTeamCode($validated['team_code']);
        }

        if (! empty($validated['team_id'])) {
            $teamCode = DB::table('rescue_teams')
                ->where('team_id', $validated['team_id'])
                ->value('team_code');

            if ($teamCode) {
                return $this->normalizeTeamCode($teamCode);
            }
        }

        $teamName = trim((string) ($validated['team_name'] ?? ''));
        $catalog = collect(self::TEAM_CATALOG)->firstWhere('team_name', $teamName);

        return $this->normalizeTeamCode($catalog['team_code'] ?? $this->teamCode($teamName));
    }

    private function normalizeTeamCode(string $teamCode): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $teamCode));

        return substr($code ?: 'TEAM', 0, 8);
    }

    private function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    private function writeAuditLog(Request $request, string $action, int $responderId, mixed $oldValues, mixed $newValues): void
    {
        if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'rescuer_accounts',
            'action' => $action,
            'reference_table' => 'responders',
            'reference_id' => (string) $responderId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
