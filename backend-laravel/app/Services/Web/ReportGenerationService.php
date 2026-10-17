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
            'sitio_id' => 'nullable|string|max:120',
            'included_sections' => 'sometimes|required|array|min:1', 'included_sections.*' => ['distinct', \Illuminate\Validation\Rule::in(array_keys(\App\Support\SituationReportSections::LABELS))],
            'actions_text' => 'nullable|string|max:20000', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'ids' => 'nullable|array', 'ids.*' => 'string|max:255',
            'report_number' => 'nullable|string|max:80', 'prepared_by' => 'nullable|string|max:150', 'reviewed_by' => 'nullable|string|max:150',
            'period_start' => 'nullable|date', 'period_end' => 'nullable|date|after_or_equal:period_start',
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
        if (!empty($data['sit_rep_id']) && isset($data['sitio_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['sitio_id' => 'Select a disaster event to export current sitio data. Saved snapshots cannot be filtered by sitio.']);
        }
        $response = !empty($data['sit_rep_id']) ? $this->situation->show($data['sit_rep_id'])
            : (isset($data['sitio_id']) ? $this->situation->eventSummary($data['event_id'], $data['sitio_id']) : $this->situation->eventSummary($data['event_id']));
        if ($response->getStatusCode() !== 200) return $response;
        $payload = $response->getData(true)['data'];
        $summary = $payload['report']['summary'] ?? $payload['summary'];
        // Locked reports always use their stored snapshot and metadata.
        if (empty($data['sit_rep_id'])) {
            $summary['actions_text'] = $data['actions_text'] ?? $summary['actions_text'];
            $summary['included_sections'] = $data['included_sections'] ?? $summary['included_sections'];
            foreach (['report_number', 'prepared_by', 'reviewed_by', 'period_start', 'period_end'] as $field) {
                if (isset($data[$field])) $summary['report'][$field] = in_array($field, ['period_start', 'period_end'], true)
                    ? \Carbon\Carbon::parse($data[$field])->format('M d, Y g:i A') : $data[$field];
            }
        }

        // A section-only copy may narrow a locked snapshot without changing its data or metadata.
        if (!empty($data['sit_rep_id']) && isset($data['included_sections'])) {
            $available = $summary['included_sections'] ?? [];
            if (array_diff($data['included_sections'], $available)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['included_sections' => 'Choose sections included in this saved report.']);
            }
            $summary['included_sections'] = $data['included_sections'];
        }
        if (empty($summary['included_sections'])) $summary['included_sections'] = array_keys(\App\Support\SituationReportSections::LABELS);

        if ($data['format'] === 'pdf') {
            return response()->json($this->files->generateSituationPdf($summary));
        }

        return response()->json($this->files->generateSituationSpreadsheet($summary, $data['format']));
    }

}
