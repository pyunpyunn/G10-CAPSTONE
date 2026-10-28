<?php

namespace App\Console\Commands;

use App\Services\Shared\SharedResqDataMerge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeLocalResqData extends Command
{
    protected $signature = 'resq:merge-local-data {--source=resq_local} {--target=shared_resq} {--apply} {--expect-database=}';
    protected $description = 'Preview or insert verified local records into the existing shared schema without replacing shared data';

    public function handle(SharedResqDataMerge $merge): int
    {
        $source = DB::connection($this->option('source'));
        $target = DB::connection($this->option('target'));
        $write = (bool) $this->option('apply');
        if ($source->getDatabaseName() === $target->getDatabaseName() && $source->getConfig('host') === $target->getConfig('host')) {
            $this->error('Source and target must be different databases.'); return self::FAILURE;
        }
        if ($write && $target->getDatabaseName() !== $this->option('expect-database')) {
            $this->error('Apply requires --expect-database to match the target database exactly.'); return self::FAILURE;
        }
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($this->option('target'));
        $dispatcher = $target->getEventDispatcher();
        // Historical imports must not dispatch live notification or realtime events.
        $target->unsetEventDispatcher();
        try {
            $this->line(json_encode(['database' => $target->getDatabaseName(), 'applied' => $write,
                'report' => $merge->run($source, $write)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } finally {
            if ($dispatcher) $target->setEventDispatcher($dispatcher);
            DB::setDefaultConnection($previous);
        }
        return self::SUCCESS;
    }
}
