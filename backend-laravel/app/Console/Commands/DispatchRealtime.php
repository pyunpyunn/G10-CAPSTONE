<?php

namespace App\Console\Commands;

use App\Services\Shared\RealtimeOutbox;
use Illuminate\Console\Command;
use Throwable;

class DispatchRealtime extends Command
{
    protected $signature = 'realtime:dispatch {--once : Dispatch at most one batch} {--inline : Deliver directly for local development}';

    protected $description = 'Relay durable outbox changes to the realtime queue';

    public function handle(RealtimeOutbox $outbox): int
    {
        if (! config('realtime.durable')) {
            $this->error('Enable REALTIME_DURABLE after running the realtime migrations.');

            return self::FAILURE;
        }
        do {
            try {
                if ($this->option('inline')) {
                    $token = $outbox->claim();
                    if ($token !== null) {
                        try {
                            $outbox->deliver($token);
                        } catch (Throwable $exception) {
                            $outbox->release($token, 5);
                            throw $exception;
                        }
                    }
                } else {
                    $outbox->dispatchBatch();
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Realtime relay failed; persisted changes will be retried.');
                if ($this->option('once')) {
                    return self::FAILURE;
                }
                usleep(1_000_000);
            }
            // Bound batches to four per second instead of emitting per-write events.
            if (! $this->option('once')) {
                usleep(250_000);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
