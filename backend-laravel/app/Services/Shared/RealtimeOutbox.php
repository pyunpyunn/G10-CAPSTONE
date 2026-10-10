<?php

namespace App\Services\Shared;

use App\Events\NotificationFeedChanged;
use App\Events\OperationsChanged;
use App\Jobs\DeliverRealtimeBatch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RealtimeOutbox
{
    public function capture(QueryExecuted $query): void
    {
        if (! preg_match('/^\s*(?:insert\s+(?:ignore\s+)?into|update|delete\s+from)\s+[`"]?([a-z_]+)[`"]?(?:\s|\()/i', $query->sql, $match)) {
            return;
        }
        $sources = config('realtime.topics');
        $table = strtolower($match[1]);
        if (! array_key_exists($table, $sources)) {
            return;
        }
        if (! config('realtime.durable')) {
            $query->connection->afterCommit(function (): void {
                try {
                    event(new NotificationFeedChanged);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return;
        }
        // Only the canonical operational connection participates in this outbox.
        if ($query->connectionName !== config('realtime.connection')) {
            return;
        }
        // Insert on the SAME connection and transaction as the source write.
        // No socket or Redis dependency exists in the reporting request.
        $query->connection->table('realtime_outbox')->insert([
            'topics' => json_encode($sources[$table], JSON_THROW_ON_ERROR),
            'available_at' => time(),
            'created_at' => now(),
        ]);
    }

    public function claim(): ?string
    {
        $db = DB::connection(config('realtime.connection'));

        return $db->transaction(function () use ($db): ?string {
            $query = $db->table('realtime_outbox')->whereNull('delivered_at')
                ->where('available_at', '<=', time())
                ->where(fn ($q) => $q->whereNull('claimed_until')->orWhere('claimed_until', '<=', time()))
                ->orderBy('id')->limit(max(1, min(2000, config('realtime.batch_size'))));
            $query->lock(config('realtime.skip_locked') && in_array($db->getDriverName(), ['mysql', 'pgsql'], true) ? 'for update skip locked' : true);
            $ids = $query->pluck('id');
            if ($ids->isEmpty()) {
                return null;
            }
            $token = (string) Str::uuid();
            $db->table('realtime_outbox')->whereIn('id', $ids)->update([
                'claim_token' => $token, 'claimed_until' => time() + config('realtime.lease_seconds'),
                'attempts' => DB::raw('attempts + 1'),
            ]);

            return $token;
        }, 3);
    }

    public function dispatchBatch(): bool
    {
        $token = $this->claim();
        if ($token === null) {
            return false;
        }
        try {
            DeliverRealtimeBatch::dispatch($token)->onConnection(config('realtime.queue_connection'))->onQueue('realtime');
        } catch (Throwable $exception) {
            $this->release($token, 5);
            throw $exception;
        }

        return true;
    }

    public function deliver(string $token): void
    {
        $db = DB::connection(config('realtime.connection'));
        $rows = $db->table('realtime_outbox')->where('claim_token', $token)->whereNull('delivered_at')->get();
        if ($rows->isEmpty()) {
            return;
        }
        $topics = $rows->flatMap(fn ($row) => json_decode($row->topics, true, 512, JSON_THROW_ON_ERROR))->unique()->values()->all();
        // Invalidate before notifying browsers, so their next fetch reads the new revision.
        app(RealtimeReadCache::class)->invalidate($token);
        // At-least-once delivery: only acknowledge after BOTH broadcasts succeed.
        // A process crash or lease expiry may repeat a batch; clients deduplicate it.
        if ($topics !== []) {
            event(new OperationsChanged($topics, $token));
        }
        event(new NotificationFeedChanged(null, $token));
        $db->table('realtime_outbox')->where('claim_token', $token)->whereNull('delivered_at')->update([
            'delivered_at' => now(), 'claim_token' => null, 'claimed_until' => null,
        ]);
    }

    public function release(string $token, int $delay = 5): void
    {
        DB::connection(config('realtime.connection'))->table('realtime_outbox')
            ->where('claim_token', $token)->whereNull('delivered_at')->update([
                'claim_token' => null, 'claimed_until' => null, 'available_at' => time() + $delay,
            ]);
    }
}
