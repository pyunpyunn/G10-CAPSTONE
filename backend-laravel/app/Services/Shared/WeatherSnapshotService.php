<?php

namespace App\Services\Shared;

use App\Presenters\WeatherSnapshotPresenter;

use App\Models\DisasterEvent;
use App\Models\WeatherLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Support\RequestSchema as Schema;
use RuntimeException;

class WeatherSnapshotService
{
    private BarangayProfileService $barangayProfile;
    private WeatherSnapshotPresenter $presenter;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
        $this->presenter = app(WeatherSnapshotPresenter::class);
    }

    public function pageData(?string $eventId): array
    {
        $activeEvent = $this->getActiveEvent();
        $logs = $this->getWeatherLogs(null);
        $latest = $logs[0] ?? null;

        return [
            'active_event' => $activeEvent ? $this->presenter->formatEvent($activeEvent, $this->latestAdvisory((string) $activeEvent->event_id)) : null,
            'latest_snapshot' => $latest,
            'logs' => $logs,
            'location' => $this->barangayProfile->weatherLocation(),
            'source_links' => $this->presenter->sourceLinks(),
            'has_weather_table' => Schema::hasTable('weather_logs'),
            'auto_refresh' => [
                'source' => 'Open-Meteo Forecast API',
                'frequency' => 'Every 3 hours through Laravel Scheduler',
                'official_warning_source' => 'DOST-PAGASA',
                'pagasa_token_status' => env('PAGASA_TENDAY_TOKEN') ? 'configured' : 'not_configured',
            ],
        ];
    }

    public function getWeatherLogs(?string $eventId): array
    {
        if (! Schema::hasTable('weather_logs')) {
            return [];
        }

        $query = WeatherLog::query();

        if ($eventId) {
            $query->where('disaster_id', $eventId);
        }

        return $query
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (object $log): array => $this->presenter->formatWeatherLog($log))
            ->values()
            ->all();
    }

    public function saveLatestSnapshot(?string $eventId): array
    {
        if (! Schema::hasTable('weather_logs')) {
            return [
                'status' => 409,
                'saved' => false,
                'message' => 'The weather_logs table is not ready yet. Ask the DB member to approve or apply the weather log table first.',
                'data' => $this->pageData($eventId),
            ];
        }

        $weather = $this->fetchOpenMeteo();
        $current = $weather['current'] ?? [];
        $summary = $this->weatherSummary($weather);
        $observedAt = $this->presenter->observedAt($current['time'] ?? null);

        WeatherLog::query()->create([
            'disaster_id' => $eventId,
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => $this->openMeteoUrl(),
            'condition_name' => $summary['condition_name'],
            'temperature' => $current['temperature_2m'] ?? null,
            'rainfall_mm' => $current['precipitation'] ?? null,
            'wind_speed' => $current['wind_speed_10m'] ?? null,
            'wind_direction' => $this->presenter->directionLabel($current['wind_direction_10m'] ?? null),
            'humidity' => $current['relative_humidity_2m'] ?? null,
            'advisory_title' => $summary['advisory_title'],
            'advisory_text' => $summary['advisory_text'],
            'raw_payload' => $weather,
            'observed_at' => $observedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'status' => 201,
            'saved' => true,
            'message' => $eventId ? 'Weather snapshot saved for the active disaster event.' : 'Weather monitoring snapshot saved independently of disaster events.',
            'data' => $this->pageData($eventId),
        ];
    }

    public function getActiveEvent(): ?object
    {
        $query = DisasterEvent::query()
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'disaster_events.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_events.severity_level_id')
            ->whereNull('disaster_events.ended_at');

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('disaster_events.deleted_at');
        }

        return $query
            ->orderByDesc('disaster_events.started_at')
            ->select([
                'disaster_events.event_id',
                'disaster_events.name',
                'disaster_events.started_at',
                'disaster_events.ended_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    public function findEvent(string $eventId): ?object
    {
        $query = DisasterEvent::query()
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'disaster_events.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'disaster_events.severity_level_id')
            ->where('disaster_events.event_id', $eventId);

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('disaster_events.deleted_at');
        }

        return $query
            ->select([
                'disaster_events.event_id',
                'disaster_events.name',
                'disaster_events.started_at',
                'disaster_events.ended_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function fetchOpenMeteo(): array
    {
        $request = Http::timeout(15)->retry(1, 300);

        if (! $this->shouldVerifySsl()) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get($this->openMeteoUrl(), [
                'latitude' => $this->latitude(),
                'longitude' => $this->longitude(),
                'timezone' => 'Asia/Manila',
                'current' => implode(',', [
                    'temperature_2m',
                    'relative_humidity_2m',
                    'apparent_temperature',
                    'precipitation',
                    'weather_code',
                    'wind_speed_10m',
                    'wind_direction_10m',
                    'wind_gusts_10m',
                ]),
                'hourly' => implode(',', [
                    'precipitation_probability',
                    'precipitation',
                    'weather_code',
                    'wind_speed_10m',
                    'wind_gusts_10m',
                ]),
                'daily' => implode(',', [
                    'weather_code',
                    'temperature_2m_max',
                    'temperature_2m_min',
                    'precipitation_sum',
                    'precipitation_probability_max',
                    'wind_speed_10m_max',
                    'wind_gusts_10m_max',
                ]),
                'forecast_days' => 3,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Open-Meteo weather data cannot be fetched right now. The latest saved weather log is still available.');
        }

        return $response->json();
    }

    private function latestAdvisory(string $eventId): ?object
    {
        if (! Schema::hasTable('disaster_broadcasts')) return null;
        return DB::table('disaster_broadcasts')->where('disaster_id', $eventId)
            ->orderByDesc('sent_at')->orderByDesc('created_at')
            ->first(['broadcast_title', 'message', 'sent_at']);
    }

    private function openMeteoUrl(): string { return 'https://api.open-meteo.com/v1/forecast'; }
    private function latitude(): float { return (float) $this->barangayProfile->weatherLocation()['latitude']; }
    private function longitude(): float { return (float) $this->barangayProfile->weatherLocation()['longitude']; }
    private function shouldVerifySsl(): bool { return filter_var(env('WEATHER_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN); }

    private function weatherSummary(array $weather): array
    {
        $current = $weather['current'] ?? [];
        $hourly = $weather['hourly'] ?? [];
        $daily = $weather['daily'] ?? [];
        $code = (int) ($current['weather_code'] ?? 0);
        $condition = $this->presenter->conditionName($code);
        $rainNow = (float) ($current['precipitation'] ?? 0);
        $windNow = (float) ($current['wind_speed_10m'] ?? 0);
        $gustNow = (float) ($current['wind_gusts_10m'] ?? 0);
        $rainChance = $this->presenter->maxValue($hourly['precipitation_probability'] ?? []);
        $dailyRain = $this->presenter->maxValue($daily['precipitation_sum'] ?? []);
        $dailyGust = $this->presenter->maxValue($daily['wind_gusts_10m_max'] ?? []);
        $riskText = [];

        if ($rainChance >= 80 || $dailyRain >= 20 || $rainNow >= 7.5) {
            $riskText[] = 'High rainfall potential. Monitor flood-prone and low-lying puroks.';
        }

        if ($windNow >= 39 || $gustNow >= 50 || $dailyGust >= 50) {
            $riskText[] = 'Strong wind or gust watch. Check official PAGASA wind advisories before broadcasting.';
        }

        if ($code >= 95) {
            $riskText[] = 'Thunderstorm signal from forecast model. Confirm local thunderstorm/rainfall advisories.';
        }

        if (empty($riskText)) {
            $riskText[] = 'No severe model trigger detected. Continue official source monitoring.';
        }

        return [
            'condition_name' => $condition,
            'advisory_title' => 'Weather monitoring snapshot',
            'advisory_text' => implode(' ', $riskText).' Confirm official warnings through PAGASA before broadcasting.',
        ];
    }

}

