<?php

namespace App\Services\Web;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class BroadcastProviderHistory
{
    public function recover(bool $write = false): array
    {
        $broadcasts = DB::table('disaster_broadcasts')->get()->keyBy('broadcast_id');
        $found = [];
        $warnings = [];
        // Read operational submissions only; automated-test provider IDs are never imported.
        foreach ($this->logPaths() as $path) {
            $stream = fopen($path, 'r');
            if (! $stream) continue;
            try {
                while (($line = fgets($stream)) !== false) {
                    if (! preg_match('/\] (?:local|production)\.INFO: Disaster broadcast push submission completed (\{.*\})\s*$/', $line, $match)) continue;
                    $entry = json_decode($match[1], true);
                    $id = $entry['broadcast_id'] ?? null;
                    if (! $broadcasts->has($id)) continue;
                    foreach (app(NotificationArchive::class)->providerIds($entry['provider_ids'] ?? []) as $providerId) {
                        $found[$id][$providerId] = 'operational_log';
                    }
                }
            } finally { fclose($stream); }
        }

        $appId = trim((string) config('services.onesignal.app_id'));
        $key = trim((string) config('services.onesignal.api_key'));
        $apiCount = 0;
        $apiComplete = false;
        if ($appId === '' || $key === '') $warnings[] = 'OneSignal credentials unavailable; only operational logs were inspected.';
        else {
            // Read-only message history; never create or resend notifications during recovery.
            for ($offset = 0; $offset < 50000; $offset += 50) {
                try {
                    $response = Http::withHeaders(['Authorization' => 'Key '.$key, 'Accept' => 'application/json'])
                        ->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])
                        ->get(rtrim(config('services.onesignal.base_url', 'https://api.onesignal.com'), '/').'/notifications',
                            ['app_id' => $appId, 'limit' => 50, 'offset' => $offset]);
                    if (! $response->successful()) {
                        $warnings[] = 'OneSignal history returned HTTP '.$response->status().'; recovery is incomplete.';
                        break;
                    }
                    $messages = $response->json('notifications');
                    if (! is_array($messages)) {
                        $warnings[] = 'OneSignal history response had no message list.';
                        break;
                    }
                    $apiCount += count($messages);
                    foreach ($messages as $message) {
                        $data = $message['data'] ?? [];
                        if (! is_array($data)) continue;
                        $id = $data['broadcast_id'] ?? null;
                        $row = $broadcasts->get($id);
                        if (! $row || ! in_array($data['type'] ?? '', ['disaster_broadcast', 'rescuer_broadcast'], true)) continue;
                        // Titles and timestamps alone are ambiguous, especially after test runs.
                        if ((string) ($data['event_id'] ?? '') !== (string) $row->disaster_id) continue;
                        foreach (app(NotificationArchive::class)->providerIds([$message['id'] ?? null]) as $providerId) {
                            $found[$id][$providerId] = 'onesignal_api';
                        }
                    }
                    if (count($messages) < 50 || $offset + count($messages) >= (int) $response->json('total_count', PHP_INT_MAX)) {
                        $apiComplete = true;
                        break;
                    }
                } catch (\Throwable) {
                    // Exception messages can include request details; do not print credentials or response bodies.
                    $warnings[] = 'OneSignal history request failed; operational-log recovery is still available.';
                    break;
                }
            }
            if (! $apiComplete && $warnings === []) $warnings[] = 'OneSignal history safety limit reached; recovery is incomplete.';
        }

        $result = [];
        foreach ($broadcasts as $id => $row) {
            $evidence = $found[$id] ?? [];
            $existing = app(NotificationArchive::class)->providerIds($row->push_provider_ids_json ?? null);
            $ids = array_values(array_unique([...$existing, ...array_keys($evidence)]));
            $result[] = ['broadcast_id' => $id, 'onesignal_ids' => $ids, 'evidence' => $evidence];
            if ($write && $ids && Schema::hasColumn('disaster_broadcasts', 'push_provider_ids_json')) {
                DB::transaction(function () use ($id, $ids): void {
                    $row = DB::table('disaster_broadcasts')->where('broadcast_id', $id)->lockForUpdate()->first();
                    $current = app(NotificationArchive::class)->providerIds($row->push_provider_ids_json ?? null);
                    $merged = array_values(array_unique([...$current, ...$ids]));
                    if ($current === $merged) return;
                    DB::table('disaster_broadcasts')->where('broadcast_id', $id)->update([
                        'push_provider_ids_json' => json_encode($merged, JSON_THROW_ON_ERROR),
                    ]);
                });
            }
        }
        return ['api_messages_inspected' => $apiCount, 'api_complete' => $apiComplete, 'warnings' => $warnings, 'broadcasts' => $result];
    }

    protected function logPaths(): array
    {
        return glob(storage_path('logs/*.log')) ?: [];
    }
}
