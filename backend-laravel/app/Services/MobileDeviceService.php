<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MobileDeviceService
{
    public function storePushToken(Request $request): JsonResponse
    {
        if (! Schema::hasTable('device_tokens')) {
            return response()->json([
                'message' => 'The device_tokens table is not available in the selected database.',
            ], 503);
        }

        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:150'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'push_provider' => ['nullable', Rule::in(['onesignal'])],
            'player_id' => ['nullable', 'string', 'max:255'],
            'push_token' => ['nullable', 'string', 'max:255'],
            'one_signal_user_id' => ['nullable', 'string', 'max:255'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable'],
            'notification_permission_status' => [
                'required',
                Rule::in(['granted', 'denied', 'undetermined', 'unavailable', 'error']),
            ],
        ]);

        $user = $request->user()?->load('role');
        $roleKey = $user?->roleKey();

        if (! $user || ! in_array($roleKey, ['household_resident', 'rescuer'], true)) {
            return response()->json([
                'message' => 'Only authenticated household and rescuer mobile accounts can register a device.',
            ], 403);
        }

        $tokenColumn = $this->oneSignalTokenColumn();

        if (! $tokenColumn) {
            return response()->json([
                'message' => 'The selected database has no OneSignal player_id column. Add device_tokens.player_id before registering mobile notifications.',
            ], 503);
        }

        $responderId = $roleKey === 'rescuer' ? $this->responderId($user->user_id) : null;
        $canLinkUser = Schema::hasColumn('device_tokens', 'user_id');
        $canLinkResponder = Schema::hasColumn('device_tokens', 'responder_id');
        $canLinkMember = Schema::hasColumn('device_tokens', 'member_id');
        $householdId = $roleKey === 'household_resident' ? $user->household_id : null;
        $appRole = $roleKey === 'household_resident' ? 'household' : $roleKey;
        $savedPlayerId = $this->oneSignalPlayerIdForStorage($validated);

        if ($roleKey === 'rescuer' && ! $canLinkUser && ! $canLinkResponder && ! $canLinkMember) {
            return $this->storeRescuerTokenOnUsersTable($user->user_id, $validated);
        }

        $existing = $this->existingDevice(
            $validated['device_uuid'],
            $user->user_id,
            $householdId,
            $responderId,
            $canLinkUser,
            $canLinkResponder,
            $canLinkMember,
            $roleKey
        );

        $now = now();
        $data = [
            'household_id' => $householdId,
            'user_id' => $canLinkUser ? $user->user_id : null,
            'responder_id' => $canLinkResponder ? $responderId : null,
            'device_uuid' => $validated['device_uuid'],
            'device_name' => $validated['device_name'] ?? 'RESQPERATION mobile',
            'platform' => $validated['platform'],
            'app_role' => $appRole,
            'push_provider' => 'onesignal',
            $tokenColumn => $savedPlayerId,
            'push_token' => $this->stringOrFallback($validated['push_token'] ?? null, $savedPlayerId),
            'one_signal_user_id' => $this->stringOrFallback($validated['one_signal_user_id'] ?? null, $savedPlayerId),
            'notification_permission_status' => $validated['notification_permission_status'],
            'battery_level' => $validated['battery_level'] ?? 0,
            'signal_strength' => $validated['signal_strength'] ?? 0,
            'last_seen_at' => $now,
            'logged_at' => $now,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if (! $canLinkUser && ! $canLinkResponder && $canLinkMember) {
            $data['member_id'] = $user->user_id;
        }

        $data = $this->filterColumns('device_tokens', $data);

        DB::transaction(function () use ($existing, $data, $now): void {
            if ($existing) {
                DB::table('device_tokens')->where('id', $existing->id)->update($data);

                return;
            }

            DB::table('device_tokens')->insert($this->filterColumns('device_tokens', array_merge($data, [
                'id' => $this->nextId('device_tokens', 'id'),
                'created_at' => $now,
            ])));
        });

        return response()->json([
            'message' => 'Mobile notification registration saved.',
            'data' => [
                'permission_status' => $validated['notification_permission_status'],
                'push_token_saved' => $this->isUsableOneSignalPlayerId($savedPlayerId),
                'storage_column' => $tokenColumn,
            ],
        ]);
    }

    private function existingDevice(
        string $deviceUuid,
        string $userId,
        ?string $householdId,
        ?int $responderId,
        bool $canLinkUser,
        bool $canLinkResponder,
        bool $canLinkMember,
        string $roleKey
    ): ?object {
        $query = DB::table('device_tokens');

        if (Schema::hasColumn('device_tokens', 'device_uuid')) {
            $query->where('device_uuid', $deviceUuid);
        }

        if ($roleKey === 'household_resident' && $householdId && Schema::hasColumn('device_tokens', 'household_id')) {
            $query->where('household_id', $householdId);
        } elseif ($canLinkUser) {
            $query->where('user_id', $userId);
        } elseif ($canLinkResponder && $responderId) {
            $query->where('responder_id', $responderId);
        } elseif ($canLinkMember) {
            $query->where('member_id', $userId);
        } elseif ($householdId && Schema::hasColumn('device_tokens', 'household_id')) {
            $query->where('household_id', $householdId);
        } else {
            return null;
        }

        return $query->first();
    }

    private function storeRescuerTokenOnUsersTable(string $userId, array $validated): JsonResponse
    {
        $tokenColumn = collect(['player_id', 'push_token'])
            ->first(fn (string $column): bool => Schema::hasColumn('users', $column));

        if (! $tokenColumn) {
            return response()->json([
                'message' => 'Rescuer push tokens need device_tokens.user_id or device_tokens.responder_id so each token is linked to the correct account.',
            ], 503);
        }

        $savedToken = $this->oneSignalPlayerIdForStorage($validated);

        DB::table('users')
            ->where('user_id', $userId)
            ->update([
                $tokenColumn => $savedToken,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Rescuer notification registration saved.',
            'data' => [
                'permission_status' => $validated['notification_permission_status'],
                'push_token_saved' => $this->isUsableOneSignalPlayerId($savedToken),
                'storage_column' => 'users.'.$tokenColumn,
            ],
        ]);
    }

    private function oneSignalPlayerIdForStorage(array $validated): string
    {
        $token = trim((string) ($validated['player_id'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        $status = $validated['notification_permission_status'] ?? 'unavailable';
        $deviceUuid = $validated['device_uuid'] ?? 'unknown-device';

        return substr('unavailable:'.$status.':'.$deviceUuid, 0, 255);
    }

    private function isUsableOneSignalPlayerId(string $token): bool
    {
        return $token !== '' && ! str_starts_with($token, 'unavailable:');
    }

    private function oneSignalTokenColumn(): ?string
    {
        if (Schema::hasColumn('device_tokens', 'player_id')) {
            return 'player_id';
        }

        return null;
    }

    private function stringOrFallback(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value === '' ? $fallback : $value;
    }

    private function responderId(string $userId): ?int
    {
        if (! Schema::hasTable('responders') || ! Schema::hasColumn('responders', 'user_id')) {
            return null;
        }

        $id = DB::table('responders')->where('user_id', $userId)->value('responder_id');

        return $id ? (int) $id : null;
    }

    private function nextId(string $table, string $column): int
    {
        return ((int) DB::table($table)->max($column)) + 1;
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->filter(fn ($value, string $key): bool => in_array($key, $columns, true))
            ->all();
    }
}
