<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OneSignalNotificationService
{
    public function sendToMobileDevices(string $title, string $message, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return $this->result('not_configured', 0, 'OneSignal credentials are not configured.');
        }

        $playerIds = $this->mobilePlayerIds($options);

        if (count($playerIds) === 0) {
            return $this->result('no_recipients', 0, 'No OneSignal mobile recipients were found.');
        }

        $sent = 0;
        $providerIds = [];
        $errors = [];

        foreach (array_chunk($playerIds, 2000) as $chunk) {
            $response = $this->sendChunk($chunk, $title, $message, $options['data'] ?? []);

            if ($response['ok']) {
                $sent += count($chunk);

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

    private function sendChunk(array $playerIds, string $title, string $message, array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Key '.$this->apiKey(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->retry(2, 500)
                ->post(rtrim($this->baseUrl(), '/').'/notifications', [
                    'app_id' => $this->appId(),
                    'target_channel' => 'push',
                    'include_subscription_ids' => array_values($playerIds),
                    'headings' => ['en' => $title],
                    'contents' => ['en' => $message],
                    'data' => $data,
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'provider_id' => $response->json('id'),
                    'error' => null,
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
        $this->applyResponderFilter($query, $options['responder_ids'] ?? []);
        $this->applyPurokFilter($query, $options['household_puroks'] ?? []);

        return $query
            ->distinct()
            ->pluck('dt.player_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function applyRoleFilter($query, array $roles): void
    {
        if (empty($roles) || ! Schema::hasColumn('device_tokens', 'app_role')) {
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
        }
    }

    private function applyPurokFilter($query, array $purokNames): void
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
            || ! Schema::hasColumn('households', 'address_id')
            || ! Schema::hasTable('addresses')
            || ! Schema::hasColumn('addresses', 'purok_sitio')) {
            return;
        }

        $query
            ->join('households as h_filter', 'h_filter.household_id', '=', 'dt.household_id')
            ->join('addresses as a_filter', 'a_filter.address_id', '=', 'h_filter.address_id')
            ->whereIn('a_filter.purok_sitio', $purokNames);
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
