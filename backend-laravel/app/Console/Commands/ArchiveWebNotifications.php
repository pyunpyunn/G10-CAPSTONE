<?php

namespace App\Console\Commands;

use App\Queries\NotificationQuery;
use App\Services\Web\BroadcastProviderHistory;
use App\Services\Web\NotificationArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ArchiveWebNotifications extends Command
{
    protected $signature = 'notifications:archive {--recover-onesignal : Read OneSignal history and operational logs} {--dry-run : Report without database changes}';
    protected $description = 'Archive complete web notices; pause incomplete records without fabricating missing values';

    public function handle(NotificationQuery $query, NotificationArchive $archive, BroadcastProviderHistory $history): int
    {
        if (! $archive->available()) {
            $this->error('Run the notification archive migration first.');
            return self::FAILURE;
        }
        $report = ['generated_at' => now()->toIso8601String(), 'strict_nonempty_fields' => true];
        if ($this->option('recover-onesignal')) $report['provider_recovery'] = $history->recover(! $this->option('dry-run'));
        $sources = $query->sourceRecords(true);
        // Validation-only mode must not write archive rows.
        $report['archive'] = $archive->sync($sources, ! $this->option('dry-run'));
        $path = storage_path('app/private/notification-backfill-report.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info(($this->option('dry-run') ? 'Eligible (not written): ' : 'Stored: ').$report['archive']['stored'].'; paused: '.count($report['archive']['paused']).'.');
        if (isset($report['provider_recovery'])) {
            $recovered = array_filter($report['provider_recovery']['broadcasts'], fn ($row) => count($row['onesignal_ids']) > 0);
            $this->info('Broadcasts with recovered IDs: '.count($recovered).'.');
            foreach ($report['provider_recovery']['warnings'] as $warning) $this->warn($warning);
        }
        $this->line('Report: '.$path);
        if ($report['archive']['paused']) $this->warn('Incomplete records remain in their source tables; original feed and delivery are preserved.');
        return self::SUCCESS;
    }
}
