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

    public function eventSummary(string $eventId): JsonResponse
    {
        $event = $this->query->findEvent($eventId);
        if (! $event) return response()->json(['message' => 'Disaster event was not found.'], 404);
        $summary = $this->presenter->buildSummary($event, $this->query->summarySources($eventId), $this->barangayProfile->current());
        return response()->json(['data' => ['summary' => $summary]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:255'], 'report_number' => ['nullable', 'string', 'max:80'],
            'period_start' => ['nullable', 'date'], 'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'prepared_by' => ['nullable', 'string', 'max:150'], 'reviewed_by' => ['nullable', 'string', 'max:150'],
            'actions_text' => ['nullable', 'string'], 'included_sections' => ['nullable', 'array'],
            'included_sections.*' => ['string', 'in:I,II,III,IV,V,VI,VII,VIII'],
            'report_status' => ['nullable', 'string', 'in:draft,generated,reviewed,archived'],
        ], ['event_id.required' => 'Select a disaster event before generating a SitRep.', 'period_end.after_or_equal' => 'Period end must be after the period start.']);
        return $this->workflow->store($request, $validated);
    }

    public function show(int $sitRepId): JsonResponse
    {
        $report = $this->query->findReport($sitRepId);
        if (! $report) return response()->json(['message' => 'Situation report was not found.'], 404);
        return response()->json(['data' => ['report' => $this->presenter->formatSavedReport($report, true)]]);
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
