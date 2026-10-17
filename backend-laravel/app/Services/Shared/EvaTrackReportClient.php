<?php

namespace App\Services\Shared;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class EvaTrackReportClient
{
    public function __construct(
        private readonly string $configPrefix = 'evatrack',
        private readonly string $source = 'EvaTrack',
        private readonly string $filePrefix = 'evatrack',
    ) {}

    private function setting(string $key): mixed
    {
        return config($this->configPrefix.'.reports_'.$key);
    }

    public function configuration(): array
    {
        return [
            'configured' => $this->validOrigin() && trim((string) $this->setting('api_key')) !== '',
            'source' => $this->source,
            'report_types' => ['demographics', 'dromic', 'utilization', 'vulnerable', 'general'],
            'formats' => ['pdf', 'csv', 'xlsx'],
            'supports_date_range' => true,
        ];
    }

    private function validOrigin(): bool
    {
        $url = (string) $this->setting('url');
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
            && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT);
    }

    public function generate(array $payload): JsonResponse
    {
        if (! $this->configuration()['configured']) {
            return response()->json(['message' => $this->source.' reports are not configured. Set the backend API URL and API key.'], 503);
        }

        if (($payload['format'] ?? 'pdf') === 'xlsx') return $this->excel($payload);

        try {
            $response = Http::acceptJson()->asJson()
                ->withHeaders(['X-API-KEY' => $this->setting('api_key')])
                ->timeout(max(1, (int) $this->setting('timeout')))
                ->withoutRedirecting()
                ->post(rtrim((string) $this->setting('url'), '/').'/api/v1/external/reports/generate', $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            return response()->json(['message' => $this->source.' could not be reached. Check its server address and availability.'], 503);
        }

        if (! $response->successful()) {
            $message = match ($response->status()) {
                401, 403 => $this->source.' rejected the configured API key.',
                422 => $this->source.' rejected the report parameters.',
                429 => $this->source.' report rate limit reached. Try again shortly.',
                default => $this->source.' report generation failed. Try again or check the server.',
            };
            $result = response()->json(['message' => $message, 'upstream_status' => $response->status()], $response->status() === 429 ? 429 : 502);
            if ($response->status() === 429 && ctype_digit((string) $response->header('Retry-After'))) {
                $result->header('Retry-After', $response->header('Retry-After'));
            }
            return $result;
        }

        $data = $response->json();
        $url = $data['download_url'] ?? null;
        $filename = $data['file_name'] ?? null;
        if (! is_array($data) || ($data['status'] ?? '') !== 'success'
            || ! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)
            || ! is_string($filename) || ! preg_match('/^[a-zA-Z0-9_-]+\.(pdf|csv)$/D', $filename)
            || ! is_string($data['expires_at'] ?? null)) {
            return response()->json(['message' => $this->source.' returned an invalid report response.'], 502);
        }

        return response()->json([
            'status' => 'success', 'source' => $this->source,
            'report_type' => $payload['report_type'], 'format' => $payload['format'] ?? 'pdf',
            'file_name' => $filename, 'download_url' => $url,
            'expires_at' => $data['expires_at'],
            'expires_in_minutes' => (int) ($data['expires_in_minutes'] ?? 30),
        ])->header('Cache-Control', 'no-store');
    }
    private function excel(array $payload): JsonResponse
    {
        // The external report API supplies PDF/CSV; build Excel from its real CSV through our existing writer.
        $generated = $this->generate(array_replace($payload, ['format' => 'csv']));
        if ($generated->getStatusCode() !== 200) return $generated;
        $report = $generated->getData(true);
        $url = $report['download_url'];
        $origin = fn (string $value): array => [strtolower((string) parse_url($value, PHP_URL_SCHEME)), strtolower((string) parse_url($value, PHP_URL_HOST)), parse_url($value, PHP_URL_PORT) ?: (parse_url($value, PHP_URL_SCHEME) === 'https' ? 443 : 80)];
        if ($origin($url) !== $origin((string) $this->setting('url'))) {
            return response()->json(['message' => $this->source.' returned a download address outside its configured server.'], 502);
        }
        $handle = null;
        try {
            if (\Carbon\Carbon::parse($report['expires_at'])->isPast()) return response()->json(['message' => 'The '.$this->source.' download link has expired. Generate the report again.'], 502);
            $response = Http::timeout(max(1, (int) $this->setting('timeout')))->withoutRedirecting()->withOptions(['stream' => true])->get($url);
            if (! $response->successful()) return response()->json(['message' => $this->source.' report could not be downloaded for Excel export.'], 502);
            $handle = fopen('php://temp/maxmemory:2097152', 'w+');
            $stream = $response->toPsrResponse()->getBody();
            $size = 0;
            while (! $stream->eof()) {
                $chunk = $stream->read(8192); $size += strlen($chunk);
                if ($size > 20 * 1024 * 1024) return response()->json(['message' => 'The '.$this->source.' report is too large for Excel conversion. Export CSV instead.'], 422);
                fwrite($handle, $chunk);
            }
            rewind($handle);
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! $headers || $headers === [null]) return response()->json(['message' => $this->source.' returned an empty CSV report.'], 502);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '');
            $rows = (function () use ($handle) { while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) yield $row; })();
            $file = app(\App\Services\Web\ReportFileService::class)->generate($this->filePrefix.'-'.$payload['report_type'], 'xlsx', $headers, $rows);
            return response()->json(array_merge($file, ['source' => $this->source, 'report_type' => $payload['report_type'], 'format' => 'xlsx']))->header('Cache-Control', 'no-store');
        } catch (\Throwable $failure) {
            report($failure);
            return response()->json(['message' => $this->source.' Excel export could not be generated. Try again or export CSV.'], 502);
        } finally { if (is_resource($handle)) fclose($handle); }
    }
}
