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

    public function test_situation_pdf_uses_report_layout_and_keeps_event_metadata(): void
    {
        Storage::fake('local');
        config(['reports.disk' => 'local']);

        $summary = json_decode('{
            "report": {
                "report_number": "SITREP-01",
                "period_start": "Oct 08, 2026 8:00 AM",
                "period_end": "Oct 08, 2026 6:00 PM",
                "prepared_by": "Admin",
                "reviewed_by": "Duty Officer"
            },
            "event": {
                "name": "Flooding Response",
                "type": "Flood",
                "declared_at": "Oct 08, 2026 8:00 AM",
                "finished_at": "Ongoing",
                "status": "Active",
                "scope": "Barangay Wide"
            },
            "barangay_profile": {"name": "Barangay Sample"},
            "weather": {
                "condition": "Cloudy",
                "wind": "30 km/h",
                "rainfall": "5 mm",
                "temperature": "29 C",
                "source": "PAGASA",
                "advisory": "Monitor rainfall"
            },
            "household": {
                "total": 100,
                "reported": 80,
                "safe_total": 60,
                "evacuated": 10,
                "unsafe": 8,
                "unchecked": 20,
                "progress_text": "80%",
                "progress_sub": "80 / 100",
                "puroks": [{"purok": "Purok A", "total": 40, "safe": 30, "evacuated": 5, "unsafe": 3, "unchecked": 2}]
            },
            "casualties": {"deaths": 0, "missing": 2, "injured": 4, "rescued": 3},
            "evacuation": [{"name": "School Hall", "type": "Shelter", "status": "Open", "capacity_status": "12 slots left"}],
            "dispatch": {
                "timeline": [{"time": "9:00 AM", "title": "Rescue dispatch", "actor": "Team Alpha", "detail": "Deployment", "status": "On scene"}],
                "rows": [{"team": "Team Alpha", "deployed_at": "9:00 AM", "area": "Zone 2", "outcomes": "3 rescued"}]
            },
            "damage": {"partial": 6, "total": 2, "cost": "PHP 250,000"},
            "resources": {"rows": [{"item": "Bottled water", "quantity": "200 packs", "source": "Relief team", "status": "Verified"}]},
            "actions_text": "Continue monitoring, support evacuations, and check remaining households.",
            "included_sections": ["I", "II", "VIII"]
        }', true);

        $result = app(ReportFileService::class)->generateSituationPdf($summary);
        $bytes = Storage::disk('local')->get('reports/'.$result['file_name']);

        $this->assertStringStartsWith('%PDF', $bytes);
        // PDF content streams are compressed; inspect the rendered source for layout/data.
        $html = view('reports.situation', ['summary' => $summary])->render();
        $this->assertStringContainsString('BDRRMC Situation Report', $html);
        $this->assertStringContainsString('Flooding Response', $html);
        $this->assertStringContainsString('Barangay Mambaling', $html);
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
