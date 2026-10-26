<?php

namespace App\Services\Mobile;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\AuthAccountQuery;
use App\Support\RequestSchema as Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HouseholdAccessService
{
    public static function pending($user): bool
    {
        return str_starts_with((string) ($user?->currentAccessToken()?->name ?? ''), 'household-pending:');
    }

    public function credentialUser(User $user): User
    {
        return app(AuthAccountQuery::class)->householdAccount((string) $user->household_id) ?? $user;
    }

    public function login(Request $request, User $credential): JsonResponse
    {
        $this->requireTables();
        $input = $request->validate(['device_uuid' => ['required', 'string', 'max:150']]);
        return DB::transaction(function () use ($credential, $input): JsonResponse {
            $this->lockHousehold($credential->household_id);
            DB::table('household_login_accounts')->insertOrIgnore([
                'household_id' => $credential->household_id, 'user_id' => $credential->user_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $binding = DB::table('household_device_owners')->where('household_id', $credential->household_id)->where('device_uuid', $input['device_uuid'])->first();
            $owner = $binding ? $this->memberUser($credential, $binding->member_id, false) : null;
            if ($owner) {
                $this->revokeDeviceTokens($credential->household_id, $input['device_uuid']);
                return $this->issue($owner, $input['device_uuid'], false);
            }
            if ($binding) DB::table('household_device_owners')->where('id', $binding->id)->delete();
            $this->revokeDeviceTokens($credential->household_id, $input['device_uuid']);
            return $this->issue($credential, $input['device_uuid'], true);
        }, 3);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->ownerOptions($request->user())]);
    }

    public function select(Request $request): JsonResponse
    {
        $this->requireTables();
        $user = $request->user();
        abort_unless(self::pending($user), 403, 'Reset the device owner in Profile settings before selecting another member.');
        $input = $request->validate(['member_id' => ['required', 'string', 'max:255']]);
        $uuid = $this->deviceUuid($request);
        return DB::transaction(function () use ($request, $user, $uuid, $input): JsonResponse {
            $this->lockHousehold($user->household_id);
            $token = $user->currentAccessToken();
            abort_unless(DB::table('personal_access_tokens')->where('id', $token->id)->exists(), 401, 'Sign in again.');
            $binding = DB::table('household_device_owners')->where('household_id', $user->household_id)->where('device_uuid', $uuid)->first();
            if ($binding && ((int) $binding->selection_token_id !== (int) $token->id || $binding->member_id !== $input['member_id'])) {
                abort(409, 'This phone already has an owner. Sign in again to use the saved owner, or reset it in Profile settings.');
            }
            $owner = $this->memberUser($this->credentialUser($user), $input['member_id'], true);
            abort_unless($owner, 422, 'Choose an active member of this household.');
            DB::table('household_device_owners')->updateOrInsert(['household_id' => $user->household_id, 'device_uuid' => $uuid], [
                'member_id' => $owner->member_id, 'selection_token_id' => $token->id, 'created_at' => $binding->created_at ?? now(), 'updated_at' => now(),
            ]);
            $deviceRequest = Request::create('/api/v1/household/device-location', 'POST', ['device_uuid' => $uuid]);
            $deviceRequest->setUserResolver(fn () => $owner);
            $saved = app(HouseholdMobileSetupWorkflow::class)->updateDeviceLocation($deviceRequest);
            abort_if($saved->getStatusCode() >= 400, $saved->getStatusCode(), 'The device owner could not be saved.');
            // Keep the restricted selection token until the phone confirms its new session.
            // If a slow connection loses this response, the same selection can be retried.
            $this->revokeDeviceTokens($user->household_id, $uuid, false);
            foreach ($token->abilities ?? [] as $ability) {
                if (str_starts_with($ability, 'replace-token:')) $this->tokens($user->household_id)->where('id', (int) substr($ability, strlen('replace-token:')))->delete();
            }
            return $this->issue($owner, $uuid, false);
        }, 3);
    }

    public function reset(Request $request): JsonResponse
    {
        $this->requireTables();
        $user = $request->user();
        abort_if(self::pending($user), 403, 'Choose the device owner first.');
        $uuid = $this->deviceUuid($request);
        return DB::transaction(function () use ($user, $uuid): JsonResponse {
            $this->lockHousehold($user->household_id);
            DB::table('household_device_owners')->where('household_id', $user->household_id)->where('device_uuid', $uuid)->delete();
            $this->tokens($user->household_id)->where('name', 'household-pending:'.$uuid)->delete();
            return $this->issue($this->credentialUser($user), $uuid, true, $user->currentAccessToken()?->id);
        }, 3);
    }

    public function confirm(User $user): void
    {
        $name = (string) ($user->currentAccessToken()?->name ?? '');
        if (str_starts_with($name, 'household-device:')) {
            $this->tokens($user->household_id)->where('name', 'household-pending:'.substr($name, strlen('household-device:')))->delete();
        }
    }

    public function revokeHouseholdSessions(string $householdId, ?int $except = null): void
    {
        $this->tokens($householdId)->when($except !== null, fn ($q) => $q->where('id', '<>', $except))->delete();
    }

    private function issue(User $user, string $uuid, bool $pending, ?int $replaceToken = null): JsonResponse
    {
        $abilities = $pending ? ['household:select-owner'] : ['*'];
        if ($replaceToken !== null) $abilities[] = 'replace-token:'.$replaceToken;
        $token = $user->createToken(($pending ? 'household-pending:' : 'household-device:').$uuid, $abilities, null)->plainTextToken;
        $user->setAttribute('owner_selection_required', $pending);
        $data = ['token' => $token, 'user' => new UserResource($user), 'owner_selection_required' => $pending];
        if ($pending) $data['owner_options'] = $this->ownerOptions($user);
        return response()->json($data);
    }

    private function ownerOptions(User $user): array
    {
        $household = DB::table('households')->where('household_id', $user->household_id)->first();
        $rows = DB::table('household_members')->where('household_id', $user->household_id)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->get();
        $accounts = User::query()->whereIn('member_id', $rows->pluck('member_id'))->get()->keyBy('member_id');
        $members = $rows->filter(function ($member) use ($accounts): bool {
                $account = $accounts->get($member->member_id);
                return ! $account || (empty($account->deleted_at) && ($account->is_active === null || $account->is_active));
            })->map(fn ($member): array => [
                'member_id' => $member->member_id,
                'name' => trim(implode(' ', array_filter([$member->first_name ?? null, $member->middle_name ?? null, $member->last_name ?? null]))) ?: ($member->name ?? 'Household member'),
                'relationship' => ! empty($member->is_household_head) ? 'Head of household' : ($member->relation ?? 'Household member'),
            ])->values()->all();
        return ['household_id' => $user->household_id, 'household_name' => $household->household_name ?? $user->household_id, 'members' => $members];
    }

    private function memberUser(User $credential, string $memberId, bool $create): ?User
    {
        $member = DB::table('household_members')->where('member_id', $memberId)->where('household_id', $credential->household_id)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->first();
        if (! $member) return null;
        $user = User::query()->where('member_id', $memberId)->first();
        if ($user) return $user->household_id === $credential->household_id && empty($user->deleted_at)
            && $user->roleKey() === 'household_resident' && ($user->is_active === null || $user->is_active) ? $user : null;
        if (! $create) return null;
        $values = [
            'user_id' => (string) Str::uuid(), 'household_id' => $credential->household_id, 'member_id' => $memberId,
            'username' => $memberId, 'first_name' => $member->first_name ?? null, 'last_name' => $member->last_name ?? null,
            'name' => $member->name ?? trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            'role_id' => $credential->role_id, 'password' => $credential->getRawOriginal('password'),
            'contact_number' => $member->contact_number ?? null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('users')->insert(array_intersect_key($values, array_flip(Schema::getColumnListing('users'))));
        return User::findOrFail($values['user_id']);
    }

    private function deviceUuid(Request $request): string
    {
        $name = (string) ($request->user()->currentAccessToken()?->name ?? '');
        foreach (['household-device:', 'household-pending:'] as $prefix) {
            if (str_starts_with($name, $prefix)) return substr($name, strlen($prefix));
        }
        $input = $request->validate(['device_uuid' => ['required', 'string', 'max:150']]);
        abort_unless(DB::table('device_tokens')->where('household_id', $request->user()->household_id)->where('user_id', $request->user()->user_id)
            ->where('device_uuid', $input['device_uuid'])->exists(), 409, 'Sign in using the household ID to register this phone first.');
        return $input['device_uuid'];
    }

    private function tokens(string $householdId)
    {
        return DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::query()->where('household_id', $householdId)->select('user_id'));
    }

    private function revokeDeviceTokens(string $householdId, string $uuid, bool $pending = true): void
    {
        $names = ['household-device:'.$uuid];
        if ($pending) $names[] = 'household-pending:'.$uuid;
        $this->tokens($householdId)->whereIn('name', $names)->delete();
    }

    private function lockHousehold(string $id): void
    {
        abort_unless(DB::table('households')->where('household_id', $id)->lockForUpdate()->first(), 404, 'Household not found.');
    }

    private function requireTables(): void
    {
        abort_unless(Schema::hasTable('household_login_accounts') && Schema::hasTable('household_device_owners'), 503, 'The household login update must be installed first.');
    }
}
