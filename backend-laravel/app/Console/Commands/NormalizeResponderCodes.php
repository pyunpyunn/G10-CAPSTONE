<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\NormalizeResponderCodes as Normalize;

class NormalizeResponderCodes extends Command
{
    protected $signature = 'rescuers:normalize-codes {--apply : Apply the audited code corrections} {--output= : CSV mapping output path}';
    protected $description = 'Audit or correct legacy field responder codes while retaining login aliases and internal IDs';

    public function handle(Normalize $normalize): int
    {
        $changes = $normalize->run((bool)$this->option('apply'));
        if ($path=$this->option('output')) {
            $stream=fopen($path,'w');
            if (!$stream) throw new \RuntimeException('Cannot open mapping output.');
            fwrite($stream,"\xEF\xBB\xBF");
            fputcsv($stream,['responder_id','name','team','old_code','new_code','login','legacy_login'],',','"','');
            foreach($changes as $row) fputcsv($stream,array_values($row),',','"','');
            fclose($stream);
        }
        $this->info(($this->option('apply') ? 'Corrected ' : 'Planned ').count($changes).' field responder codes. Login aliases and internal IDs retained.');
        if($this->option('apply')) app(\App\Services\Shared\RealtimeOutbox::class)->deliverPendingNow();
        return self::SUCCESS;
    }
}
