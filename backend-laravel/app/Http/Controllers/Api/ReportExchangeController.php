<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\ReportFileService;
use App\Services\Web\SituationReportService;
use App\Support\SituationReportSections;
use App\Queries\SituationReportQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportExchangeController extends Controller
{
    private const REPORT_PREPARER = 'Emergency Operations Center: ResQperation System';
    public function options(SituationReportQuery $query, \App\Presenters\SituationReportPresenter $presenter)
    {
        return response()->json(['data' => [
            'events' => $query->eventOptions()->map(fn ($event) => $presenter->formatEventOption($event))->values(),
            'sitios' => $query->sitioOptions(),
            'all_sitios_value' => 'all',
            'api_version' => '4.0',
            'sections' => SituationReportSections::LABELS,
            'formats' => ['pdf', 'csv', 'xlsx'],
            'source_systems' => ['evatrack', 'safetrack', 'trackingaid'],
        ]])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected'])], 'page' => 'nullable|integer|min:1',
            'source_system' => ['nullable', Rule::in(['evatrack', 'safetrack', 'trackingaid'])], 'search' => 'nullable|string|max:160',
        ]);
        $rows = DB::table('external_report_requests')->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['source_system'] ?? null, fn ($q, $source) => $q->where('source_system', $source))
            ->when($data['search'] ?? null, function ($q, $search): void {
                $matchingSections = array_keys(array_filter(SituationReportSections::LABELS, fn ($title) => stripos($title, $search) !== false));
                $q->where(function ($q) use ($search, $matchingSections): void {
                    foreach (['source_system', 'reference', 'purpose', 'id'] as $column) $q->orWhere($column, 'like', '%'.$search.'%');
                    foreach ($matchingSections as $section) $q->orWhere('sections', 'like', '%"'.$section.'"%');
                });
            })
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $counts = DB::table('external_report_requests')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        return response()->json(['data' => [
            'requests' => collect($rows->items())->map(fn ($row) => $this->present($row)),
            'counts' => $counts, 'sections' => SituationReportSections::LABELS,
            'page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $request->validate(['api_version'=>['required', Rule::in(['4.0'])]], [
            'api_version.required'=>'Report contract v4.0 requires api_version: 4.0. Refresh report options and update the I–VII section meanings before resubmitting.',
            'api_version.in'=>'Older report contracts are retired. Use api_version: 4.0 with the six-section meanings from options.',
        ]);
        $data = $request->validate([
            'source_system' => ['required', Rule::in(['evatrack', 'safetrack', 'trackingaid'])],
            'reference' => 'nullable|string|max:120', 'event_id' => 'required|string|max:255',
            'sitio_id' => 'required|string|max:120',
            'included_sections' => 'required|array|min:1|max:6',
            'included_sections.*' => ['required', 'distinct', Rule::in(array_keys(SituationReportSections::LABELS))],
            'format' => ['required', Rule::in(['pdf', 'csv', 'xlsx'])], 'purpose' => 'required|string|max:2000',
        ]);
        $query = app(SituationReportQuery::class);
        if (!$query->findEvent($data['event_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['event_id' => 'Select a disaster event from RESQPERATION.']);
        }
        if ($data['sitio_id'] !== 'all' && !$query->sitioOptions()->firstWhere('sitio_id', $data['sitio_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['sitio_id' => 'Select a registered sitio or all.']);
        }
        $id = (string) Str::uuid();
        DB::table('external_report_requests')->insert([
            'api_version' => '4.0',
            'id' => $id, 'source_system' => $data['source_system'], 'reference' => $data['reference'] ?? null,
            'event_id' => $data['event_id'] ?? null, 'sections' => json_encode($data['included_sections']),
            'sitio_id' => $data['sitio_id'],
            'format' => $data['format'], 'purpose' => $data['purpose'], 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['status' => 'pending', 'request_id' => $id, 'prepared_by' => self::REPORT_PREPARER,
            'message' => 'Request received. A RESQPERATION operator must accept it before a file is released.',
            'status_url' => URL::signedRoute('external.report-requests.status', ['id' => $id]),
        ], 202)->header('Cache-Control', 'no-store');
    }

    public function externalStatus(string $id)
    {
        $row = DB::table('external_report_requests')->where('id', $id)->first();
        abort_unless($row, 404);
        return response()->json(['data' => $this->present($row)])->header('Cache-Control', 'no-store');
    }

    public function decide(Request $request, string $id, SituationReportService $situations, ReportFileService $files)
    {
        $rules = array_fill_keys(SituationReportSections::INPUTS, 'nullable|string|max:20000');
        $data = $request->validate([...$rules,
            'sitrep_type'=>'nullable|in:flash,initial,progress,final', 'actions_text'=>'nullable|string|max:20000',
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'note' => 'required_if:decision,rejected|nullable|string|max:2000',
            'event_id' => 'nullable|string|max:255',
        ]);
        $sync=null;
        if ($data['decision']==='accepted') {
            $pending=DB::table('external_report_requests')->where('id',$id)->first();
            abort_unless($pending,404);
            abort_unless($pending->status==='pending',409,'This request has already been reviewed.');
            abort_unless(($pending->api_version ?? '2.0')==='4.0',422,'Resubmit using api_version: 4.0 and the six-section options.');
            $sync=app(\App\Services\Web\SituationReportSync::class)->requireReady($pending->event_id ?: ($data['event_id'] ?? ''));
        }
        $result = DB::transaction(function () use ($request, $id, $data, $situations, $files, $sync) {
            $row = DB::table('external_report_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'pending', 409, 'This request has already been reviewed. Refresh the inbox.');
            $operator = $request->user()?->name ?: $request->user()?->getAuthIdentifier();
            $update = ['status' => $data['decision'], 'decision_note' => $data['note'] ?? null,
                'decided_by' => $operator, 'decided_at' => now(), 'updated_at' => now()];
            if ($data['decision'] === 'accepted') {
                abort_unless(($row->api_version ?? '2.0') === '4.0', 422, 'This pending request uses an older report template. Reject it and ask the partner to resubmit with api_version: 4.0 and current report options.');
                $eventId = $row->event_id ?: ($data['event_id'] ?? null);
                abort_unless($eventId, 422, 'Select the disaster event to include in this report.');
                $sitioId = $row->sitio_id ?? 'all';
                $response = $sitioId === 'all' ? $situations->eventSummary($eventId) : $situations->eventSummary($eventId, $sitioId);
                abort_unless($response->getStatusCode() === 200, 422, 'The requested disaster event is unavailable.');
                $summary = $response->getData(true)['data']['summary'];
                $summary=app(\App\Services\Web\SituationReportSync::class)->apply($summary,$sync);
                $summary['schema_version'] = '4.0';
                $summary['operator_inputs'] = array_intersect_key($data, array_flip(SituationReportSections::INPUTS));
                $summary['actions_text'] = $data['actions_text'] ?? '';
                $summary['report']['report_type'] = $data['sitrep_type'] ?? $summary['report']['report_type'] ?? 'progress';
                $summary['report']['approved_by'] = null;
                $summary['included_sections'] = json_decode($row->sections, true);
                $summary['report']['report_number'] = app(SituationReportQuery::class)->nextReportNumber($eventId);
                $summary['report']['prepared_by'] = self::REPORT_PREPARER;
                $summary['report']['reviewed_by'] = $operator;
                $update['event_id'] = $eventId;
                $update['approved_summary'] = json_encode($summary);
                $update['file'] = json_encode($this->generateFile($summary, $row->format, $files));
            }
            DB::table('external_report_requests')->where('id', $id)->update($update);
            return $this->present(DB::table('external_report_requests')->where('id', $id)->first());
        });
        return response()->json(['data' => $result]);
    }

    public function refreshFile(string $id, ReportFileService $files)
    {
        $row = DB::transaction(function () use ($id, $files) {
            $row = DB::table('external_report_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'accepted', 409, 'Accept this request before preparing a file.');
            $file = json_decode($row->file ?? 'null', true);
            if (!$file || \Carbon\Carbon::parse($file['expires_at'])->isPast()) {
                DB::table('external_report_requests')->where('id', $id)->update([
                    'file' => json_encode($this->generateFile(json_decode($row->approved_summary, true), $row->format, $files)), 'updated_at' => now(),
                ]);
            }
            return DB::table('external_report_requests')->where('id', $id)->first();
        });
        return response()->json(['data' => $this->present($row)]);
    }

    private function generateFile(array $summary, string $format, ReportFileService $files): array
    {
        if ($format === 'pdf') return $files->generateSituationPdf($summary);
        return $files->generateSituationSpreadsheet($summary, $format);
    }

    private function present(object $row): array
    {
        $file = json_decode($row->file ?? 'null', true);
        if ($file && \Carbon\Carbon::parse($file['expires_at'])->isPast()) {
            $file['download_url'] = null;
            $file['expired'] = true;
        }
        return ['api_version'=>$row->api_version ?? '2.0', 'id' => $row->id, 'prepared_by' => self::REPORT_PREPARER, 'source_system' => $row->source_system, 'reference' => $row->reference,
            'event_id' => $row->event_id, 'sitio_id' => $row->sitio_id ?? 'all', 'sections' => json_decode($row->sections, true), 'format' => $row->format,
            'purpose' => $row->purpose, 'status' => $row->status, 'decision_note' => $row->decision_note,
            'decided_by' => $row->decided_by, 'decided_at' => $row->decided_at, 'created_at' => $row->created_at, 'file' => $file];
    }
}
