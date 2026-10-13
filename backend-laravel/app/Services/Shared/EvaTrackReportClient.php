<?php

namespace App\Services\Shared;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class EvaTrackReportClient
{
    public function configuration(): array
    {
        return [
            'configured' => $this->validOrigin() && trim((string) config('evatrack.reports_api_key')) !== '',
            'source' => 'EvaTrack',
            'report_types' => ['demographics', 'dromic', 'utilization', 'vulnerable', 'general'],
            'formats' => ['pdf', 'csv'],
        ];
    }

    private function validOrigin(): bool
    {
        $url = (string) config('evatrack.reports_url');
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
            && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT);
    }

    public function generate(array $payload): JsonResponse
    {
        if (! $this->configuration()['configured']) {
            return response()->json(['message' => 'EvaTrack reports are not configured. Set EVATRACK_REPORTS_URL and EVATRACK_REPORTS_API_KEY on the backend.'], 503);
        }

        try {
            $response = Http::acceptJson()->asJson()
                ->withHeaders(['X-API-KEY' => config('evatrack.reports_api_key')])
                ->timeout(max(1, (int) config('evatrack.timeout')))
                ->withoutRedirecting()
                ->post(rtrim(config('evatrack.reports_url'), '/').'/api/v1/external/reports/generate', $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            return response()->json(['message' => 'EvaTrack could not be reached. Check its server address and availability.'], 503);
        }

        if (! $response->successful()) {
            $message = match ($response->status()) {
                401, 403 => 'EvaTrack rejected the configured API key. Check EVATRACK_REPORTS_API_KEY.',
                422 => 'EvaTrack rejected the report parameters.',
                429 => 'EvaTrack report rate limit reached. Try again shortly.',
                default => 'EvaTrack report generation failed. Try again or check the EvaTrack server.',
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
            return response()->json(['message' => 'EvaTrack returned an invalid report response.'], 502);
        }

        return response()->json([
            'status' => 'success', 'source' => 'EvaTrack',
            'report_type' => $payload['report_type'], 'format' => $payload['format'] ?? 'pdf',
            'file_name' => $filename, 'download_url' => $url,
            'expires_at' => $data['expires_at'],
            'expires_in_minutes' => (int) ($data['expires_in_minutes'] ?? 30),
        ])->header('Cache-Control', 'no-store');
    }
}
