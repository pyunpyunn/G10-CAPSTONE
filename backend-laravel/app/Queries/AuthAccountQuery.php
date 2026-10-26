<?php

namespace App\Queries;

use App\Models\Responder;
use App\Models\User;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class AuthAccountQuery
{
    public function userFromLogin(string $login): ?User
    {
        $login = trim($login);
        $household = $this->householdAccount($login);
        if ($household) return $household;
        if (Schema::hasColumn('users', 'member_id') && Schema::hasTable('household_members')) {
            $member = User::query()->whereRaw('LOWER(member_id) = ?', [strtolower($login)])
                ->whereHas('role', fn ($q) => $q->where('role_key', 'household_resident'))
                ->whereExists(function ($q): void {
                    $q->selectRaw('1')->from('household_members as hm')
                        ->whereColumn('hm.member_id', 'users.member_id')
                        ->whereColumn('hm.household_id', 'users.household_id');
                    if (Schema::hasColumn('household_members', 'deleted_at')) $q->whereNull('hm.deleted_at');
                })
                ->when(Schema::hasColumn('users', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->first();
            if ($member) return $member;
        }
        return $this->userFromSharedUserTable($login) ?: $this->userFromResponderLogin($login);
    }

    public function householdAccount(string $householdId): ?User
    {
        if (! Schema::hasTable('households') || ! Schema::hasColumn('users', 'household_id')) return null;
        $id = DB::table('households')->where('household_id', $householdId)
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->value('household_id');
        if (! $id) return null;
        $query = User::query()->where('household_id', $id)
            ->whereHas('role', fn ($q) => $q->where('role_key', 'household_resident'))
            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));
        if (Schema::hasTable('household_login_accounts')) {
            $accountId = DB::table('household_login_accounts')->where('household_id', $id)->value('user_id');
            if ($accountId) return $query->whereKey($accountId)->first();
        }
        if (Schema::hasTable('household_members') && Schema::hasColumn('users', 'member_id') && Schema::hasColumn('household_members', 'is_household_head')) {
            $query->orderByDesc(DB::table('household_members')->select('is_household_head')->whereColumn('member_id', 'users.member_id')->limit(1));
        }
        return $query->orderBy('user_id')->first();
    }

    private function userFromResponderLogin(string $login): ?User
    {
        $term = trim($login);
        $search = strtolower($term);
        if (! Schema::hasTable('responders')) return null;
        $query = Responder::query();
        if (Schema::hasColumn('responders', 'responder_code')) $query->whereRaw('LOWER(responder_code) = ?', [$search]);
        if (Schema::hasColumn('responders', 'username')) $query->orWhereRaw('LOWER(username) = ?', [$search]);
        if (Schema::hasColumn('responders', 'user_id')) $query->orWhereRaw('LOWER(user_id) = ?', [$search]);
        $responder = $query->when(Schema::hasColumn('responders', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->first();
        if (! $responder) return null;

        $userQuery = User::query();
        if (Schema::hasColumn('users', 'user_id')) $userQuery->where('user_id', $responder->user_id);
        else $userQuery->whereRaw('1 = 0');
        if ($term !== '') {
            if (Schema::hasColumn('users', 'username')) $userQuery->orWhere('username', $responder->username ?? $term);
            if (Schema::hasColumn('users', 'login_id')) $userQuery->orWhere('login_id', $responder->username ?? $term);
        }
        $user = $userQuery->when(Schema::hasColumn('users', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->first();
        return $user ?: $this->userFromSharedUserTable($responder->username ?? $responder->user_id ?? $term);
    }

    private function userFromSharedUserTable(string $login): ?User
    {
        $search = strtolower(trim($login));
        $query = User::query()->where(function ($q) use ($search): void {
            $q->whereRaw('1 = 0');
            foreach (['username', 'login_id', 'email', 'user_id'] as $column) {
                if (Schema::hasColumn('users', $column)) $q->orWhereRaw('LOWER('.$column.') = ?', [$search]);
            }
        });
        if (Schema::hasColumn('users', 'deleted_at')) $query->whereNull('deleted_at');
        return $query->first();
    }
}
