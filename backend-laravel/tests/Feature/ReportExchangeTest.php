<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportExchangeController;
use App\Services\Web\ReportFileService;
use App\Services\Web\SituationReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExchangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_17_000001_create_external_report_requests_table.php'))->up();
        (require database_path('migrations/2026_10_17_000002_add_sitio_to_external_report_requests.php'))->up();
        (require database_path('migrations/2026_10_18_000022_add_external_report_contract_version.php'))->up();
        $this->mock(\App\Queries\SituationReportQuery::class, function ($mock): void {
            $mock->shouldReceive('nextReportNumber')->andReturn('1');
            $mock->shouldReceive('findEvent')->with('EV-1')->andReturn((object) ['event_id' => 'EV-1']);
            $mock->shouldReceive('findEvent')->with('UNKNOWN')->andReturn(null);
            $mock->shouldReceive('sitioOptions')->andReturn(collect([(object) ['sitio_id' => 710, 'sitio_name' => 'Sitio Alaska']]));
            $mock->shouldReceive('eventOptions')->andReturn(collect([(object) ['event_id'=>'EV-1', 'name'=>'Flood', 'started_at'=>'2026-10-17 08:00:00', 'ended_at'=>null]]));
        });
        config(['reports.external_api_key' => 'exchange-secret', 'reports.disk' => 'local']);
        Storage::fake('local');
    }

    public function test_partner_contract_declares_v4_and_rejects_unversioned_or_v2_requests(): void
    {
        $this->withHeader('X-API-KEY','exchange-secret')->postJson('/api/v1/external/reports/handshake')
            ->assertOk()->assertJsonPath('api_version','4.0')->assertJsonMissingPath('situation_sections.III')->assertJsonPath('situation_sections.VI','Status of Evacuation Centers');
        foreach ([[],['api_version'=>'2.0']] as $version) {
            $this->withHeader('X-API-KEY','exchange-secret')->postJson('/api/v1/external/report-requests',$version+[
                'source_system'=>'safetrack','event_id'=>'EV-1','sitio_id'=>'all','included_sections'=>['I'],
                'format'=>'pdf','purpose'=>'Planning',
            ])->assertUnprocessable()->assertJsonValidationErrors('api_version');
        }
    }

    public function test_v4_accepts_six_sections_and_rejects_a_seventh(): void
    {
        $keys=array_keys(\App\Support\SituationReportSections::LABELS);
        $result=$this->submit(['included_sections'=>$keys]);
        $this->assertSame($keys,json_decode(DB::table('external_report_requests')->where('id',$result['request_id'])->value('sections'),true));
        $this->assertSame('4.0',DB::table('external_report_requests')->where('id',$result['request_id'])->value('api_version'));
        $this->withHeader('X-API-KEY','exchange-secret')->postJson('/api/v1/external/report-requests',[
            'api_version'=>'4.0','source_system'=>'evatrack','event_id'=>'EV-1','sitio_id'=>'all','included_sections'=>[...$keys,'III'],
            'format'=>'csv','purpose'=>'Planning',
        ])->assertUnprocessable()->assertJsonValidationErrors('included_sections');
    }

    public function test_old_pending_requests_require_resubmission(): void
    {
        $result=$this->submit();
        DB::table('external_report_requests')->where('id',$result['request_id'])->update(['api_version'=>'2.0']);
        try { $this->decision($result['request_id'],['decision'=>'accepted']); $this->fail('Must reject retired section meanings.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(422,$e->getStatusCode()); }
        $this->assertSame('pending',DB::table('external_report_requests')->where('id',$result['request_id'])->value('status'));
    }

    public function test_acceptance_freezes_findings_entries_and_leaves_chair_signature_blank(): void
    {
        $result=$this->submit(['included_sections'=>array_keys(\App\Support\SituationReportSections::LABELS)]);
        $this->mock(SituationReportService::class,fn ($mock)=>$mock->shouldReceive('eventSummary')->with('EV-1')->once()->andReturn(response()->json(['data'=>['summary'=>[
            'schema_version'=>'4.0','event'=>['event_id'=>'EV-1','name'=>'Flood'],
            'report'=>['approved_by'=>'Must not be copied'],
        ]]])));
        $this->decision($result['request_id'],['decision'=>'accepted','sitrep_type'=>'initial',
            'resources_text'=>'Available: 40 water bottles','overview_text'=>'Flooding in Purok One','response_text'=>'Team deployed',
            'approved_by'=>'Must not be accepted',
        ]);
        $snapshot=json_decode(DB::table('external_report_requests')->where('id',$result['request_id'])->value('approved_summary'),true);
        $this->assertSame('4.0',$snapshot['schema_version']);
        $this->assertSame('1',$snapshot['report']['report_number']);
        $this->assertSame('initial',$snapshot['report']['report_type']);
        $this->assertSame('Emergency Operations Center: ResQperation System',$snapshot['report']['prepared_by']);
        $this->assertSame('Duty officer',$snapshot['report']['reviewed_by']);
        $this->assertNull($snapshot['report']['approved_by']);
        $this->assertSame('Available: 40 water bottles',$snapshot['operator_inputs']['resources_text']);
    }

    private function submit(array $overrides = []): array
    {
        return $this->withHeader('X-API-KEY', 'exchange-secret')->postJson('/api/v1/external/report-requests', array_merge([
            'api_version'=>'4.0', 'source_system' => 'evatrack', 'included_sections' => ['II'], 'format' => 'csv',
            'purpose' => 'Plan relief distribution by sitio and purok.', 'event_id' => 'EV-1', 'reference' => 'EVA-42', 'sitio_id' => 'all',
        ], $overrides))->assertStatus(202)->json();
    }

    private function decision(string $id, array $data)
    {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => new \App\Models\User(['user_id' => 'ADMIN-1', 'name' => 'Duty officer']));
        return app(ReportExchangeController::class)->decide($request, $id, app(SituationReportService::class), app(ReportFileService::class));
    }

    public function test_options_provide_database_choices_and_require_api_key(): void
    {
        $this->getJson('/api/v1/external/reports/options')->assertUnauthorized();
        $this->withHeader('X-API-KEY', 'exchange-secret')->getJson('/api/v1/external/reports/options')
            ->assertOk()->assertJsonPath('data.events.0.event_id', 'EV-1')
            ->assertJsonPath('data.sitios.0.sitio_id', 710)->assertJsonPath('data.all_sitios_value', 'all')
            ->assertJsonPath('data.sections.II', 'Status of Affected Areas and Population');
    }

    public function test_external_submission_requires_disaster_and_explicit_sitio_choice(): void
    {
        $payload = ['api_version'=>'4.0', 'source_system'=>'evatrack', 'included_sections'=>['II'], 'format'=>'pdf', 'purpose'=>'Relief planning'];
        $this->withHeader('X-API-KEY', 'exchange-secret')->postJson('/api/v1/external/report-requests', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['event_id', 'sitio_id']);
        $this->postJson('/api/v1/external/report-requests', $payload + ['event_id'=>'UNKNOWN', 'sitio_id'=>'all'])
            ->assertUnprocessable()->assertJsonValidationErrors('event_id');
        $this->postJson('/api/v1/external/report-requests', $payload + ['event_id'=>'EV-1', 'sitio_id'=>'999'])
            ->assertUnprocessable()->assertJsonValidationErrors('sitio_id');
        $this->assertSame(0, DB::table('external_report_requests')->count());
    }

    public function test_requested_sitio_is_preserved_and_used_when_accepted(): void
    {
        $result = $this->submit(['sitio_id' => '710']);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('data.sitio_id', '710');
        $this->mock(SituationReportService::class, function ($mock): void {
            $mock->shouldReceive('eventSummary')->once()->with('EV-1', '710')->andReturn(response()->json(['data'=>['summary'=>[
                'event'=>['name'=>'Flood', 'sitio_name'=>'Sitio Alaska'], 'report'=>[],
                'household'=>['total'=>3], 'included_sections'=>[],
            ]]]));
        });
        $row = $this->decision($result['request_id'], ['decision'=>'accepted', 'event_id'=>'ANOTHER-EVENT'])->getData(true)['data'];
        $this->assertSame('710', $row['sitio_id']);
        $this->assertSame('EV-1', $row['event_id']);
        $csv = Storage::disk('local')->get('reports/'.$row['file']['file_name']);
        $this->assertStringContainsString('Sitio Alaska', $csv);
    }

    public function test_external_request_requires_api_key_and_valid_section_selection(): void
    {
        $this->postJson('/api/v1/external/report-requests', [])->assertUnauthorized();
        $this->withHeader('X-API-KEY', 'exchange-secret')->postJson('/api/v1/external/report-requests', [
            'api_version'=>'4.0', 'source_system' => 'evatrack', 'included_sections' => ['XIII'], 'format' => 'csv', 'purpose' => 'Need data',
        ])->assertUnprocessable()->assertJsonValidationErrors('included_sections.0');
        $this->withHeader('X-API-KEY', 'exchange-secret')->postJson('/api/v1/external/report-requests', [
            'api_version'=>'4.0', 'source_system' => 'evatrack', 'included_sections' => [], 'format' => 'csv', 'purpose' => 'Need data',
        ])->assertUnprocessable()->assertJsonValidationErrors('included_sections');
    }

    public function test_pending_request_has_signed_status_and_no_file_and_cannot_be_reviewed_anonymously(): void
    {
        $result = $this->submit();
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.file', null);
        $this->getJson('/api/v1/external/report-requests/'.$result['request_id'])->assertForbidden();
        $this->postJson('/api/v1/reports/external/requests/'.$result['request_id'].'/decision', ['decision' => 'accepted'])->assertUnauthorized();
        $this->getJson('/api/v1/reports/external/requests')->assertUnauthorized();
    }

    public function test_old_external_situation_generate_endpoint_also_requires_review(): void
    {
        $this->withHeader('X-API-KEY', 'exchange-secret')->postJson('/api/v1/external/reports/generate', [
            'api_version'=>'4.0', 'report_type' => 'situation', 'source_system' => 'safetrack', 'event_id' => 'EV-1', 'sitio_id' => 'all',
            'included_sections' => ['II'], 'format' => 'pdf', 'purpose' => 'Population verification',
        ])->assertStatus(202)->assertJsonPath('status', 'pending')->assertJsonMissingPath('download_url');
        $this->assertSame(1, DB::table('external_report_requests')->where('status', 'pending')->count());
    }

    public function test_acceptance_produces_only_population_and_keeps_an_approved_snapshot(): void
    {
        $result = $this->submit();
        $this->mock(SituationReportService::class, function ($mock): void {
            $mock->shouldReceive('eventSummary')->once()->with('EV-1')->andReturn(response()->json(['data' => ['summary' => [
                'event' => ['event_id' => 'EV-1', 'name' => 'Flood'], 'report' => [],
                'household' => ['total' => 12, 'affected' => 4, 'puroks' => [['sitio' => 'Sitio A', 'purok' => 'Purok 1', 'total' => 12, 'affected'=>4, 'affected_persons'=>15]]],
                'casualties' => ['injured' => 999], 'actions_text' => 'Private unrelated operations note', 'included_sections' => [],
            ]]]));
        });
        $response = $this->decision($result['request_id'], ['decision' => 'accepted', 'note' => 'Reviewed for relief planning']);
        $row = $response->getData(true)['data'];
        $this->assertSame('accepted', $row['status']);
        $bytes = Storage::disk('local')->get('reports/'.$row['file']['file_name']);
        $this->assertStringContainsString('"Sitio A","Purok 1",4,15', $bytes);
        $this->assertStringNotContainsString('Private unrelated operations note', $bytes);
        $this->assertStringNotContainsString('Injured', $bytes);
        $stored = DB::table('external_report_requests')->where('id', $row['id'])->first();
        $this->assertSame(['II'], json_decode($stored->approved_summary, true)['included_sections']);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.decided_by', 'Duty officer');
        $this->get($row['file']['download_url'])->assertOk();
        $this->travel(31)->minutes();
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('data.file.expired', true)->assertJsonPath('data.file.download_url', null);
        // Renewal uses the stored approved snapshot, without querying current event data again.
        $renewed = app(ReportExchangeController::class)->refreshFile($row['id'], app(ReportFileService::class))->getData(true)['data'];
        $this->assertNotSame($row['file']['file_name'], $renewed['file']['file_name']);
        $this->assertStringNotContainsString('Injured', Storage::disk('local')->get('reports/'.$renewed['file']['file_name']));
    }

    public function test_rejection_is_visible_to_requester_and_cannot_be_overwritten(): void
    {
        $result = $this->submit();
        $this->decision($result['request_id'], ['decision' => 'rejected', 'note' => 'Specify the affected area.']);
        $this->getJson($result['status_url'])->assertOk()->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision_note', 'Specify the affected area.')->assertJsonPath('data.file', null);
        try {
            $this->decision($result['request_id'], ['decision' => 'accepted']);
            $this->fail('A second decision must not overwrite the first.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_rejection_requires_a_reason_and_acceptance_requires_event(): void
    {
        $result = $this->submit();
        // Historical requests may predate the mandatory event selection.
        DB::table('external_report_requests')->where('id', $result['request_id'])->update(['event_id' => null]);
        try {
            $this->decision($result['request_id'], ['decision' => 'rejected', 'note' => '']);
            $this->fail('Rejection needs a reason.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }
        try {
            $this->decision($result['request_id'], ['decision' => 'accepted']);
            $this->fail('Acceptance needs an event.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame('pending', DB::table('external_report_requests')->where('id', $result['request_id'])->value('status'));
    }

    public function test_inbox_filters_requests_and_returns_totals_for_each_status(): void
    {
        $pending = $this->submit();
        $rejected = $this->submit(['source_system' => 'trackingaid']);
        $this->decision($rejected['request_id'], ['decision' => 'rejected', 'note' => 'Incomplete purpose.']);
        $response = app(ReportExchangeController::class)->index(Request::create('/', 'GET', ['status' => 'pending']))->getData(true)['data'];
        $this->assertCount(1, $response['requests']);
        $this->assertSame($pending['request_id'], $response['requests'][0]['id']);
        $this->assertEquals(1, $response['counts']['pending']);
        $this->assertEquals(1, $response['counts']['rejected']);
        $this->assertArrayHasKey('IV', $response['sections']);
        $controller = app(ReportExchangeController::class);
        $filtered = $controller->index(Request::create('/', 'GET', ['source_system' => 'trackingaid']))->getData(true)['data'];
        $this->assertCount(1, $filtered['requests']);
        $this->assertSame('trackingaid', $filtered['requests'][0]['source_system']);
        $searched = $controller->index(Request::create('/', 'GET', ['search' => 'Affected Areas and Population']))->getData(true)['data'];
        $this->assertCount(2, $searched['requests']);
    }

    public function test_population_only_pdf_template_does_not_include_casualties_or_other_sections(): void
    {
        $html = view('reports.situation', ['summary' => [
            'included_sections' => ['II'], 'household' => ['total' => 10, 'puroks' => [['sitio' => 'Sitio A', 'purok' => 'Purok 1']]],
            'casualties' => ['injured' => 999], 'actions_text' => 'Unrelated action note',
        ]])->render();
        $this->assertStringContainsString('Affected Areas and Population', $html);
        $this->assertStringContainsString('Sitio A', $html);
        $this->assertStringNotContainsString('Injured', $html);
        $this->assertStringNotContainsString('Unrelated action note', $html);
        $this->assertStringNotContainsString('Rescue Operations Timeline', $html);
    }
    public function test_external_evacuation_section_can_be_requested_and_exported(): void
    {
        $result = $this->submit(['included_sections' => ['VI']]);
        $this->mock(SituationReportService::class, function ($mock): void {
            $mock->shouldReceive('eventSummary')->with('EV-1')->andReturn(response()->json(['data' => ['summary' => [
                'report' => [], 'event' => ['name' => 'Flood'], 'evacuation' => ['households' => [], 'members' => [], 'sites' => [
                    ['name' => 'School Shelter', 'status' => 'Open', 'capacity' => 20, 'occupancy' => 12, 'vacancy' => 8, 'assigned_members' => 2],
                ]],
            ]]]));
        });
        $response = $this->decision($result['request_id'], ['decision' => 'accepted'])->getData(true)['data'];
        $csv = Storage::disk('local')->get('reports/'.$response['file']['file_name']);
        $this->assertStringNotContainsString('Status of Displaced Population', $csv);
        $this->assertStringContainsString('School Shelter', $csv);
        $this->assertStringContainsString('Vacancy', $csv);
    }

    public function test_partner_reports_identify_resqperation_as_preparer_in_all_formats(): void
    {
        $preparer = 'Emergency Operations Center: ResQperation System';
        $this->mock(SituationReportService::class, function ($mock): void {
            $mock->shouldReceive('eventSummary')->with('EV-1')->times(3)->andReturn(response()->json(['data' => ['summary' => [
                'report' => ['prepared_by' => 'Original preparer'], 'event' => ['name' => 'Flood'], 'household' => ['total' => 0],
            ]]]));
        });
        foreach (['pdf', 'csv', 'xlsx'] as $format) {
            $result = $this->submit(['format' => $format]);
            $this->assertSame($preparer, $result['prepared_by']);
            $this->getJson($result['status_url'])->assertJsonPath('data.prepared_by', $preparer);
            $row = $this->decision($result['request_id'], ['decision' => 'accepted'])->getData(true)['data'];
            $this->assertSame('evatrack', $row['source_system']);
            $this->assertSame($preparer, $row['prepared_by']);
            $summary = json_decode(DB::table('external_report_requests')->where('id', $row['id'])->value('approved_summary'), true);
            $this->assertSame($preparer, $summary['report']['prepared_by']);
            $this->assertSame('Duty officer', $summary['report']['reviewed_by']);
            $bytes = Storage::disk('local')->get('reports/'.$row['file']['file_name']);
            if ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF', $bytes);
                $bytes = view('reports.situation', ['summary' => $summary])->render();
            } elseif ($format === 'xlsx') {
                $path = tempnam(sys_get_temp_dir(), 'source-test'); file_put_contents($path, $bytes);
                $zip = new \ZipArchive; $zip->open($path); $bytes = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close(); unlink($path);
            }
            $this->assertStringContainsString($preparer, $bytes);
        }
    }

}
