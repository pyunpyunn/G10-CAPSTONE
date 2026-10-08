<?php

namespace App\Services\Web;

use App\Presenters\ArchiveExportPresenter;
use App\Queries\ArchiveQuery;
use Illuminate\Http\Request;

class ReportGenerationService
{
    public function __construct(private ArchiveQuery $query, private ArchiveExportPresenter $presenter, private SituationReportService $situation, private ReportFileService $files) {}

    public function generate(Request $request, bool $allowQueue = true)
    {
        $data = $request->validate([
            'report_type' => 'required|in:archive,situation', 'format' => 'nullable|in:pdf,csv,xlsx,excel',
            'category' => 'required_if:report_type,archive|in:disaster-events,household-status-logs,dispatch-logs,radio-communication-logs,resource-requests,situation-reports',
            'event_id' => 'nullable|string|max:255', 'sit_rep_id' => 'nullable|integer|min:1',
            'included_sections' => 'nullable|array', 'included_sections.*' => 'in:I,II,III,IV,V,VI,VII,VIII',
            'actions_text' => 'nullable|string|max:20000', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'ids' => 'nullable|array', 'ids.*' => 'string|max:255',
        ]);
        $data['format'] = $data['format'] ?? 'pdf';
        if ($data['report_type'] === 'archive') {
            $filters = Request::create('/', 'GET', $request->only(['search', 'purok', 'event_id', 'status', 'ids', 'start_date', 'end_date']));
            [$total, $records] = $this->query->exportRows($data['category'], $filters);
            if ($allowQueue && $total > config('reports.queue_threshold')) {
                $token = (string) \Illuminate\Support\Str::uuid();
                \Illuminate\Support\Facades\Storage::disk('local')->put('report-jobs/'.$token.'.json', json_encode(['status' => 'pending']));
                \App\Jobs\GenerateReportFile::dispatch($token, $request->all())->onConnection(config('reports.queue_connection'))->onQueue('reports');
                return response()->json(['status' => 'pending', 'status_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('external.reports.status', now()->addHours(2), ['token' => $token])], 202);
            }
            $headers = array_values($this->presenter->headers($data['category']));
            $rows = $records->map(fn ($record) => $this->presenter->row($record, $data['category']));
            return response()->json($this->files->generate($data['category'], $data['format'], $headers, $rows));
        }
        abort_unless(!empty($data['sit_rep_id']) || !empty($data['event_id']), 422, 'Select an event or saved report.');
        $response = !empty($data['sit_rep_id']) ? $this->situation->show($data['sit_rep_id']) : $this->situation->eventSummary($data['event_id']);
        if ($response->getStatusCode() !== 200) return $response;
        $payload = $response->getData(true)['data'];
        $summary = $payload['report']['summary'] ?? $payload['summary'];
        // Locked reports always use their stored snapshot and metadata.
        if (empty($data['sit_rep_id'])) {
            $summary['actions_text'] = $data['actions_text'] ?? $summary['actions_text'];
            $summary['included_sections'] = $data['included_sections'] ?? $summary['included_sections'];
        }
        $rows = app(\App\Presenters\SituationReportExportPresenter::class)->rows($summary);
        return response()->json($this->files->generate('situation', $data['format'], ['Section', 'Field', 'Value'], $rows));
    }

}
