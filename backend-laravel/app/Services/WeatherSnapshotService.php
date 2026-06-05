<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class WeatherSnapshotService
{
    private BarangayProfileService $barangayProfile;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
    }

    public function pageData(?string $eventId): array
    {
        $activeEvent = $this->getActiveEvent();
        $logs = $this->getWeatherLogs($eventId);
        $latest = $logs[0] ?? null;

        return [
            'active_event' => $activeEvent ? $this->formatEvent($activeEvent) : null,
            'latest_snapshot' => $latest,
            'logs' => $logs,
            'location' => $this->barangayProfile->weatherLocation(),
            'source_links' => $this->sourceLinks(),
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

        $query = DB::table('weather_logs');

        if ($eventId) {
            $query->where('disaster_id', $eventId);
        } else {
            $query->whereNull('disaster_id');
        }

        return $query
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (object $log): array => $this->formatWeatherLog($log))
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
        $observedAt = $this->observedAt($current['time'] ?? null);

        if ($this->snapshotAlreadySaved($eventId, $observedAt)) {
            return [
                'status' => 200,
                'saved' => false,
                'message' => 'Latest Open-Meteo weather snapshot is already saved.',
                'data' => $this->pageData($eventId),
            ];
        }

        DB::table('weather_logs')->insert([
            'disaster_id' => $eventId,
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => $this->openMeteoUrl(),
            'condition_name' => $summary['condition_name'],
            'temperature' => $current['temperature_2m'] ?? null,
            'rainfall_mm' => $current['precipitation'] ?? null,
            'wind_speed' => $current['wind_speed_10m'] ?? null,
            'wind_direction' => $this->directionLabel($current['wind_direction_10m'] ?? null),
            'humidity' => $current['relative_humidity_2m'] ?? null,
            'advisory_title' => $summary['advisory_title'],
            'advisory_text' => $summary['advisory_text'],
            'raw_payload' => json_encode($weather, JSON_UNESCAPED_SLASHES),
            'observed_at' => $observedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'status' => 201,
            'saved' => true,
            'message' => $eventId
                ? 'Weather snapshot saved for the active disaster event.'
                : 'Weather monitoring snapshot saved. It is not linked to a disaster event yet.',
            'data' => $this->pageData($eventId),
        ];
    }

    public function getActiveEvent(): ?object
    {
        $query = DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.ended_at');

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }

        return $query
            ->orderByDesc('de.started_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'de.ended_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    public function findEvent(string $eventId): ?object
    {
        $query = DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->where('de.event_id', $eventId);

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('de.deleted_at');
        }

        return $query
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'de.ended_at',
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

    private function weatherSummary(array $weather): array
    {
        $current = $weather['current'] ?? [];
        $hourly = $weather['hourly'] ?? [];
        $daily = $weather['daily'] ?? [];
        $code = (int) ($current['weather_code'] ?? 0);
        $condition = $this->conditionName($code);
        $rainNow = (float) ($current['precipitation'] ?? 0);
        $windNow = (float) ($current['wind_speed_10m'] ?? 0);
        $gustNow = (float) ($current['wind_gusts_10m'] ?? 0);
        $rainChance = $this->maxValue($hourly['precipitation_probability'] ?? []);
        $dailyRain = $this->maxValue($daily['precipitation_sum'] ?? []);
        $dailyGust = $this->maxValue($daily['wind_gusts_10m_max'] ?? []);
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

    private function formatWeatherLog(object $log): array
    {
        $payload = json_decode($log->raw_payload ?? '', true);
        $current = $payload['current'] ?? [];
        $daily = $this->dailyForecast($payload['daily'] ?? []);
        $risk = $this->riskLevel($log, $current, $daily);

        return [
            'weather_log_id' => $log->weather_log_id,
            'disaster_id' => $log->disaster_id,
            'source_name' => $log->source_name,
            'source_url' => $log->source_url,
            'condition_name' => $log->condition_name ?? 'Weather update',
            'condition_key' => $this->conditionKey($log->condition_name),
            'temperature' => $this->numberOrNull($log->temperature),
            'apparent_temperature' => $this->numberOrNull($current['apparent_temperature'] ?? null),
            'humidity' => $log->humidity,
            'rainfall_mm' => $this->numberOrNull($log->rainfall_mm),
            'wind_speed' => $this->numberOrNull($log->wind_speed),
            'wind_direction' => $log->wind_direction,
            'wind_gusts' => $this->numberOrNull($current['wind_gusts_10m'] ?? null),
            'advisory_title' => $log->advisory_title,
            'advisory_text' => $log->advisory_text,
            'risk_level' => $risk['level'],
            'risk_tags' => $risk['tags'],
            'daily_forecast' => $daily,
            'observed_at' => $this->formatDateTime($log->observed_at),
            'observed_time' => $this->formatTime($log->observed_at),
            'created_at' => $this->formatDateTime($log->created_at),
        ];
    }

    private function dailyForecast(array $daily): array
    {
        $days = $daily['time'] ?? [];

        return collect($days)
            ->take(3)
            ->map(fn (string $date, int $index): array => [
                'date' => Carbon::parse($date)->format('M d'),
                'condition_name' => $this->conditionName((int) ($daily['weather_code'][$index] ?? 0)),
                'rain_probability' => $daily['precipitation_probability_max'][$index] ?? null,
                'rainfall_sum' => $daily['precipitation_sum'][$index] ?? null,
                'wind_max' => $daily['wind_speed_10m_max'][$index] ?? null,
                'gust_max' => $daily['wind_gusts_10m_max'][$index] ?? null,
                'temp_max' => $daily['temperature_2m_max'][$index] ?? null,
                'temp_min' => $daily['temperature_2m_min'][$index] ?? null,
            ])
            ->values()
            ->all();
    }

    private function riskLevel(object $log, array $current, array $daily): array
    {
        $tags = [];
        $level = 'normal';
        $rainfall = (float) ($log->rainfall_mm ?? 0);
        $wind = (float) ($log->wind_speed ?? 0);
        $gust = (float) ($current['wind_gusts_10m'] ?? 0);
        $dailyRain = max(collect($daily)->pluck('rainfall_sum')->filter()->all() ?: [0]);
        $dailyRainChance = max(collect($daily)->pluck('rain_probability')->filter()->all() ?: [0]);

        if ($rainfall >= 7.5 || $dailyRain >= 20 || $dailyRainChance >= 80) {
            $tags[] = 'Heavy rain watch';
            $level = 'warning';
        }

        if ($wind >= 39 || $gust >= 50) {
            $tags[] = 'High wind watch';
            $level = 'critical';
        }

        if ($log->condition_name && str_contains(strtolower($log->condition_name), 'thunderstorm')) {
            $tags[] = 'Thunderstorm watch';
            $level = 'critical';
        }

        if (empty($tags)) {
            $tags[] = 'Monitoring';
        }

        return [
            'level' => $level,
            'tags' => $tags,
        ];
    }

    private function sourceLinks(): array
    {
        return [
            [
                'name' => 'PAGASA Daily Weather Forecast',
                'type' => 'official_weather',
                'url' => 'https://www.pagasa.dost.gov.ph/weather',
                'note' => 'Official public weather forecast and warning context. Confirm official warnings through PAGASA before broadcasting.',
            ],
            [
                'name' => 'PAGASA Tropical Cyclone Bulletin',
                'type' => 'official_cyclone',
                'url' => 'https://www.pagasa.dost.gov.ph/tropical-cyclone/severe-weather-bulletin',
                'note' => 'Use this to confirm named cyclones, PAR entry, wind signals, and official broadcast language.',
            ],
            [
                'name' => 'PAGASA Products and Services',
                'type' => 'official_reference',
                'url' => 'https://www.pagasa.dost.gov.ph/products-and-services',
                'note' => 'Reference for rainfall warning, thunderstorm alert, flood advisory, and other official PAGASA services.',
            ],
            [
                'name' => 'Open-Meteo Forecast API',
                'type' => 'numeric_forecast',
                'url' => 'https://open-meteo.com/en/docs',
                'note' => 'Structured numeric forecast used for automatic snapshots while waiting for official PAGASA Ten-Day API token access.',
            ],
        ];
    }

    private function snapshotAlreadySaved(?string $eventId, ?string $observedAt): bool
    {
        if (! $observedAt) {
            return false;
        }

        $query = DB::table('weather_logs')
            ->where('source_name', 'Open-Meteo Forecast API')
            ->where('observed_at', $observedAt);

        if ($eventId) {
            $query->where('disaster_id', $eventId);
        } else {
            $query->whereNull('disaster_id');
        }

        return $query
            ->limit(5)
            ->get(['raw_payload'])
            ->contains(function (object $row): bool {
                $payload = json_decode($row->raw_payload ?? '', true);

                if (! is_array($payload)) {
                    return false;
                }

                $savedLatitude = (float) ($payload['latitude'] ?? 0);
                $savedLongitude = (float) ($payload['longitude'] ?? 0);

                return abs($savedLatitude - $this->latitude()) < 0.001
                    && abs($savedLongitude - $this->longitude()) < 0.001;
            });
    }

    private function formatEvent(object $event): array
    {
        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type_name' => $event->type_name ?? 'Disaster event',
            'severity_key' => $event->severity_key ?? 'medium',
            'severity_label' => $event->severity_label ?? 'Unspecified',
            'started_at' => $this->formatDateTime($event->started_at),
            'started_time' => $this->formatTime($event->started_at),
            'ended_at' => $this->formatDateTime($event->ended_at),
            'status' => $event->ended_at ? 'closed' : 'active',
        ];
    }

    private function openMeteoUrl(): string
    {
        return 'https://api.open-meteo.com/v1/forecast';
    }

    private function latitude(): float
    {
        return (float) $this->barangayProfile->weatherLocation()['latitude'];
    }

    private function longitude(): float
    {
        return (float) $this->barangayProfile->weatherLocation()['longitude'];
    }

    private function shouldVerifySsl(): bool
    {
        return filter_var(env('WEATHER_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function observedAt(?string $time): ?string
    {
        if (! $time) {
            return now()->toDateTimeString();
        }

        return Carbon::parse($time, 'Asia/Manila')->toDateTimeString();
    }

    private function conditionName(int $code): string
    {
        return match (true) {
            $code === 0 => 'Sunny',
            in_array($code, [1, 2, 3], true) => 'Cloudy',
            in_array($code, [45, 48], true) => 'Cloudy',
            ($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82) => 'Rainy',
            $code >= 95 => 'Stormy',
            default => 'Weather update',
        };
    }

    private function conditionKey(?string $name): string
    {
        $value = strtolower((string) $name);

        return match (true) {
            str_contains($value, 'sun') => 'sunny',
            str_contains($value, 'rain') => 'rainy',
            str_contains($value, 'storm') => 'stormy',
            str_contains($value, 'cloud') => 'cloudy',
            default => 'monitoring',
        };
    }

    private function directionLabel(mixed $degrees): ?string
    {
        if ($degrees === null) {
            return null;
        }

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) round(((float) $degrees % 360) / 45) % 8;

        return $directions[$index];
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function maxValue(array $values): float
    {
        $numbers = collect($values)
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->values()
            ->all();

        return empty($numbers) ? 0 : max($numbers);
    }

    private function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    private function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }
}
