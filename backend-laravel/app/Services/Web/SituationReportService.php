<?php

namespace App\Services\Web;

use App\Presenters\SituationReportPresenter;
use App\Queries\SituationReportQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SituationReportService
{
    public function __construct(
        private SituationReportQuery $query,
        private SituationReportPresenter $presenter,
        private SituationReportWorkflow $workflow,
        private \App\Services\Shared\BarangayProfileService $barangayProfile,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'barangay_profile' => $this->barangayProfile->current(),
            'events' => $this->query->eventOptions()->map(fn (object $event): array => $this->presenter->formatEventOption($event))->values(),
            'reports' => $this->query->savedReports()->map(fn (object $report): ?array => $this->presenter->formatSavedReport($report))->values(),
            'note' => 'Select a disaster event first. The SitRep preview is generated from the selected event snapshot only.',
        ]]);
    }

    public function sitios(): JsonResponse
    {
        return response()->json(['data' => ['sitios' => $this->query->sitioOptions()]]);
    }

    public function eventSummary(string $eventId, ?string $sitioId = null): JsonResponse
    {
        $event = $this->query->findEvent($eventId);
        if (! $event) return response()->json(['message' => 'Disaster event was not found.'], 404);
        $sitio = $sitioId !== null ? $this->query->sitioOptions()->firstWhere('sitio_id', $sitioId) : null;
        if ($sitioId !== null && !$sitio) throw ValidationException::withMessages(['sitio_id' => 'Select a registered sitio in this barangay.']);
        $summary = $this->presenter->buildSummary($event, $this->query->summarySources($eventId, $sitioId), $this->barangayProfile->current());
        if ($sitio) {
            $summary['event']['sitio_id'] = $sitio->sitio_id;
            $summary['event']['sitio_name'] = $sitio->sitio_name;
            $summary['event']['scope'] = $sitio->sitio_name;
            $summary['event']['coverage'] = $sitio->sitio_name.' · '.$summary['household']['total'].' registered family units';
            $summary['scope_note'] = 'Weather observations are barangay-wide. Resource requests and action notes without a sitio link are excluded.';
        }
        return response()->json(['data' => ['summary' => $summary]]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data=$request->validate(['event_id'=>'required|string|max:255']);
        $response=$this->eventSummary($data['event_id']);
        if ($response->getStatusCode()!==200) return $response;
        $sync=app(SituationReportSync::class)->run($data['event_id']);
        $summary=app(SituationReportSync::class)->apply($response->getData(true)['data']['summary'],$sync);
        return response()->json(['data'=>['summary'=>$summary,'sync'=>app(SituationReportSync::class)->status($sync)]])->header('Cache-Control','no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $rules = array_fill_keys(\App\Support\SituationReportSections::INPUTS, 'nullable|string|max:20000');
        $validated = $request->validate([...$rules,
            'supersedes_sit_rep_id' => 'nullable|integer|min:1',
            'sitrep_type' => 'nullable|in:flash,initial,progress,final', 'approved_by' => 'nullable|string|max:150',
            'sync_id'=>'nullable|uuid', 'event_id' => ['required', 'string', 'max:255'], 'report_number' => ['nullable', 'string', 'max:80'],
            'period_start' => ['nullable', 'date'], 'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'prepared_by' => ['nullable', 'string', 'max:150'], 'reviewed_by' => ['nullable', 'string', 'max:150'],
            'actions_text' => ['nullable', 'string'], 'included_sections' => ['nullable', 'array', 'min:1', 'max:6'],
            'included_sections.*' => ['string', 'in:I,II,III,IV,V,VI,VII'],
            'sitio_id' => ['nullable', 'string', 'max:120'], 'purok_id' => ['nullable', 'string', 'max:120'], 'include_households' => ['sometimes', 'boolean'],
            'report_status' => ['nullable', 'string', 'in:draft,generated,reviewed,archived'],
        ], ['event_id.required' => 'Select a disaster event before generating a SitRep.', 'period_end.after_or_equal' => 'Period end must be after the period start.']);
        $validated['_external_sync']=app(SituationReportSync::class)->requireReady($validated['event_id'],$validated['sync_id'] ?? null);
        return $this->workflow->store($request, $validated);
    }

    public function show(int $sitRepId): JsonResponse
    {
        $report = $this->query->findReport($sitRepId);
        if (! $report) return response()->json(['message' => 'Situation report was not found.'], 404);
        $data = $this->presenter->formatSavedReport($report, true);
        $data['summary'] = \App\Support\SituationReportSnapshot::forRendering($data['summary']);
        $data['submissions'] = \Illuminate\Support\Facades\Schema::hasTable('situation_report_submissions')
            ? \App\Models\SituationReportSubmission::query()->where('sit_rep_id',$sitRepId)->orderByDesc('submitted_at')->get() : [];
        return response()->json(['data' => ['report' => $data]]);
    }

    public function recordSubmission(Request $request, int $sitRepId): JsonResponse
    {
        $report=$this->query->findReport($sitRepId);
        abort_unless($report,404,'Saved situation report was not found.');
        $data=$request->validate([
            'submitted_at'=>'required|date|before_or_equal:now',
            'delivered_by'=>'required|string|max:150', 'received_by'=>'required|string|max:150',
        ]);
        $entry=\App\Models\SituationReportSubmission::query()->create([
            ...$data, 'sit_rep_id'=>$sitRepId,
            'recorded_by'=>(string) $request->user()->getAuthIdentifier(),
        ]);
        return response()->json(['message'=>'Manual submission to CCDRRMO recorded.','data'=>['submission'=>$entry]],201);
    }

    public function createClosureSnapshot(string $eventId, ?string $adminId, Carbon $endedAt, string $closureNote): ?int
    {
        return $this->workflow->createClosureSnapshot($eventId, $adminId, $endedAt, $closureNote);
    }

    public function pdf(int $sitRepId): JsonResponse
    {
        return app(ReportGenerationService::class)->generate(Request::create('/', 'POST', [
            'report_type' => 'situation', 'sit_rep_id' => $sitRepId, 'format' => 'pdf',
        ]));
    }
}
