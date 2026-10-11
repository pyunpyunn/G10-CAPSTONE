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
    private const CAPTAIN_ACCOUNT_ID = 'BDRRM-HQCC-001';
    private const ACCOUNT_PREFIX = 'BDRRM-HQCC-';

    public function __construct(private RescuerAccountSupport $support) {}

    public function index(): JsonResponse
    {
        $accounts = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id')
            ->where('u.username', 'like', self::ACCOUNT_PREFIX.'%')
            ->orderBy('u.username')
            ->get(['u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.name', 'u.email',
                'u.contact_number', 'u.is_active', 'u.must_change_password', 'r.role_name']);

        return response()->json(['data' => [
            'accounts' => $accounts->map(fn (object $account): array => $this->present($account))->values()->all(),
            'captain_account_id' => self::CAPTAIN_ACCOUNT_ID,
            'next_account_id' => $this->nextAccountId(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:barangay_captain,command_center'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $account = DB::transaction(function () use ($validated): array {
            $isCaptain = $validated['account_type'] === 'barangay_captain';
            $accountId = $isCaptain ? self::CAPTAIN_ACCOUNT_ID : $this->nextAccountId(true);
            $userId = 'USR-HQCC-'.$accountId;
            $existing = $isCaptain
                ? DB::table('users')->where(fn ($query) => $query->where('username', $accountId)->orWhere('user_id', $userId))->lockForUpdate()->first()
                : null;

            if (! $existing && empty($validated['password'])) {
                throw ValidationException::withMessages([
                    'password' => ['Enter a temporary password for a new account.'],
                ]);
            }

            if (! empty($validated['email'])) {
                $emailExists = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
                    ->when($existing, fn ($query) => $query->where('user_id', '<>', $existing->user_id))
                    ->exists();
                if ($emailExists) {
                    throw ValidationException::withMessages(['email' => ['This email is already assigned to another account.']]);
                }
            }

            if (! $existing && DB::table('users')->where('username', $accountId)->orWhere('user_id', $userId)->exists()) {
                throw ValidationException::withMessages(['account_type' => ['This account identifier is already in use.']]);
            }

            $now = now();
            $values = [
                'user_id' => $existing->user_id ?? $userId,
                'username' => $accountId,
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'role_id' => $this->support->adminRoleId(),
                'is_active' => 1,
                'updated_at' => $now,
            ];
            if (! $existing) {
                $values['created_at'] = $now;
                $values['must_change_password'] = 1;
            }
            if (! empty($validated['password'])) {
                $values['password'] = Hash::make($validated['password']);
                $values['must_change_password'] = 1;
            }

            $columns = array_flip(Schema::getColumnListing('users'));
            $values = array_intersect_key($values, $columns);
            if ($existing) {
                DB::table('users')->where('user_id', $existing->user_id)->update($values);
            } else {
                DB::table('users')->insert($values);
            }

            return [
                'account_id' => $accountId,
                'user_id' => $existing->user_id ?? $userId,
                'account_type' => $isCaptain ? 'barangay_captain' : 'command_center',
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'is_active' => true,
            ];
        });

        return response()->json([
            'message' => $account['account_type'] === 'barangay_captain'
                ? 'Barangay Captain account saved.'
                : 'Command Center Personnel account created.',
            'data' => ['account' => $account],
        ], $account['account_type'] === 'barangay_captain' ? 200 : 201);
    }

    private function nextAccountId(bool $lock = false): string
    {
        $query = DB::table('users')->where('username', 'like', self::ACCOUNT_PREFIX.'%');
        if ($lock) $query->lockForUpdate();
        $next = 2;
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
        $captain = strtoupper($account->username) === self::CAPTAIN_ACCOUNT_ID;
        return [
            'account_id' => $account->username,
            'user_id' => $account->user_id,
            'account_type' => $captain ? 'barangay_captain' : 'command_center',
            'account_type_label' => $captain ? 'Barangay Captain' : 'Command Center Personnel (HCC)',
            'name' => trim((string) ($account->name ?: trim(($account->first_name ?? '').' '.($account->last_name ?? '')))),
            'first_name' => $account->first_name,
            'last_name' => $account->last_name,
            'email' => $account->email,
            'contact_number' => $account->contact_number,
            'is_active' => (bool) $account->is_active,
            'must_change_password' => (bool) $account->must_change_password,
            'role_name' => $account->role_name,
        ];
    }
}
