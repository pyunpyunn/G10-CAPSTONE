<?php

namespace App\Services\Shared;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\MobileLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Responder;
use App\Models\Household;
use App\Queries\AuthAccountQuery;
use App\Services\Shared\SmsGatewayService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Support\RequestSchema as Schema;
use PDOException;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private AuthAccountQuery $accounts,
        private SmsGatewayService $smsGateway,
        private SemaphoreSmsService $semaphore,
    ) {}
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
        return $this->authenticate($request, false);
    }

    public function mobileLogin(MobileLoginRequest $request): JsonResponse
    {
        return $this->authenticate($request, true);
    }

    private function authenticate(LoginRequest $request, bool $deviceBound): JsonResponse
    {
        $login = trim($request->string('login')->toString());

        try {
            $user = $this->accounts->userFromLogin($login);
        } catch (QueryException|PDOException) {
            return response()->json(['message' => 'The database is not reachable right now. Make sure the shared MySQL laptop is online, or switch Laravel to a working local database.'], 503);
        }

        if (! $user || ! $user->password) {
            throw ValidationException::withMessages(['login' => ['The login details are incorrect.']]);
        }

        $passwordMatches = $this->checkStoredPassword($request->password, $user->password);
        if ($passwordMatches === null) {
            return response()->json([
                'message' => 'This account password cannot be verified. Use Forgot password to reset it, or contact HQ/Admin.',
            ], 409);
        }
        if (! $passwordMatches) {
            throw ValidationException::withMessages(['login' => ['The login details are incorrect.']]);
        }

        if ($user->is_active !== null && ! (bool) $user->is_active) {
            return response()->json(['message' => 'This account is inactive. Please contact HQ/Admin.'], 403);
        }

        if ($user->roleKey() === 'household_resident' && strcasecmp($login, (string) $user->household_id) !== 0) {
            throw ValidationException::withMessages(['login' => ['Sign in using your household ID and household password.']]);
        }

        if ($this->isUnvalidatedResponder($user)) {
            return response()->json(['message' => 'This rescuer account is pending HQ validation. Please wait for approval before logging in.'], 403);
        }

        if (! $this->isHouseholdAccountReady($user)) {
            return response()->json(['message' => 'This household account is not linked to a registered household record. Please contact SafeTrack or HQ/Admin.'], 409);
        }

        if ($user->roleKey() === 'household_resident' && ! $deviceBound) {
            return app(\App\Services\Mobile\HouseholdAccessService::class)->login($request, $user);
        }

        $deviceUuid = null;
        $responder = null;
        if ($deviceBound) {
            if (! in_array($user->roleKey(), ['rescuer', 'tl', 'tm'], true)) {
                return response()->json(['message' => 'Device-bound navigation login is only available to rescuer accounts.'], 403);
            }
            if (! Schema::hasTable('devices') || ! Schema::hasTable('responders')) {
                return response()->json(['message' => 'Mobile device registration is not available in the current database.'], 503);
            }

            $deviceUuid = (string) $request->input('device_uuid');
            $responder = Responder::query()->where('user_id', $user->user_id)->first();
            if (! $responder || ! $responder->team_id) {
                return response()->json(['message' => 'This rescuer account is not assigned to a rescue team.'], 409);
            }

            $existingDevice = DB::table('devices')->where('device_uuid', $deviceUuid)->first();
            if ($existingDevice && (string) $existingDevice->user_id !== (string) $user->user_id) {
                return response()->json(['message' => 'This device is already registered to another account.'], 409);
            }
        }

        $token = null;
        if (Schema::hasTable('personal_access_tokens')) {
            $tokenName = $deviceBound ? 'mobile-device:'.$deviceUuid : $request->input('device_name', 'resqperation-client');
            if ($deviceBound) {
                DB::transaction(function () use ($user, $responder, $deviceUuid, $tokenName, &$token): void {
                    $user->tokens()->where('name', $tokenName)->delete();
                    $token = $user->createToken($tokenName, ['mobile:rescuer', 'team:'.(int) $responder->team_id])->plainTextToken;
                    $device = DB::table('devices')->where('device_uuid', $deviceUuid)->first();
                    $deviceData = [
                        'user_id' => $user->user_id,
                        'team_id' => $responder->team_id,
                        'updated_at' => now(),
                    ];
                    if ($device) {
                        DB::table('devices')->where('id', $device->id)->update($deviceData);
                    } else {
                        DB::table('devices')->insert(array_merge($deviceData, [
                            'device_uuid' => $deviceUuid,
                            'last_sync_at' => null,
                            'created_at' => now(),
                        ]));
                    }
                }, 3);
            } else {
                $token = $user->createToken($tokenName)->plainTextToken;
            }
        } elseif (app()->environment('local', 'testing')) {
            Log::warning('Sanctum personal_access_tokens table is missing; login has no token to issue.', ['user_id' => $user->user_id]);
        }

        $data = ['message' => 'Login successful.', 'token' => $token, 'user' => new UserResource($user)];
        if ($deviceBound) {
            $data['device_uuid'] = $deviceUuid;
            $data['team_id'] = (int) $responder->team_id;
        }

        return response()->json($data);
    }

    private function checkStoredPassword(string $password, string $storedHash): ?bool
    {
        $algorithm = password_get_info($storedHash)['algoName'] ?? 'unknown';
        $driver = match ($algorithm) {
            'bcrypt' => 'bcrypt',
            'argon2i' => 'argon',
            'argon2id' => 'argon2id',
            default => null,
        };

        return $driver === null ? null : Hash::driver($driver)->check($password, $storedHash);
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user()->refresh()->load('role');
        if ($user->roleKey() === 'household_resident') app(\App\Services\Mobile\HouseholdAccessService::class)->confirm($user);
        return new UserResource($user);
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
        $user = $this->accounts->userFromLogin($validated['login']);

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

    public function verifyPasswordChange(Request $request): JsonResponse
    {
        $values = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'current_password' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
        $user = $this->accounts->userFromLogin(trim($values['login']));
        if (! $user) {
            throw ValidationException::withMessages(['login' => ['Account ID does not exist. Check the ID issued by your administrator.']]);
        }
        if ($user->is_active !== null && ! $user->is_active) {
            return response()->json(['message' => 'This account is inactive. Contact HQ/Admin.'], 403);
        }
        if (array_key_exists('current_password', $values) &&
            (! $user->password || ! Hash::check($values['current_password'], $user->password))) {
            throw ValidationException::withMessages(['current_password' => ['Old password is incorrect.']]);
        }
        return response()->json(['message' => array_key_exists('current_password', $values) ? 'Old password verified.' : 'Account ID verified.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $values = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);
        $user = $this->accounts->userFromLogin(trim($values['login']));
        if (! $user) {
            throw ValidationException::withMessages(['login' => ['Account ID does not exist. Check the ID issued by your administrator.']]);
        }
        if (! $user->password || ! Hash::check($values['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Old password is incorrect.']]);
        }
        if ($user->is_active !== null && ! $user->is_active) {
            return response()->json(['message' => 'This account is inactive. Contact HQ/Admin.'], 403);
        }
        if (Hash::check($values['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Choose a different new password.']]);
        }
        DB::transaction(function () use ($user, $values): void {
            $updates = ['password' => Hash::make($values['password']), 'updated_at' => now()];
            if (Schema::hasColumn('users', 'password_changed_at')) $updates['password_changed_at'] = now();
            if (Schema::hasColumn('users', 'must_change_password')) $updates['must_change_password'] = 0;
            User::query()->whereKey($user->getKey())->update($updates);
            if (Schema::hasTable('personal_access_tokens')) {
                if ($user->roleKey() === 'household_resident') app(\App\Services\Mobile\HouseholdAccessService::class)->revokeHouseholdSessions($user->household_id);
                else $user->tokens()->delete();
            }
        });
        return response()->json(['message' => 'Password changed successfully. Sign in with your new password.']);
    }

    public function lookupHouseholdRecoveryContact(Request $request): JsonResponse
    {
        $values = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $details = $this->householdRecoveryDetails(trim($values['login']));

        if (! $details) {
            throw ValidationException::withMessages(['login' => ['No active account matches this ID. Check the household or rescuer ID and try again.']]);
        }
        if (! $details['contact_number']) {
            throw ValidationException::withMessages(['login' => ['This account exists, but no valid registered recovery phone number is on file. Contact an administrator to update it before requesting an OTP.']]);
        }

        return response()->json([
            'masked_contact_number' => $this->maskPhoneNumber($details['contact_number']),
        ]);
    }

    public function validateHouseholdRecoveryContact(Request $request): JsonResponse
    {
        $values = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:40'],
        ]);
        $details = $this->householdRecoveryDetails(trim($values['login']));

        if (! $details) {
            throw ValidationException::withMessages([
                'login' => ['No active account matches this ID. Check the household or rescuer ID and try again.'],
            ]);
        }
        if (! $details['contact_number']) {
            throw ValidationException::withMessages([
                'contact_number' => ['This account exists, but no valid registered recovery phone number is on file. Contact an administrator to update it before requesting an OTP.'],
            ]);
        }
        if (! $this->phoneNumbersMatch($values['contact_number'], $details['contact_number'])) {
            throw ValidationException::withMessages([
                'contact_number' => ['This number does not match a phone number registered to this account.'],
            ]);
        }

        return response()->json(['message' => 'Registered contact number verified.']);
    }

    public function sendHouseholdRecoveryCode(Request $request): JsonResponse
    {
        $values = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:40'],
        ]);
        $details = $this->householdRecoveryDetails(trim($values['login']));

        if (! $details) {
            throw ValidationException::withMessages([
                'login' => ['No active account matches this ID. Check the household or rescuer ID and try again.'],
            ]);
        }
        if (! $details['contact_number']) {
            throw ValidationException::withMessages([
                'contact_number' => ['This account exists, but no valid registered recovery phone number is on file. Contact an administrator to update it before requesting an OTP.'],
            ]);
        }
        if (! $this->phoneNumbersMatch($values['contact_number'], $details['contact_number'])) {
            throw ValidationException::withMessages([
                'contact_number' => ['This number does not match a phone number registered to this account.'],
            ]);
        }
        if (! Schema::hasTable('household_password_recovery_otps')) {
            return response()->json(['message' => 'Password recovery is not configured. Run the latest database migration.'], 503);
        }

        $code = (string) random_int(100000, 999999);
        $smsMessage = "Your ResQperation password reset code is {$code}. It expires in 10 minutes.";
        $smsProviders = [];
        $smsProviders[] = fn (): array => $this->smsGateway->sendDirectSms($details['contact_number'], $smsMessage);
        if ($this->semaphore->isConfigured()) {
            $smsProviders[] = fn (): array => $this->semaphore->sendDirectSms($details['contact_number'], $smsMessage);
        }
        $sms = null;
        $lastFailure = null;
        foreach ($smsProviders as $sendSms) {
            $smsResult = $sendSms();
            if ($smsResult['status'] === 'accepted') {
                $sms = $smsResult;
                break;
            }
            if ($smsResult['status'] !== 'not_configured') {
                $lastFailure = $smsResult;
            }
        }
        if ($sms === null) {
            $message = $lastFailure['message']
                ?? 'SMS delivery is not configured. Start the Android SMS Gateway local server and configure its URL and credentials, or configure a Semaphore API key.';

            return response()->json(['message' => $message], 503);
        }

        DB::table('household_password_recovery_otps')->updateOrInsert(
            ['user_id' => $details['user']->user_id],
            [
                'household_id' => $details['household_id'],
                'code_hash' => Hash::make($code),
                'failed_attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['message' => 'A password reset code was sent by SMS.']);
    }

    public function resetHouseholdPassword(Request $request): JsonResponse
    {
        $values = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:40'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);
        $details = $this->householdRecoveryDetails(trim($values['login']));

        if (! $details) {
            throw ValidationException::withMessages([
                'login' => ['No active account matches this ID. Check the household or rescuer ID and try again.'],
            ]);
        }
        if (! $details['contact_number']) {
            throw ValidationException::withMessages([
                'contact_number' => ['This account exists, but no valid registered recovery phone number is on file. Contact an administrator to update it before requesting an OTP.'],
            ]);
        }
        if (! $this->phoneNumbersMatch($values['contact_number'], $details['contact_number'])) {
            throw ValidationException::withMessages([
                'contact_number' => ['This number does not match a phone number registered to this account.'],
            ]);
        }
        if (! Schema::hasTable('household_password_recovery_otps')) {
            return response()->json(['message' => 'Password recovery is not configured. Run the latest database migration.'], 503);
        }
        if ($details['user']->is_active !== null && ! $details['user']->is_active) {
            return response()->json(['message' => 'This account is inactive. Contact HQ/Admin.'], 403);
        }

        $result = DB::transaction(function () use ($details, $values): array {
            $table = DB::table('household_password_recovery_otps');
            $recovery = $table->where('user_id', $details['user']->user_id)->lockForUpdate()->first();

            if (! $recovery) {
                return ['error' => 'Request a new verification code.'];
            }
            if ((string) $recovery->household_id !== $details['household_id']) {
                $table->where('user_id', $details['user']->user_id)->delete();
                return ['error' => 'Household details changed. Request a new verification code.'];
            }
            if (now()->gte(\Illuminate\Support\Carbon::parse($recovery->expires_at))) {
                $table->where('user_id', $details['user']->user_id)->delete();
                return ['error' => 'Your verification code has expired. Request a new code.'];
            }
            if (! Hash::check($values['code'], $recovery->code_hash)) {
                $attempts = (int) $recovery->failed_attempts + 1;
                if ($attempts >= 5) {
                    $table->where('user_id', $details['user']->user_id)->delete();
                    return ['error' => 'Too many incorrect codes. Request a new verification code.'];
                }
                $table->where('user_id', $details['user']->user_id)->update([
                    'failed_attempts' => $attempts,
                    'updated_at' => now(),
                ]);
                return ['error' => 'The verification code is incorrect.'];
            }
            if (! $details['user']->password || Hash::check($values['password'], $details['user']->password)) {
                return ['error' => 'Choose a different new password.'];
            }

            $updates = ['password' => Hash::make($values['password']), 'updated_at' => now()];
            if (Schema::hasColumn('users', 'password_changed_at')) {
                $updates['password_changed_at'] = now();
            }
            if (Schema::hasColumn('users', 'must_change_password')) {
                $updates['must_change_password'] = 0;
            }
            User::query()->whereKey($details['user']->user_id)->update($updates);
            if (Schema::hasTable('personal_access_tokens')) {
                if ($details['user']->roleKey() === 'household_resident') app(\App\Services\Mobile\HouseholdAccessService::class)->revokeHouseholdSessions($details['user']->household_id);
                else $details['user']->tokens()->delete();
            }
            $table->where('user_id', $details['user']->user_id)->delete();

            return ['account_identifier' => $details['account_identifier']];
        });

        if (isset($result['error'])) {
            throw ValidationException::withMessages(['code' => [$result['error']]]);
        }

        return response()->json([
            'message' => 'Password changed successfully.',
            'household_id' => $result['account_identifier'],
            'account_identifier' => $result['account_identifier'],
        ]);
    }

    /**
     * @return array{user: User, household_id: string, account_identifier: string, contact_number: ?string}|null
     */
    private function householdRecoveryDetails(string $login): ?array
    {
        $user = $this->accounts->userFromLogin($login);
        if (! $user
            || ($user->is_active !== null && ! $user->is_active)
            || (Schema::hasColumn('users', 'deleted_at') && $user->deleted_at)) {
            return null;
        }
        $user->loadMissing('role');
        if ($user->roleKey() === 'household_resident' && strcasecmp($login, (string) $user->household_id) !== 0) return null;

        $householdId = Schema::hasColumn('users', 'household_id') ? (string) ($user->household_id ?? '') : '';
        $contactNumbers = [];
        if ($user->roleKey() === 'household_resident' && $user->member_id) {
            $member = app(\App\Queries\HouseholdMobileReadQuery::class)->memberForUser($user);
            if ($member?->contact_number) $contactNumbers[] = (string) $member->contact_number;
            if ($user->contact_number) $contactNumbers[] = (string) $user->contact_number;
        }
        if ($householdId !== ''
            && Schema::hasTable('households')
            && Schema::hasColumn('households', 'household_id')
            && Schema::hasColumn('households', 'contact_number')) {
            $household = DB::table('households')
                ->where('household_id', $householdId)
                ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->first(['household_id', 'contact_number']);
            if ($household?->contact_number) {
                $contactNumbers[] = (string) $household->contact_number;
            }
        }

        if (Schema::hasColumn('users', 'contact_number') && filled($user->contact_number)) {
            $contactNumbers[] = (string) $user->contact_number;
        }

        if (Schema::hasTable('responders')
            && Schema::hasColumn('responders', 'user_id')
            && Schema::hasColumn('responders', 'contact_number')) {
            $responder = DB::table('responders')
                ->where('user_id', $user->user_id)
                ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->first(['responder_id', 'contact_number']);
            if ($responder?->contact_number) {
                $contactNumbers[] = (string) $responder->contact_number;
            }
        }

        $contactNumber = collect($contactNumbers)
            ->first(fn (string $number): bool => $this->normalizePhoneNumber($number) !== null);

        $accountIdentifier = $householdId !== '' ? $householdId : trim((string) ($user->username ?: ($user->login_id ?? $user->user_id)));
        $recoveryScopeId = $householdId !== '' ? $householdId : (string) $user->user_id;

        return [
            'user' => $user,
            'household_id' => $recoveryScopeId,
            'account_identifier' => $accountIdentifier,
            'contact_number' => $contactNumber,
        ];
    }

    private function phoneNumbersMatch(string $input, string $registered): bool
    {
        $normalizedInput = $this->normalizePhoneNumber($input);
        return $normalizedInput !== null && $normalizedInput === $this->normalizePhoneNumber($registered);
    }

    private function normalizePhoneNumber(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if (! $digits) {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+63'.substr($digits, 1);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '+63'.$digits;
        }
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        return null;
    }

    private function maskPhoneNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        return str_repeat('*', max(0, strlen($digits) - 2)).substr($digits, -2);
    }

    private function hasRecoveryColumns(): bool
    {
        foreach (['security_question_1', 'security_question_2', 'security_answer_1', 'security_answer_2'] as $column) {
            if (! Schema::hasColumn('users', $column)) return false;
        }
        return true;
    }

    private function normalizeAnswer(string $answer): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($answer)));
    }

    private function storeRecoveryQuestions(User $user, array $values): void
    {
        User::query()->whereKey($user->user_id)->update([
            'security_question_1' => $values['question_1'],
            'security_answer_1' => Hash::make($this->normalizeAnswer($values['answer_1'])),
            'security_question_2' => $values['question_2'],
            'security_answer_2' => Hash::make($this->normalizeAnswer($values['answer_2'])),
            'updated_at' => now(),
        ]);
    }

    private function isHouseholdAccountReady(User $user): bool
    {
        if ($user->roleKey() !== 'household_resident') {
            return true;
        }

        if (! $user->household_id || ! Schema::hasTable('households')) {
            return false;
        }

        return Household::query()
            ->whereKey($user->household_id)
            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->exists();
    }

    private function isUnvalidatedResponder(User $user): bool
    {
        if (! in_array($user->roleKey(), ['rescuer', 'responder'], true)) {
            return false;
        }

        if (! Schema::hasTable('responders') || ! Schema::hasColumn('responders', 'is_validated')) {
            return false;
        }

        $isValidated = Responder::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->user_id)
                    ->orWhere('username', $user->username);
            })
            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->value('is_validated');

        return $isValidated !== null && (int) $isValidated !== 1;
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
        foreach ($tables as $table) if (Schema::hasTable($table)) return $table;
        return null;
    }

    private function orWhereExisting($query, string $table, array $columns, mixed $value): void
    {
        foreach ($columns as $column) if ($value !== null && Schema::hasColumn($table, $column)) $query->orWhere($column, $value);
    }
}
