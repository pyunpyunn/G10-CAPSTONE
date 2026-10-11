<?php

namespace Tests\Feature;

use App\Services\Web\ReportFileService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalReportsTest extends TestCase
{
    public function test_external_generation_requires_configured_api_key_and_valid_payload(): void
    {
        config(['reports.external_api_key' => 'test-secret']);
        $this->postJson('/api/v1/external/reports/generate', [])->assertUnauthorized();
        $this->withHeader('X-API-KEY', 'wrong')->postJson('/api/v1/external/reports/generate', [])->assertUnauthorized();
        $this->withHeader('X-API-KEY', 'test-secret')->postJson('/api/v1/external/reports/generate', [
            'report_type' => 'archive', 'category' => 'disaster-events', 'format' => 'html',
        ])->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->postJson('/api/v1/reports/generate', [])->assertUnauthorized();
    }

    public function test_pdf_csv_and_real_xlsx_have_signed_downloads_and_expiration(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);
        foreach (['pdf' => '%PDF', 'csv' => "\xEF\xBB\xBF", 'xlsx' => 'PK'] as $format => $magic) {
            $result = app(ReportFileService::class)->generate('archive', $format, ['Reference', 'Description'], [['EVT-42', '=SUM(A1)']]);
            $bytes = Storage::disk('local')->get('reports/'.$result['file_name']);
            $this->assertStringStartsWith($magic, $bytes);
            if ($format === 'csv') $this->assertStringContainsString("'=SUM(A1)", $bytes);
            $this->get($result['download_url'])->assertOk()->assertDownload($result['file_name']);
            $this->get($result['download_url'].'&changed=yes')->assertForbidden();
            $this->travel(31)->minutes();
            $this->get($result['download_url'])->assertForbidden();
            $this->travelBack();
        }
    }

    public function test_pdf_limit_rejects_instead_of_truncating_records(): void
    {
        config(['reports.pdf_max_rows' => 1]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ReportFileService::class)->generate('archive', 'pdf', ['Reference'], [['first'], ['second']]);
    }

    public function test_large_archive_exports_are_queued_and_status_is_signed(): void
    {
        Storage::fake('local');
        \Illuminate\Support\Facades\Queue::fake();
        config(['reports.external_api_key' => 'test-secret', 'reports.queue_threshold' => 1]);
        $query = \Mockery::mock(\App\Queries\ArchiveQuery::class);
        $query->shouldReceive('exportRows')->once()->andReturn([2, \Illuminate\Support\LazyCollection::make([])]);
        $this->app->instance(\App\Queries\ArchiveQuery::class, $query);
        $response = $this->withHeader('X-API-KEY', 'test-secret')->postJson('/api/v1/external/reports/generate', [
            'report_type' => 'archive', 'category' => 'disaster-events', 'format' => 'xlsx',
        ])->assertStatus(202)->assertJsonPath('status', 'pending');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\GenerateReportFile::class, fn ($job) => $job->queue === 'reports');
        $url = $response->json('status_url');
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'pending');
        $this->getJson($url.'&tampered=true')->assertForbidden();
    }
}
