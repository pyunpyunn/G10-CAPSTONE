<?php

namespace App\Services\Web;

use App\Services\Mobile\RescuerAccountSupport;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class HeadquartersAccountWorkflow
{
    private const WEB_ROLE_KEYS = ['chair', 'operations_admin', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation'];
    private const ACCOUNT_PREFIXES = [
        'chair' => 'CHAIR',
        'operations_admin' => 'OPS',
        'mitigation_prevention' => 'MITPREV',
        'preparedness' => 'PREP',
        'response' => 'RESP',
        'recovery_rehabilitation' => 'RECOVERY',
    ];

    public function __construct(private RescuerAccountSupport $support) {}

    public function index(): JsonResponse
    {
        $accounts = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->leftJoin('responders as p', fn ($join) => $join->on('p.user_id', '=', 'u.user_id')->whereNull('p.deleted_at'))
            ->leftJoin('rescue_teams as t', 't.team_id', '=', 'p.team_id')
            ->whereIn('r.role_key', self::WEB_ROLE_KEYS)
            ->orderBy('u.username')
            ->get(['u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.name', 'u.email',
                'u.contact_number', 'u.is_active', 'u.must_change_password', 'r.role_name', 'r.role_key',
                'p.responder_id', 'p.team_id', 't.team_name', 't.team_code', 't.team_type', 't.leader_responder_id']);

        return response()->json(['data' => [
            'accounts' => $accounts->map(fn (object $account): array => $this->present($account))->values()->all(),
            'next_account_id' => $this->nextAccountId('operations_admin'),
            'next_account_ids' => collect(self::WEB_ROLE_KEYS)->mapWithKeys(fn (string $key): array => [$key => $this->nextAccountId($key)])->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:command_center'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'integer', 'exists:roles,role_id'],
            'title' => ['nullable', 'string', 'max:100'],
            'account_status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('rescuers.account_statuses')))],
            'duty_status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('rescuers.duty_statuses')))],
            'gender' => ['nullable', 'string', 'max:20'], 'blood_type' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:1000'], 'date_of_birth' => ['nullable', 'date', 'before:today'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'], 'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $role = app(PersonnelRoleService::class)->selected($validated, true);
        if (! $role) {
            throw ValidationException::withMessages(['role_id' => ['Choose a web role for this account.']]);
        }

        $account = DB::transaction(function () use ($validated, $role): array {
            $accountId = $this->nextAccountId(strtolower($role->role_key), true);
            $userId = 'USR-'.$accountId;

            if (empty($validated['password'])) {
                throw ValidationException::withMessages([
                    'password' => ['Enter a temporary password for a new account.'],
                ]);
            }

            if (! empty($validated['email'])) {
                $emailExists = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
                    ->exists();
                if ($emailExists) {
                    throw ValidationException::withMessages(['email' => ['This email is already assigned to another account.']]);
                }
            }

            if (DB::table('users')->where('username', $accountId)->orWhere('user_id', $userId)->exists()) {
                throw ValidationException::withMessages(['account_type' => ['This account identifier is already in use.']]);
            }

            $now = now();
            $values = [
                'user_id' => $userId,
                'username' => $accountId,
                'login_id' => $accountId,
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'role_id' => $role->role_id,
                'is_active' => ($validated['account_status'] ?? 'active') !== 'disabled' ? 1 : 0,
                'updated_at' => $now,
            ];
            $values['created_at'] = $now;
            $values['must_change_password'] = 1;
            $values['password'] = Hash::make($validated['password']);

            $columns = array_flip(Schema::getColumnListing('users'));
            $values = array_intersect_key($values, $columns);
            DB::table('users')->insert($values);
            $responderId = app(HeadquartersTeamSupport::class)->syncUser(DB::table('users')->where('user_id', $userId)->first());
            if ($responderId) {
                $profile = array_intersect_key($validated, array_flip(['gender', 'blood_type', 'address', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_number']));
                $profile['duty_status'] = ($validated['account_status'] ?? 'active') === 'disabled' ? 'disabled' : ($validated['duty_status'] ?? 'on_duty');
                $profile['is_validated'] = ($validated['account_status'] ?? 'active') === 'active' ? 1 : 0;
                $profile = array_intersect_key($profile, array_flip(Schema::getColumnListing('responders')));
                DB::table('responders')->where('responder_id', $responderId)->update($profile);
                app(PersonnelRoleService::class)->apply($responderId, $role);
            }
            app(PersonnelAccessNotifier::class)->notify([$values['user_id']]);

            return [
                'account_id' => $accountId,
                'user_id' => $userId,
                'account_type' => 'command_center',
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'is_active' => (bool) $values['is_active'],
                'responder_id' => $responderId,
            ];
        });

        return response()->json([
            'message' => 'BDRRMC committee account created.',
            'data' => ['account' => $account],
        ], 201);
    }

    private function nextAccountId(string $roleKey, bool $lock = false): string
    {
        $prefix = (self::ACCOUNT_PREFIXES[$roleKey] ?? strtoupper($roleKey)).'-';
        $query = DB::table('users')->where('username', 'like', $prefix.'%');
        if ($lock) $query->lockForUpdate();
        $next = 1;
        foreach ($query->pluck('username') as $username) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d{4,})$/i', (string) $username, $matches)) {
                $next = max($next, (int) $matches[1] + 1);
            }
        }
        if ($next > 9999) {
            throw ValidationException::withMessages(['role_id' => ['No account identifiers remain for this role.']]);
        }

        return $prefix.sprintf('%04d', $next);
    }

    private function present(object $account): array
    {
        $isTeamLeader = $account->responder_id && (int) $account->responder_id === (int) $account->leader_responder_id;
        return [
            'account_id' => $account->username,
            'user_id' => $account->user_id,
            'account_type' => 'command_center',
            'account_type_label' => $account->role_name ?: 'Web account',
            'name' => trim((string) ($account->name ?: trim(($account->first_name ?? '').' '.($account->last_name ?? '')))),
            'first_name' => $account->first_name,
            'last_name' => $account->last_name,
            'email' => $account->email,
            'contact_number' => $account->contact_number,
            'is_active' => (bool) $account->is_active,
            'must_change_password' => (bool) $account->must_change_password,
            'role_name' => $account->role_name,
            'role_key' => $account->role_key,
            'responder_id' => $account->responder_id,
            'team_id' => $account->team_id,
            'team_name' => $account->team_name,
            'team_code' => $account->team_code,
            'team_type' => $account->team_type,
            'is_team_leader' => (bool) $isTeamLeader,
        ];
    }
}
