<?php

namespace App\Queries;

use App\Models\Household;
use App\Models\Responder;
use App\Models\User;
use App\Support\RequestSchema as Schema;

class AuthAccountQuery
{
    public function userFromLogin(string $login): ?User
    {
        return $this->userFromSharedUserTable(trim($login)) ?: $this->userFromResponderLogin(trim($login));
    }

    private function userFromResponderLogin(string $login): ?User
    {
        $term = trim($login);
        $search = strtolower($term);
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
        $query = User::query();
        if (Schema::hasColumn('users', 'username')) $query->where(fn ($q) => $q->whereRaw('LOWER(username) = ?', [$search]));
        if (Schema::hasColumn('users', 'login_id')) $query->orWhereRaw('LOWER(login_id) = ?', [$search]);
        if (Schema::hasColumn('users', 'email')) $query->orWhereRaw('LOWER(email) = ?', [$search]);
        if (Schema::hasColumn('users', 'user_id')) $query->orWhereRaw('LOWER(user_id) = ?', [$search]);
        if (Schema::hasColumn('users', 'deleted_at')) $query->whereNull('deleted_at');
        return $query->first() ?: $this->userFromHouseholdIdentifier(trim($login));
    }

    private function userFromHouseholdIdentifier(string $login): ?User
    {
        if (! Schema::hasTable('households') || ! Schema::hasColumn('users', 'household_id')) return null;
        $id = Household::query()->where(fn ($q) => $q->where('household_id', $login)->orWhere('household_code', $login)->orWhere('household_number', $login))
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->value('household_id');
        if (! $id) return null;
        return User::query()->where('household_id', $id)->when(Schema::hasColumn('users', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->first();
    }
}
