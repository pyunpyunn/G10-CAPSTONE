<?php

namespace Tests\Feature;

use App\Services\Shared\SafeTrackReportClient;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SafeTrackReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'safetrack.base_url' => 'http://safetrack.test',
            'safetrack.api_prefix' => 'api/external',
            'safetrack.api_key' => 'private-safetrack-test-key',
            'reports.disk' => 'local',
        ]);
        Http::preventStrayRequests();
    }

    public function test_household_reports_fetch_all_pages_and_keep_key_private(): void
    {
        Storage::fake('local');
        Http::fake(function (ClientRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            return Http::response([
                'status' => 'success',
                'meta' => ['current_page' => $page, 'last_page' => 2],
                'data' => [[
                    'household_id' => 'HH-2026-000'.$page,
                    'household_name' => 'Sample Household '.$page,
                    'head_of_household' => 'Resident '.$page,
                    'location' => ['sitio_name' => 'North', 'street' => 'Coastal Road'],
                    'member_count' => 2,
                    'vulnerability_summary' => ['seniors_count' => 1, 'pwd_count' => 0, 'pregnant_count' => 0, 'is_vulnerable' => true],
                ]],
            ]);
        });

        $response = app(SafeTrackReportClient::class)->generate([
            'report_type' => 'vulnerable_households', 'format' => 'csv', 'sitio_id' => 4,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $result = $response->getData(true);
        $this->assertSame(2, $result['record_count']);
        $this->assertSame('SafeTrack', $result['source']);
        $this->assertStringNotContainsString('private-safetrack-test-key', $response->getContent());
        $csv = Storage::disk('local')->get('reports/'.$result['file_name']);
        $this->assertStringContainsString('HH-2026-0001', $csv);
        $this->assertStringContainsString('HH-2026-0002', $csv);
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('X-API-KEY', 'private-safetrack-test-key')
            && str_contains($request->url(), '/api/external/households?')
            && $request['vulnerable'] === 1 && $request['sitio_id'] === 4 && $request['per_page'] === 100);
    }

    public function test_missing_configuration_blocks_requests_and_provider_never_exposes_key(): void
    {
        config(['safetrack.api_key' => '']);
        $this->assertFalse(app(SafeTrackReportClient::class)->configuration()['configured']);
        $response = app(SafeTrackReportClient::class)->generate(['report_type' => 'sitios', 'format' => 'csv']);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringNotContainsString('private-safetrack-test-key', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_safe_track_route_requires_authentication_and_household_detail_id(): void
    {
        $this->postJson('/api/v1/reports/safetrack/generate', [
            'report_type' => 'sitios', 'format' => 'csv',
        ])->assertUnauthorized();

        $this->withoutMiddleware();
        $this->postJson('/api/v1/reports/safetrack/generate', [
            'report_type' => 'household_detail', 'format' => 'csv',
        ])->assertUnprocessable()->assertJsonValidationErrors('household_id');

        Http::assertNothingSent();
    }

    public function test_bad_key_and_rate_limit_errors_do_not_expose_upstream_body(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'private key rejected'], 401)
            ->push(['message' => 'private upstream detail'], 429);

        $unauthorized = app(SafeTrackReportClient::class)->generate(['report_type' => 'sitios', 'format' => 'csv']);
        $limited = app(SafeTrackReportClient::class)->generate(['report_type' => 'sitios', 'format' => 'csv']);

        $this->assertSame(502, $unauthorized->getStatusCode());
        $this->assertSame(429, $limited->getStatusCode());
        $this->assertStringNotContainsString('private upstream detail', $limited->getContent());
    }
}