<?php

use App\Services\WeatherSnapshotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('weather:refresh {--active-event : Skip refresh when there is no active disaster event}', function () {
    $weather = app(WeatherSnapshotService::class);
    $activeEvent = $weather->getActiveEvent();

    if ($this->option('active-event') && ! $activeEvent) {
        $this->info('No active disaster event. Weather refresh was skipped.');

        return 0;
    }

    try {
        $result = $weather->saveLatestSnapshot($activeEvent?->event_id);
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info($result['message']);

    return 0;
})->purpose('Fetch Open-Meteo weather data and save it to weather_logs');

Schedule::command('weather:refresh')
    ->everyThreeHours()
    ->withoutOverlapping();
