<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RealtimeStatus extends Command
{
    protected $signature = 'realtime:status {--prune : Delete only acknowledged events beyond retention and expired idempotency records} {--retention-days=1 : Days to retain acknowledged events}';

    protected $description = 'Inspect realtime backlog and clean acknowledged records';

    public function handle(): int
    {
        $days = filter_var($this->option('retention-days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
        if ($days === false) {
            $this->error('Retention must be between 1 and 365 days.');

            return self::FAILURE;
        }
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
            $cutoff = now()->subDays($days);
            do {
                $ids = $db->table('realtime_outbox')->where('delivered_at', '<', $cutoff)->limit(1000)->pluck('id');
                $db->table('realtime_outbox')->whereIn('id', $ids)->where('delivered_at', '<', $cutoff)->delete();
            } while ($ids->count() === 1000);
            do {
                $ids = $db->table('report_idempotency')->where('expires_at', '<', now())->limit(1000)->pluck('scope');
                $db->table('report_idempotency')->whereIn('scope', $ids)->where('expires_at', '<', now())->delete();
            } while ($ids->count() === 1000);
        }

        return self::SUCCESS;
    }
}
