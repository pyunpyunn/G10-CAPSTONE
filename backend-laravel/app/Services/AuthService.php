<?php

namespace App\Services;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use PDOException;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->string('login')->toString());

        if ($response = $this->databaseUnavailableResponse()) {
            return $response;
        }

        try {
            $user = $this->userFromSharedUserTable($login);

            if (! $user) {
                $user = $this->userFromResponderLogin($login);
            }
        } catch (QueryException|PDOException) {
            return response()->json([
                'message' => 'The database is not reachable right now. Make sure the shared MySQL laptop is online, or switch Laravel to a working local database.',
            ], 503);
        }

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The login details are incorrect.'],
            ]);
        }

        if ($user->is_active !== null && ! (bool) $user->is_active) {
            return response()->json([
                'message' => 'This account is inactive. Please contact HQ/Admin.',
            ], 403);
        }

        if (! $this->isHouseholdAccountReady($user)) {
            return response()->json([
                'message' => 'This household account is not linked to a registered household record. Please contact SafeTrack or HQ/Admin.',
            ], 409);
        }

        $deviceName = $request->input('device_name', 'resqperation-client');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load('role')
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    private function userFromResponderLogin(string $login): ?User
    {
        $responderTable = $this->firstExistingTable(['responders', 'responder']);

        if (! $responderTable) {
            return null;
        }

        $responder = DB::table($responderTable)
            ->where(function ($query) use ($login, $responderTable): void {
                $this->orWhereExisting($query, $responderTable, ['responder_code', 'username', 'login_id', 'user_id'], $login);
            })
            ->when(Schema::hasColumn($responderTable, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first();

        if (! $responder) {
            return null;
        }

        $query = $this->userQuery()
            ->where(function ($inner) use ($responder): void {
                $this->orWhereExisting($inner, 'users', ['user_id', 'username', 'login_id', 'id'], $responder->user_id ?? $responder->username ?? $responder->login_id ?? null);
            });

        if (Schema::hasColumn('users', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
    }

    private function userFromSharedUserTable(string $login): ?User
    {
        $query = $this->userQuery();

        if (Schema::hasColumn('users', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $query->where(function ($inner) use ($login): void {
            $this->orWhereExisting($inner, 'users', ['username', 'login_id', 'email', 'user_id', 'id'], $login);

            if (Schema::hasColumn('users', 'household_id')) {
                $inner->orWhere('household_id', $login);
            }
        });

        $user = $query->first();

        if ($user) {
            return $user;
        }

        return $this->userFromHouseholdIdentifier($login);
    }

    private function userFromHouseholdIdentifier(string $login): ?User
    {
        $householdTable = $this->firstExistingTable(['households', 'household']);

        if (! $householdTable || ! Schema::hasColumn('users', 'household_id')) {
            return null;
        }

        $householdQuery = DB::table($householdTable);

        if (Schema::hasColumn($householdTable, 'deleted_at')) {
            $householdQuery->whereNull('deleted_at');
        }

        $householdQuery->where(function ($inner) use ($login, $householdTable): void {
            $this->orWhereExisting($inner, $householdTable, ['household_id', 'household_code', 'login_id'], $login);
        });

        $householdId = $householdQuery->value('household_id');

        if (! $householdId) {
            return null;
        }

        $userQuery = $this->userQuery()
            ->where('household_id', $householdId);

        if (Schema::hasColumn('users', 'deleted_at')) {
            $userQuery->whereNull('deleted_at');
        }

        return $userQuery->first();
    }

    private function isHouseholdAccountReady(User $user): bool
    {
        if ($user->roleKey() !== 'household_resident') {
            return true;
        }

        $householdTable = $this->firstExistingTable(['households', 'household']);

        if (! $user->household_id || ! $householdTable) {
            return false;
        }

        $query = DB::table($householdTable)
            ->where('household_id', $user->household_id);

        if (Schema::hasColumn($householdTable, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private function databaseUnavailableResponse(): ?JsonResponse
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.$connectionName", []);
        $driver = $connection['driver'] ?? null;

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $host = (string) ($connection['host'] ?? '');
        $port = (int) ($connection['port'] ?? 3306);

        if ($host === '') {
            return null;
        }

        $timeout = max(1.0, (float) env('DB_CONNECT_TIMEOUT', 2));
        $target = str_contains($host, ':')
            ? "tcp://[$host]:$port"
            : "tcp://$host:$port";
        $socket = @stream_socket_client($target, $errno, $error, $timeout);

        if ($socket === false) {
            return response()->json([
                'message' => "The database is not reachable at $host:$port. Make sure the shared MySQL laptop is online, or update DB_HOST to a working database.",
            ], 503);
        }

        fclose($socket);

        return null;
    }

    private function userQuery()
    {
        $query = User::query();

        if (Schema::hasTable('roles') && Schema::hasColumn('users', 'role_id')) {
            $query->with('role');
        }

        return $query;
    }

    private function firstExistingTable(array $tables): ?string
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    private function orWhereExisting($query, string $table, array $columns, mixed $value): void
    {
        foreach ($columns as $column) {
            if ($value !== null && Schema::hasColumn($table, $column)) {
                $query->orWhere($column, $value);
            }
        }
    }
}
