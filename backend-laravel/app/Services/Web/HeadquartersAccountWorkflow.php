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
    private const ACCOUNT_PREFIX = 'BDRRM-HQCC-';

    public function __construct(private RescuerAccountSupport $support) {}

    public function index(): JsonResponse
    {
        $accounts = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->leftJoin('responders as p', fn ($join) => $join->on('p.user_id', '=', 'u.user_id')->whereNull('p.deleted_at'))
            ->leftJoin('rescue_teams as t', 't.team_id', '=', 'p.team_id')
            ->where('u.username', 'like', self::ACCOUNT_PREFIX.'%')
            ->orderBy('u.username')
            ->get(['u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.name', 'u.email',
                'u.contact_number', 'u.is_active', 'u.must_change_password', 'r.role_name', 'r.role_key',
                'p.responder_id', 'p.team_id', 't.team_name', 't.team_code', 't.team_type', 't.leader_responder_id']);

        return response()->json(['data' => [
            'accounts' => $accounts->map(fn (object $account): array => $this->present($account))->values()->all(),
            'next_account_id' => $this->nextAccountId(),
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
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $account = DB::transaction(function () use ($validated): array {
            $accountId = $this->nextAccountId(true);
            $userId = 'USR-HQCC-'.$accountId;

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
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'role_id' => app(HeadquartersTeamSupport::class)->personnelRoleId(),
                'is_active' => 1,
                'updated_at' => $now,
            ];
            $values['created_at'] = $now;
            $values['must_change_password'] = 1;
            $values['password'] = Hash::make($validated['password']);

            $columns = array_flip(Schema::getColumnListing('users'));
            $values = array_intersect_key($values, $columns);
            DB::table('users')->insert($values);
            app(HeadquartersTeamSupport::class)->syncUser(DB::table('users')->where('user_id', $userId)->first());
            app(PersonnelAccessNotifier::class)->notify([$values['user_id']]);

            return [
                'account_id' => $accountId,
                'user_id' => $userId,
                'account_type' => 'command_center',
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'is_active' => true,
            ];
        });

        return response()->json([
            'message' => 'HQ Personnel account created.',
            'data' => ['account' => $account],
        ], 201);
    }

    private function nextAccountId(bool $lock = false): string
    {
        $query = DB::table('users')->where('username', 'like', self::ACCOUNT_PREFIX.'%');
        if ($lock) $query->lockForUpdate();
        $next = 1;
        foreach ($query->pluck('username') as $username) {
            if (preg_match('/^BDRRM-HQCC-(\d{3})$/i', (string) $username, $matches)) {
                $next = max($next, (int) $matches[1] + 1);
            }
        }
        if ($next > 999) {
            throw ValidationException::withMessages(['account_type' => ['No HQ account identifiers remain.']]);
        }

        return self::ACCOUNT_PREFIX.sprintf('%03d', $next);
    }

    private function present(object $account): array
    {
        $isTeamLeader = $account->responder_id && (int) $account->responder_id === (int) $account->leader_responder_id;
        return [
            'account_id' => $account->username,
            'user_id' => $account->user_id,
            'account_type' => 'command_center',
            'account_type_label' => $account->role_key === 'hq_leader' || $isTeamLeader ? 'HQ Leader' : 'HQ Personnel',
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
