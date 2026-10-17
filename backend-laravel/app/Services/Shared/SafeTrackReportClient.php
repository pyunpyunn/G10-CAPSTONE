<?php

namespace App\Services\Shared;

use App\Services\Web\ReportFileService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SafeTrackReportClient
{
    private const REPORT_TYPES = [
        'households', 'vulnerable_households', 'household_detail',
        'members', 'pwd_members', 'senior_members', 'pregnant_members', 'sitios',
    ];

    public function configuration(): array
    {
        return [
            'configured' => $this->validOrigin() && trim((string) config('safetrack.api_key')) !== '',
            'source' => 'SafeTrack',
            'report_types' => self::REPORT_TYPES,
            'formats' => ['pdf', 'csv', 'xlsx'],
            'supports_date_range' => false,
            'filters' => ['sitio_id', 'household_id', 'search'],
        ];
    }

    private function validOrigin(): bool
    {
        $url = (string) config('safetrack.base_url');
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
            && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT);
    }

    public function generate(array $payload): JsonResponse
    {
        if (! $this->configuration()['configured']) {
            return response()->json(['message' => 'SafeTrack is not configured. Set SAFETRACK_API_URL and SAFETRACK_API_KEY on the backend.'], 503);
        }

        try {
            [$headers, $rows] = $this->reportRows($payload);
            $report = app(ReportFileService::class)->generate(
                'safetrack-'.$payload['report_type'],
                $payload['format'] ?? 'pdf',
                $headers,
                $rows,
            );

            return response()->json(array_merge($report, [
                'source' => 'SafeTrack',
                'record_count' => count($rows),
            ]))->header('Cache-Control', 'no-store');
        } catch (ConnectionException) {
            return response()->json(['message' => 'SafeTrack could not be reached. Check its server address and availability.'], 503);
        } catch (SafeTrackApiException $error) {
            $status = $error->upstreamStatus === 429 ? 429 : 502;
            $message = match ($error->upstreamStatus) {
                401, 403 => 'SafeTrack rejected the configured API key. Check SAFETRACK_API_KEY.',
                404 => 'SafeTrack does not recognize the configured API path. Check SAFETRACK_API_PREFIX.',
                429 => 'SafeTrack report rate limit reached. Try again shortly.',
                default => 'SafeTrack returned an unsuccessful or invalid response.',
            };
            return response()->json(['message' => $message, 'upstream_status' => $error->upstreamStatus], $status);
        }
    }

    private function reportRows(array $payload): array
    {
        $type = $payload['report_type'];
        $siteFilter = isset($payload['sitio_id']) ? ['sitio_id' => $payload['sitio_id']] : [];

        if ($type === 'sitios') {
            $data = $this->get('sitios');
            $sitios = $data['data'] ?? null;
            if (! is_array($sitios)) throw new SafeTrackApiException(502);
            $rows = array_map(fn (array $sitio): array => [
                $sitio['sitio_id'] ?? '',
                $sitio['sitio_name'] ?? '',
                $sitio['purok_count'] ?? count($sitio['puroks'] ?? []),
                implode(', ', array_filter(array_map(fn (array $purok): string => (string) ($purok['purok_name'] ?? ''), $sitio['puroks'] ?? []))),
            ], array_filter($sitios, 'is_array'));
            return [['Sitio ID', 'Sitio', 'Purok Count', 'Puroks'], $rows];
        }

        if ($type === 'household_detail') {
            $data = $this->get('households/'.rawurlencode((string) $payload['household_id']));
            $household = $data['data'] ?? null;
            if (! is_array($household)) throw new SafeTrackApiException(502);
            $members = $household['members'] ?? [];
            $members = is_array($members) && $members ? $members : [[]];
            $rows = array_map(fn (array $member): array => $this->householdDetailRow($household, $member), array_filter($members, 'is_array'));
            return [[
                'Household ID', 'Household', 'Head of Household', 'Sitio', 'Street', 'Member ID',
                'Resident', 'Age', 'Gender', 'Relation', 'PWD', 'Senior', 'Pregnant',
            ], $rows];
        }

        if (in_array($type, ['households', 'vulnerable_households'], true)) {
            $query = $siteFilter;
            if (isset($payload['search'])) $query['search'] = $payload['search'];
            if ($type === 'vulnerable_households') $query['vulnerable'] = 1;
            $households = $this->allPages('households', $query);
            $rows = array_map(fn (array $household): array => $this->householdRow($household), $households);
            return [[
                'Household ID', 'Household', 'Head of Household', 'Sitio', 'Street', 'Member Count',
                'Seniors', 'PWD', 'Pregnant', 'Vulnerable',
            ], $rows];
        }

        $query = $siteFilter;
        if (isset($payload['household_id'])) $query['household_id'] = $payload['household_id'];
        $memberFilter = match ($type) {
            'pwd_members' => 'is_pwd',
            'senior_members' => 'is_senior',
            'pregnant_members' => 'is_pregnant',
            default => null,
        };
        if ($memberFilter) $query[$memberFilter] = 1;
        $members = $this->allPages('members', $query);
        $rows = array_map(fn (array $member): array => $this->memberRow($member), $members);

        return [[
            'Member ID', 'Household ID', 'Resident', 'Birth Date', 'Age', 'Gender', 'Relation',
            'Sitio', 'PWD', 'Senior', 'Pregnant',
        ], $rows];
    }

    private function allPages(string $endpoint, array $query): array
    {
        $records = [];
        $page = 1;
        $maxPages = max(1, min(20, (int) config('safetrack.max_pages', 20)));

        do {
            $response = $this->get($endpoint, array_merge($query, ['per_page' => 100, 'page' => $page]));
            $data = $response['data'] ?? null;
            if (! is_array($data)) throw new SafeTrackApiException(502);
            foreach ($data as $record) if (is_array($record)) $records[] = $record;

            $lastPage = max(1, (int) ($response['meta']['last_page'] ?? 1));
            if ($page >= $lastPage) break;
            if ($page >= $maxPages) {
                throw ValidationException::withMessages([
                    'report_type' => 'SafeTrack returned more pages than this report can safely retrieve. Narrow the report filters.',
                ]);
            }
            $page++;
        } while (true);

        return $records;
    }

    private function get(string $endpoint, array $query = []): array
    {
        $prefix = trim((string) config('safetrack.api_prefix', 'api/external'), '/');
        $url = rtrim((string) config('safetrack.base_url'), '/').'/'.$prefix.'/'.$endpoint;
        $response = Http::acceptJson()
            ->withHeaders(['X-API-KEY' => config('safetrack.api_key')])
            ->timeout(max(1, min(60, (int) config('safetrack.timeout', 20))))
            ->withoutRedirecting()
            ->get($url, $query);

        if (! $response->successful()) throw new SafeTrackApiException($response->status());
        $data = $response->json();
        if (! is_array($data) || (($data['status'] ?? 'success') !== 'success')) throw new SafeTrackApiException(502);
        return $data;
    }

    private function householdRow(array $household): array
    {
        $location = $household['location'] ?? [];
        $vulnerability = $household['vulnerability_summary'] ?? [];
        return [
            $household['household_id'] ?? $household['household_code'] ?? '',
            $household['household_name'] ?? '',
            $household['head_of_household'] ?? '',
            $location['sitio_name'] ?? '',
            $location['street'] ?? '',
            $household['member_count'] ?? count($household['members'] ?? []),
            $vulnerability['seniors_count'] ?? 0,
            $vulnerability['pwd_count'] ?? 0,
            $vulnerability['pregnant_count'] ?? 0,
            ! empty($vulnerability['is_vulnerable']) ? 'Yes' : 'No',
        ];
    }

    private function householdDetailRow(array $household, array $member): array
    {
        $location = $household['location'] ?? [];
        return [
            $household['household_id'] ?? $household['household_code'] ?? '',
            $household['household_name'] ?? '',
            $household['head_of_household'] ?? '',
            $location['sitio_name'] ?? '',
            $location['street'] ?? '',
            $member['member_id'] ?? '',
            $member['full_name'] ?? trim(implode(' ', array_filter([$member['first_name'] ?? '', $member['middle_name'] ?? '', $member['last_name'] ?? '']))),
            $member['age'] ?? '',
            $member['gender'] ?? '',
            $member['relation'] ?? '',
            ! empty($member['is_pwd']) ? 'Yes' : 'No',
            ! empty($member['is_senior']) ? 'Yes' : 'No',
            ! empty($member['is_pregnant']) ? 'Yes' : 'No',
        ];
    }

    private function memberRow(array $member): array
    {
        $location = $member['location'] ?? [];
        return [
            $member['member_id'] ?? '',
            $member['household_id'] ?? '',
            $member['full_name'] ?? trim(implode(' ', array_filter([$member['first_name'] ?? '', $member['middle_name'] ?? '', $member['last_name'] ?? '']))),
            $member['birth_date'] ?? '',
            $member['age'] ?? '',
            $member['gender'] ?? '',
            $member['relation'] ?? '',
            $location['sitio_name'] ?? '',
            ! empty($member['is_pwd']) ? 'Yes' : 'No',
            ! empty($member['is_senior']) ? 'Yes' : 'No',
            ! empty($member['is_pregnant']) ? 'Yes' : 'No',
        ];
    }
}