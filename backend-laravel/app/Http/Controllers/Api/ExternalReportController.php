<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExternalReportController extends Controller
{
    public function handshake()
    {
        return response()->json([
            'status' => 'success',
            'authenticated' => true,
            'source' => 'RESQPERATION',
            'generate_endpoint' => '/api/v1/external/reports/generate',
            'report_types' => ['archive', 'situation'],
            'situation_sections' => \App\Support\SituationReportSections::LABELS,
            'situation_requires_review' => true,
            'request_endpoint' => '/api/v1/external/report-requests',
            'options_endpoint' => '/api/v1/external/reports/options',
            'request_fields' => ['source_system', 'event_id', 'sitio_id', 'included_sections', 'format', 'purpose', 'reference (optional)'],
            'all_sitios_value' => 'all',
            'api_version' => '2.0',
            'formats' => ['pdf', 'csv', 'xlsx'],
            'archive_categories' => ['disaster-events', 'household-status-logs', 'dispatch-logs', 'radio-communication-logs', 'resource-requests', 'situation-reports'],
            'expires_in_minutes' => (int) config('reports.expires_minutes', 30),
        ])->header('Cache-Control', 'no-store');
    }

    public function generate(Request $request, \App\Services\Web\ReportGenerationService $reports)
    {
        if (!$request->user() && $request->input('report_type') === 'situation') {
            return app(ReportExchangeController::class)->store($request);
        }
        return $reports->generate($request);
    }

    public function status(string $token)
    {
        abort_unless(\Illuminate\Support\Str::isUuid($token), 404);
        $disk = Storage::disk('local');
        $path = 'report-jobs/'.$token.'.json';
        abort_unless($disk->exists($path), 404);
        return response()->json(json_decode($disk->get($path), true));
    }

    public function download(string $filename)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]+\.(pdf|csv|xlsx)$/D', $filename), 404);
        $disk = Storage::disk(config('reports.disk'));
        abort_unless($disk->exists('reports/'.$filename), 404);
        return $disk->download('reports/'.$filename);
    }
}
