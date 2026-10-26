<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RealtimeStatus extends Command
{
    protected $signature = 'realtime:status {--prune : Delete only acknowledged events older than 24 hours and expired idempotency records}';

    protected $description = 'Inspect realtime backlog and clean acknowledged records';

    public function handle(): int
    {
        $db = DB::connection(config('realtime.connection'));
        $pending = $db->table('realtime_outbox')->whereNull('delivered_at');
        $oldest = (clone $pending)->min('created_at');
        $this->line(json_encode([
            'pending' => (clone $pending)->count(),
            'oldest_pending_at' => $oldest,
            'oldest_pending_seconds' => $oldest ? max(0, now()->getTimestamp() - strtotime($oldest)) : 0,
            'retried' => (clone $pending)->where('attempts', '>', 1)->count(),
            'queue_connection' => config('realtime.queue_connection'),
        ], JSON_THROW_ON_ERROR));
        if ($this->option('prune')) {
            do {
                $ids = $db->table('realtime_outbox')->where('delivered_at', '<', now()->subDay())->limit(1000)->pluck('id');
                $db->table('realtime_outbox')->whereIn('id', $ids)->delete();
            } while ($ids->count() === 1000);
            do {
                $ids = $db->table('report_idempotency')->where('expires_at', '<', now())->limit(1000)->pluck('scope');
                $db->table('report_idempotency')->whereIn('scope', $ids)->delete();
            } while ($ids->count() === 1000);
        }

        return self::SUCCESS;
    }
}
