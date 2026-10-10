<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\RequestSchema as Schema;

class OneSignalNotificationService
{
    public function prepareDelivery(array $options): array
    {
        $ids = $this->mobilePlayerIds($options);
        sort($ids);

        return [
            'recipient_ids' => $ids,
            'keys' => array_map(fn () => (string) Str::uuid(), array_chunk($ids, 2000)),
        ];
    }

    public function sendToMobileDevices(string $title, string $message, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return $this->result('not_configured', 0, 'OneSignal credentials are not configured.');
        }

        $plan = $options['delivery_plan'] ?? $this->prepareDelivery($options);
        $playerIds = $plan['recipient_ids'];

        if (count($playerIds) > $this->recipientLimit()) {
            return $this->result('limit_exceeded', count($playerIds), 'Mobile recipient limit exceeded; narrow the broadcast audience.');
        }

        if (count($playerIds) === 0) {
            return $this->result('no_recipients', 0, 'No OneSignal mobile recipients were found.');
        }

        $sent = 0;
        $providerIds = [];
        $errors = [];

        foreach (array_chunk($playerIds, 2000) as $index => $chunk) {
            $response = $this->sendChunk($chunk, $title, $message, $options['data'] ?? [], $plan['keys'][$index]);

            if ($response['ok']) {
                $sent += $response['accepted_count'];
                if ($response['error']) $errors[] = $response['error'];

                if ($response['provider_id']) {
                    $providerIds[] = $response['provider_id'];
                }
            } else {
                $errors[] = $response['error'];
            }
        }

        if ($sent === 0) {
            return [
                'status' => 'failed',
                'recipient_count' => count($playerIds),
                'sent_count' => 0,
                'provider_ids' => [],
                'message' => 'OneSignal did not accept the notification.',
                'errors' => $errors,
            ];
        }

        return [
            'status' => empty($errors) ? 'sent' : 'partial',
            'recipient_count' => count($playerIds),
            'sent_count' => $sent,
            'provider_ids' => $providerIds,
            'message' => 'OneSignal notification submitted.',
            'errors' => $errors,
        ];
    }

    public function sendToResponderIds(array $responderIds, string $title, string $message, array $data = []): array
    {
        $responderIds = collect($responderIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($responderIds) === 0) {
            return $this->result('no_recipients', 0, 'No responders were selected.');
        }

        return $this->sendToMobileDevices($title, $message, [
            'roles' => ['rescuer'],
            'responder_ids' => $responderIds,
            'data' => $data,
        ]);
    }

    private function sendChunk(array $playerIds, string $title, string $message, array $data, string $idempotencyKey): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Key '.$this->apiKey(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(2, 500, fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException
                    || ($exception instanceof \Illuminate\Http\Client\RequestException
                        && $exception->response->serverError()), throw: false)
                ->post(rtrim($this->baseUrl(), '/').'/notifications', [
                    'app_id' => $this->appId(),
                    'idempotency_key' => $idempotencyKey,
                    'target_channel' => 'push',
                    'include_subscription_ids' => array_values($playerIds),
                    'headings' => ['en' => $title],
                    'contents' => ['en' => $message],
                    'data' => $data,
                ]);

            if ($response->successful() && is_string($response->json('id')) && $response->json('id') !== '') {
                $errors = $response->json('errors', []);
                $invalid = is_array($errors) ? ($errors['invalid_player_ids'] ?? $errors['invalid_subscription_ids'] ?? []) : [];
                return [
                    'ok' => true,
                    'accepted_count' => max(0, count($playerIds) - count($invalid)),
                    'provider_id' => $response->json('id'),
                    'error' => empty($errors) ? null : substr(json_encode($errors), 0, 300),
                ];
            }

            return [
                'ok' => false,
                'provider_id' => null,
                'error' => 'HTTP '.$response->status().': '.substr($response->body(), 0, 300),
            ];
        } catch (\Throwable $error) {
            Log::warning('OneSignal push failed', [
                'message' => $error->getMessage(),
            ]);

            return [
                'ok' => false,
                'provider_id' => null,
                'error' => $error->getMessage(),
            ];
        }
    }

    private function mobilePlayerIds(array $options): array
    {
        if (! Schema::hasTable('device_tokens') || ! Schema::hasColumn('device_tokens', 'player_id')) {
            return [];
        }

        $query = DB::table('device_tokens as dt')
            ->whereNotNull('dt.player_id')
            ->where('dt.player_id', '<>', '')
            ->where('dt.player_id', 'not like', 'unavailable:%');

        if (Schema::hasColumn('device_tokens', 'push_provider')) {
            $query->where('dt.push_provider', 'onesignal');
        }

        if (Schema::hasColumn('device_tokens', 'notification_permission_status')) {
            $query->where('dt.notification_permission_status', 'granted');
        }

        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $query->where('dt.is_active', 1);
        }

        $this->applyRoleFilter($query, $options['roles'] ?? []);
        $this->applyUserFilter($query, $options['user_ids'] ?? []);
        $this->applyResponderFilter($query, $options['responder_ids'] ?? []);

        if (! empty($options['household_ids'])) {
            if (! Schema::hasColumn('device_tokens', 'household_id')) return [];
            $query->whereIn('dt.household_id', $options['household_ids']);
        }
        if (! empty($options['member_ids'])) {
            if (! Schema::hasColumn('device_tokens', 'member_id')) return [];
            $query->whereIn('dt.member_id', $options['member_ids']);
        }
        if (! empty($options['exclude_member_ids']) && Schema::hasColumn('device_tokens', 'member_id')) {
            $query->where(fn ($inner) => $inner->whereNull('dt.member_id')
                ->orWhereNotIn('dt.member_id', $options['exclude_member_ids']));
        }

        if (! $this->applyPurokFilter($query, $options['household_puroks'] ?? [])) {
            return [];
        }

        return $query
            ->distinct()
            ->limit($this->recipientLimit() + 1)
            ->pluck('dt.player_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function recipientLimit(): int
    {
        return max(1, min(100000, (int) config('services.onesignal.max_recipients', 10000)));
    }

    private function applyRoleFilter($query, array $roles): void
    {
        if (empty($roles)) {
            return;
        }
        if (! Schema::hasColumn('device_tokens', 'app_role')) {
            $query->whereRaw('1 = 0');
            return;
        }

        $normalized = collect($roles)
            ->flatMap(fn (string $role): array => match ($role) {
                'household', 'household_resident' => ['household', 'household_resident'],
                'rescuer' => ['rescuer'],
                default => [$role],
            })
            ->unique()
            ->values()
            ->all();

        $query->whereIn('dt.app_role', $normalized);
    }

    private function applyUserFilter($query, array $userIds): void
    {
        $userIds = collect($userIds)
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            return;
        }
        if (! Schema::hasColumn('device_tokens', 'user_id')) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('dt.user_id', $userIds);
    }

    private function applyResponderFilter($query, array $responderIds): void
    {
        $responderIds = collect($responderIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($responderIds)) {
            return;
        }

        if (Schema::hasColumn('device_tokens', 'responder_id')) {
            $query->whereIn('dt.responder_id', $responderIds);

            return;
        }

        if (Schema::hasColumn('device_tokens', 'user_id') && Schema::hasTable('responders') && Schema::hasColumn('responders', 'user_id')) {
            $query
                ->join('responders as r_filter', 'r_filter.user_id', '=', 'dt.user_id')
                ->whereIn('r_filter.responder_id', $responderIds);
            return;
        }
        $query->whereRaw('1 = 0');
    }

    private function applyPurokFilter($query, array $purokNames): bool
    {
        $purokNames = collect($purokNames)
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($purokNames)
            || ! Schema::hasColumn('device_tokens', 'household_id')
            || ! Schema::hasTable('households')
            || ! Schema::hasColumn('households', 'household_id')
            || ! Schema::hasColumn('households', 'address_id')
            || ! Schema::hasTable('addresses')
            || ! Schema::hasColumn('addresses', 'address_id')
            || ! Schema::hasColumn('addresses', 'purok_sitio')) {
            return empty($purokNames);
        }

        $query
            ->join('households as h_filter', 'h_filter.household_id', '=', 'dt.household_id')
            ->join('addresses as a_filter', 'a_filter.address_id', '=', 'h_filter.address_id')
            ->whereIn('a_filter.purok_sitio', $purokNames);

        if (Schema::hasColumn('households', 'deleted_at')) {
            $query->whereNull('h_filter.deleted_at');
        }

        if (Schema::hasColumn('addresses', 'deleted_at')) {
            $query->whereNull('a_filter.deleted_at');
        }

        return true;
    }

    private function isConfigured(): bool
    {
        return $this->appId() !== '' && $this->apiKey() !== '';
    }

    private function appId(): string
    {
        return trim((string) config('services.onesignal.app_id'));
    }

    private function apiKey(): string
    {
        return trim((string) config('services.onesignal.api_key'));
    }

    private function baseUrl(): string
    {
        return trim((string) config('services.onesignal.base_url', 'https://api.onesignal.com'));
    }

    private function result(string $status, int $recipientCount, string $message): array
    {
        return [
            'status' => $status,
            'recipient_count' => $recipientCount,
            'sent_count' => 0,
            'provider_ids' => [],
            'message' => $message,
            'errors' => [],
        ];
    }
}







