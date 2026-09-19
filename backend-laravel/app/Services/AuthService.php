<?php

namespace App\Services;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const RECOVERY_QUESTIONS = [
        'first_pet' => "What was your first pet's name?",
        'birth_city' => "What's the name of the city where you were born?",
        'childhood_nickname' => 'What was your childhood nickname?',
        'parents_met_city' => "What's the name of the city where your parents met?",
        'eldest_cousin_first_name' => "What's the first name of your eldest cousin?",
        'first_school' => "What's the name of the first school you attended?",
    ];

    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->string('login')->toString());

        if ($response = $this->databaseUnavailableResponse()) {
            return $response;
        }

        try {
            $user = $this->userFromSharedUserTable($login) ?: $this->userFromResponderLogin($login);
        } catch (QueryException|PDOException) {
            return response()->json(['message' => 'The database is not reachable right now. Make sure the shared MySQL laptop is online, or switch Laravel to a working local database.'], 503);
        }

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['login' => ['The login details are incorrect.']]);
        }

        if ($user->is_active !== null && ! (bool) $user->is_active) {
            return response()->json(['message' => 'This account is inactive. Please contact HQ/Admin.'], 403);
        }

        if ($this->isUnvalidatedResponder($user)) {
            return response()->json(['message' => 'This rescuer account is pending HQ validation. Please wait for approval before logging in.'], 403);
        }

        if (! $this->isHouseholdAccountReady($user)) {
            return response()->json(['message' => 'This household account is not linked to a registered household record. Please contact SafeTrack or HQ/Admin.'], 409);
        }

        $token = $user->createToken($request->input('device_name', 'resqperation-client'))->plainTextToken;

        return response()->json(['message' => 'Login successful.', 'token' => $token, 'user' => new UserResource($user)]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logout successful.']);
    }

    public function recoveryQuestions(Request $request): JsonResponse
    {
        if (! $this->hasRecoveryColumns()) {
            return response()->json(['message' => 'Account recovery is not configured yet. Run the latest database migration.'], 503);
        }

        if ($request->user()) {
            $user = $request->user();
            return response()->json(['data' => ['questions' => self::RECOVERY_QUESTIONS, 'configured' => filled($user->security_question_1) && filled($user->security_question_2)]]);
        }

        $validated = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $user = $this->userFromLogin($validated['login']);

        if (! $user) {
            return response()->json(['message' => 'The account ID could not be found.'], 404);
        }

        $configured = filled($user->security_question_1) && filled($user->security_question_2);
        return response()->json(['data' => [
            'configured' => $configured,
            'questions' => $configured ? [
                'first' => self::RECOVERY_QUESTIONS[$user->security_question_1] ?? null,
                'second' => self::RECOVERY_QUESTIONS[$user->security_question_2] ?? null,
            ] : self::RECOVERY_QUESTIONS,
        ]]);
    }

    public function saveRecoveryQuestions(Request $request): JsonResponse
    {
        if (! $this->hasRecoveryColumns()) {
            return response()->json(['message' => 'Account recovery is not configured yet. Run the latest database migration.'], 503);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'question_1' => ['required', 'string', 'in:'.implode(',', array_keys(self::RECOVERY_QUESTIONS))],
            'answer_1' => ['required', 'string', 'min:2', 'max:255'],
            'question_2' => ['required', 'string', 'different:question_1', 'in:'.implode(',', array_keys(self::RECOVERY_QUESTIONS))],
            'answer_2' => ['required', 'string', 'min:2', 'max:255'],
        ]);
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        if (filled($user->security_question_1) || filled($user->security_question_2)) {
            return response()->json(['message' => 'Your recovery questions are already configured and cannot be changed here.'], 409);
        }

        $this->storeRecoveryQuestions($user, $validated);
        return response()->json(['message' => 'Recovery questions saved successfully.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        if (! $this->hasRecoveryColumns()) {
            return response()->json(['message' => 'Account recovery is not configured yet. Run the latest database migration.'], 503);
        }

        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'method' => ['required', 'in:previous_password,security_questions'],
            'previous_password' => ['nullable', 'string'],
            'question_1' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::RECOVERY_QUESTIONS))],
            'answer_1' => ['nullable', 'string'],
            'question_2' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::RECOVERY_QUESTIONS))],
            'answer_2' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = $this->userFromLogin($validated['login']);

        if (! $user) {
            return response()->json(['message' => 'The account ID could not be found.'], 422);
        }

        $configured = filled($user->security_question_1) && filled($user->security_question_2);
        $previousPasswordValid = filled($validated['previous_password'] ?? null) && Hash::check($validated['previous_password'], $user->password);

        if ($validated['method'] === 'previous_password') {
            if (! $previousPasswordValid) {
                return response()->json(['message' => 'The previous password is incorrect.'], 422);
            }

            if (! $configured) {
                if (($validated['question_1'] ?? null) === ($validated['question_2'] ?? null) || ! filled($validated['question_1'] ?? null) || ! filled($validated['question_2'] ?? null) || ! filled($validated['answer_1'] ?? null) || ! filled($validated['answer_2'] ?? null)) {
                    return response()->json(['message' => 'Choose two different recovery questions and answer both before continuing.'], 422);
                }

                $this->storeRecoveryQuestions($user, $validated);
            }
        } elseif (! $configured) {
            return response()->json(['message' => 'Set up your two recovery questions by verifying your previous password first.'], 422);
        } elseif (! Hash::check($this->normalizeAnswer($validated['answer_1'] ?? ''), $user->security_answer_1) || ! Hash::check($this->normalizeAnswer($validated['answer_2'] ?? ''), $user->security_answer_2)) {
            return response()->json(['message' => 'The recovery answers are incorrect.'], 422);
        }

        DB::table('users')->where('user_id', $user->user_id)->update(['password' => Hash::make($validated['password']), 'password_changed_at' => now(), 'must_change_password' => 0, 'updated_at' => now()]);
        return response()->json(['message' => 'Password reset successfully. You can now sign in.']);
    }

    private function storeRecoveryQuestions(User $user, array $values): void
    {
        DB::table('users')->where('user_id', $user->user_id)->update([
            'security_question_1' => $values['question_1'],
            'security_answer_1' => Hash::make($this->normalizeAnswer($values['answer_1'])),
            'security_question_2' => $values['question_2'],
            'security_answer_2' => Hash::make($this->normalizeAnswer($values['answer_2'])),
            'updated_at' => now(),
        ]);
    }

    private function userFromLogin(string $login): ?User
    {
        return $this->userFromSharedUserTable(trim($login)) ?: $this->userFromResponderLogin(trim($login));
    }

    private function hasRecoveryColumns(): bool
    {
        return Schema::hasColumn('users', 'security_question_1') && Schema::hasColumn('users', 'security_answer_1') && Schema::hasColumn('users', 'security_question_2') && Schema::hasColumn('users', 'security_answer_2') && Schema::hasColumn('users', 'password_changed_at');
    }

    private function normalizeAnswer(string $answer): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $answer)));
    }

    private function userFromResponderLogin(string $login): ?User
    {
        $responderTable = $this->firstExistingTable(['responders', 'responder']);
        if (! $responderTable) return null;

        $responder = DB::table($responderTable)->where(function ($query) use ($login, $responderTable): void {
            $this->orWhereExisting($query, $responderTable, ['responder_code', 'username', 'login_id', 'user_id'], $login);
        })->when(Schema::hasColumn($responderTable, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))->first();

        if (! $responder) return null;

        $query = $this->userQuery()->where(function ($inner) use ($responder): void {
            $this->orWhereExisting($inner, 'users', ['user_id', 'username', 'login_id', 'id'], $responder->user_id ?? $responder->username ?? $responder->login_id ?? null);
        });
        if (Schema::hasColumn('users', 'deleted_at')) $query->whereNull('deleted_at');
        return $query->first();
    }

    private function userFromSharedUserTable(string $login): ?User
    {
        $query = $this->userQuery();
        if (Schema::hasColumn('users', 'deleted_at')) $query->whereNull('deleted_at');
        $query->where(function ($inner) use ($login): void {
            $this->orWhereExisting($inner, 'users', ['username', 'login_id', 'email', 'user_id', 'id'], $login);
            if (Schema::hasColumn('users', 'household_id')) $inner->orWhere('household_id', $login);
        });
        $user = $query->first();
        return $user ?: $this->userFromHouseholdIdentifier($login);
    }

    private function userFromHouseholdIdentifier(string $login): ?User
    {
        $householdTable = $this->firstExistingTable(['households', 'household']);
        if (! $householdTable || ! Schema::hasColumn('users', 'household_id')) return null;

        $householdQuery = DB::table($householdTable);
        if (Schema::hasColumn($householdTable, 'deleted_at')) $householdQuery->whereNull('deleted_at');
        $householdQuery->where(function ($inner) use ($login, $householdTable): void {
            $this->orWhereExisting($inner, $householdTable, ['household_id', 'household_code', 'household_number'], $login);
        });
        $householdId = $householdQuery->value('household_id');
        if (! $householdId) return null;

        return $this->userQuery()->where('household_id', $householdId)->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))->first();
    }

    private function isHouseholdAccountReady(User $user): bool
    {
        if ($user->roleKey() !== 'household_resident') return true;
        $householdTable = $this->firstExistingTable(['households', 'household']);
        if (! $user->household_id || ! $householdTable) return false;
        $query = DB::table($householdTable)->where('household_id', $user->household_id);
        if (Schema::hasColumn($householdTable, 'deleted_at')) $query->whereNull('deleted_at');
        return $query->exists();
    }

    private function isUnvalidatedResponder(User $user): bool
    {
        if (! in_array($user->roleKey(), ['rescuer', 'responder'], true)) return false;
        $responderTable = $this->firstExistingTable(['responders', 'responder']);
        if (! $responderTable || ! Schema::hasColumn($responderTable, 'is_validated')) return false;
        $query = DB::table($responderTable);
        if (Schema::hasColumn($responderTable, 'deleted_at')) $query->whereNull('deleted_at');
        $query->where(function ($inner) use ($responderTable, $user): void {
            $this->orWhereExisting($inner, $responderTable, ['user_id', 'username', 'login_id'], $user->user_id ?? $user->username ?? $user->id);
        });
        $isValidated = $query->value('is_validated');
        return $isValidated !== null && (int) $isValidated !== 1;
    }

    private function databaseUnavailableResponse(): ?JsonResponse
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.$connectionName", []);
        $driver = $connection['driver'] ?? null;
        if (! in_array($driver, ['mysql', 'mariadb'], true)) return null;
        $host = (string) ($connection['host'] ?? '');
        $port = (int) ($connection['port'] ?? 3306);
        if ($host === '') return null;

        $timeout = max(1.0, (float) env('DB_CONNECTION_TIMEOUT', 5));
        $target = str_contains($host, ':') ? "tcp://[$host]:$port" : "tcp://$host:$port";
        $socket = @stream_socket_client($target, $errno, $error, $timeout);
        if ($socket === false) {
            Log::warning('Database connectivity check failed.', ['connection' => $connectionName, 'driver' => $driver, 'host' => $host, 'port' => $port, 'errno' => $errno, 'error' => $error]);
            return response()->json(['message' => "The database is not reachable at $host:$port. Make sure the shared MySQL laptop is online, or update DB_HOST to a working database."], 503);
        }
        fclose($socket);
        return null;
    }

    private function userQuery()
    {
        $query = User::query();
        if (Schema::hasTable('roles') && Schema::hasColumn('users', 'role_id')) $query->with('role');
        return $query;
    }

    private function firstExistingTable(array $tables): ?string
    {
        foreach ($tables as $table) if (Schema::hasTable($table)) return $table;
        return null;
    }

    private function orWhereExisting($query, string $table, array $columns, mixed $value): void
    {
        foreach ($columns as $column) if ($value !== null && Schema::hasColumn($table, $column)) $query->orWhere($column, $value);
    }
}
