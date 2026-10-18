<?php

namespace App\Console\Commands;

use App\Services\Shared\WeatherSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Local alternative when a full Laravel Scheduler is not running. Fetches weather directly. */
class WorkWeatherUpdates extends Command
{
    protected $signature = 'weather:work {--once : Run one weather maintenance cycle}';
    protected $description = 'Capture weather directly every three hours without a queue worker';

    public function handle(WeatherSnapshotService $weather): int
    {
        do {
            $lastCapture = DB::table('weather_logs')->max('created_at');
            if (! $lastCapture || \Carbon\Carbon::parse($lastCapture)->lte(now()->subHours(3))) {
                try {
                    $result = $weather->saveLatestSnapshot(null);
                    $this->line($result['message']);
                } catch (\Throwable $failure) {
                    report($failure); $this->error('Weather update failed; the latest saved snapshot is retained.');
                }
            }
            if ($this->option('once')) break;
            sleep(30);
        } while (true);
        return self::SUCCESS;
    }
}
