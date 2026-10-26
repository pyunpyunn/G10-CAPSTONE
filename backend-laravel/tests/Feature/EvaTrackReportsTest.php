<?php

namespace Tests\Feature;

use App\Services\Shared\EvaTrackReportClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvaTrackReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'evatrack.reports_url' => 'http://evatrack.test',
            'evatrack.reports_api_key' => 'private-test-key',
            'safetrack.base_url' => '',
            'safetrack.api_key' => '',
            'trackingaid_reports.reports_url' => '',
            'trackingaid_reports.reports_api_key' => '',
        ]);
        Http::preventStrayRequests();
    }

    private function report(): array
    {
        return ['status' => 'success', 'file_name' => 'general_test.csv',
            'download_url' => 'http://evatrack.test/download?signature=test',
            'expires_at' => now()->addMinutes(30)->toIso8601String(), 'expires_in_minutes' => 30];
    }

    public function test_generation_forwards_the_documented_payload_and_keeps_key_private(): void
    {
        Http::fake(['evatrack.test/*' => Http::response($this->report())]);
        $this->withoutMiddleware();
        $this->postJson('/api/v1/reports/evatrack/generate', [
            'report_type' => 'demographics', 'format' => 'csv', 'start_date' => '2026-10-01', 'end_date' => '2026-10-14',
        ])->assertOk()->assertJsonPath('source', 'EvaTrack')->assertDontSee('private-test-key');
        Http::assertSent(fn ($request) => $request->url() === 'http://evatrack.test/api/v1/external/reports/generate'
            && $request->hasHeader('X-API-KEY', 'private-test-key') && $request['report_type'] === 'demographics'
            && $request['end_date'] === '2026-10-14');
    }

    public function test_external_provider_catalog_exposes_only_implemented_report_capabilities(): void
    {
        $this->getJson('/api/v1/reports/external/providers')->assertUnauthorized();
        $this->withoutMiddleware();
        $this->getJson('/api/v1/reports/external/providers')->assertOk()
            ->assertJsonPath('data.providers.0.key', 'evatrack')
            ->assertJsonPath('data.providers.0.implemented', true)
            ->assertJsonPath('data.providers.0.configured', true)
            ->assertJsonPath('data.providers.1.name', 'SafeTrack')
            ->assertJsonPath('data.providers.1.implemented', true)
            ->assertJsonPath('data.providers.1.configured', false)
            ->assertJsonPath('data.providers.2.name', 'TrackingAid')
            ->assertJsonPath('data.providers.2.implemented', true)
            ->assertJsonPath('data.providers.2.configured', false)
            ->assertJsonPath('data.providers.2.report_types.0', 'demographics')
            ->assertDontSee('private-test-key');
        Http::assertNothingSent();
    }

    public function test_routes_require_user_authentication(): void
    {
        $this->getJson('/api/v1/reports/evatrack/configuration')->assertUnauthorized();
        $this->postJson('/api/v1/reports/evatrack/generate', ['report_type' => 'general'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_external_system_can_authenticate_and_discover_our_reports(): void
    {
        config(['reports.external_api_key' => 'our-private-test-key']);
        $this->postJson('/api/v1/external/reports/handshake')->assertUnauthorized();
        $this->postJson('/api/v1/external/reports/handshake', [], ['X-API-KEY' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/v1/external/reports/handshake', [], ['X-API-KEY' => 'our-private-test-key'])
            ->assertOk()->assertJsonPath('authenticated', true)
            ->assertJsonPath('report_types', ['archive', 'situation'])->assertDontSee('our-private-test-key');
    }

    public function test_invalid_inputs_never_reach_evatrack(): void
    {
        $this->withoutMiddleware();
        $this->postJson('/api/v1/reports/evatrack/generate', [
            'report_type' => 'archive', 'format' => 'html', 'start_date' => '2026-10-14', 'end_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['report_type', 'format', 'end_date']);
        Http::assertNothingSent();
    }

    public function test_configuration_does_not_expose_the_key_and_missing_key_blocks_calls(): void
    {
        $this->withoutMiddleware();
        $this->getJson('/api/v1/reports/evatrack/configuration')->assertOk()->assertJsonPath('data.configured', true)->assertDontSee('private-test-key');
        config(['evatrack.reports_api_key' => '']);
        $this->postJson('/api/v1/reports/evatrack/generate', ['report_type' => 'general'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_upstream_errors_are_reported_without_leaking_the_remote_body(): void
    {
        $sequence = Http::sequence();
        foreach ([401, 422, 429, 500] as $status) $sequence->push(['secret' => 'private-test-key'], $status);
        Http::fake(['*' => $sequence]);
        foreach ([401 => 502, 422 => 502, 429 => 429, 500 => 502] as $upstream => $expected) {
            $result = app(EvaTrackReportClient::class)->generate(['report_type' => 'general']);
            $this->assertSame($expected, $result->getStatusCode());
            $this->assertStringNotContainsString('private-test-key', $result->getContent());
        }
    }

    public function test_unreachable_server_and_invalid_response_fail_clearly(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->assertSame(503, app(EvaTrackReportClient::class)->generate(['report_type' => 'general'])->getStatusCode());
    }

    public function test_invalid_download_response_is_rejected(): void
    {
        Http::fake(['*' => Http::response(array_merge($this->report(), ['download_url' => 'javascript:alert(1)']))]);
        $this->assertSame(502, app(EvaTrackReportClient::class)->generate(['report_type' => 'general'])->getStatusCode());
    }

    public function test_excel_export_converts_real_evatrack_csv_without_sending_the_api_key_to_the_download(): void
    {
        Storage::fake('local'); config(['reports.disk' => 'local']);
        Http::fake([
            'evatrack.test/api/*' => Http::response($this->report()),
            'evatrack.test/download*' => Http::response("Area,Persons\nReal area,3\n", 200, ['Content-Type' => 'text/csv']),
        ]);
        $response = app(EvaTrackReportClient::class)->generate(['report_type'=>'demographics','format'=>'xlsx']);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame('xlsx', $data['format']);
        $this->assertStringStartsWith('PK', Storage::disk('local')->get('reports/'.$data['file_name']));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/generate') && $request['format'] === 'csv');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/download') && ! $request->hasHeader('X-API-KEY'));
    }

    public function test_excel_export_rejects_downloads_outside_the_configured_origin(): void
    {
        Http::fake(['evatrack.test/*' => Http::response(array_merge($this->report(), ['download_url'=>'http://other.test/private.csv']))]);
        $this->assertSame(502, app(EvaTrackReportClient::class)->generate(['report_type'=>'general','format'=>'xlsx'])->getStatusCode());
        Http::assertSentCount(1);
    }

    public function test_verification_command_downloads_and_saves_the_report(): void
    {
        Storage::fake('local');
        Http::fake([
            'evatrack.test/api/*' => Http::response($this->report()),
            'evatrack.test/download*' => Http::response("Type,Count\nGeneral,10\n", 200, ['Content-Type' => 'text/csv']),
        ]);
        $this->artisan('reports:verify-evatrack')->assertSuccessful();
        Storage::disk('local')->assertExists('evatrack-verification/general_test.csv');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/download') && ! $request->hasHeader('X-API-KEY'));
    }

    public function test_trackingaid_uses_the_external_report_contract_with_independent_credentials(): void
    {
        config([
            'trackingaid_reports.reports_url' => 'http://trackingaid.test',
            'trackingaid_reports.reports_api_key' => 'private-trackingaid-key',
        ]);
        Http::fake(['trackingaid.test/*' => Http::response([
            'status' => 'success',
            'file_name' => 'general_test.csv',
            'download_url' => 'http://trackingaid.test/download?signature=test',
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'expires_in_minutes' => 30,
        ])]);

        $this->withoutMiddleware();
        $this->postJson('/api/v1/reports/trackingaid/generate', [
            'report_type' => 'general', 'format' => 'csv',
        ])->assertOk()->assertJsonPath('source', 'TrackingAid')->assertDontSee('private-trackingaid-key');

        Http::assertSent(fn ($request) => $request->url() === 'http://trackingaid.test/api/v1/external/reports/generate'
            && $request->hasHeader('X-API-KEY', 'private-trackingaid-key')
            && $request['report_type'] === 'general');
    }
}
