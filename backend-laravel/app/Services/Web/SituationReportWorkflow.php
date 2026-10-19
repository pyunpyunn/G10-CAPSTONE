<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use App\Presenters\SituationReportPresenter;
use App\Queries\SituationReportQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class SituationReportWorkflow
{
    public function __construct(
        private SituationReportQuery $query,
        private SituationReportPresenter $presenter,
        private \App\Services\Shared\BarangayProfileService $barangayProfile,
    ) {}

    public function store(Request $request, array $validated): JsonResponse
    {
        $event = $this->query->findEvent($validated['event_id']);
        if (! $event) return response()->json(['message' => 'Disaster event was not found.'], 404);

        $report = DB::transaction(function () use ($request, $validated, $event): array {
            $now = now();
            $supersedes = !empty($validated['supersedes_sit_rep_id']) ? $this->query->findReport($validated['supersedes_sit_rep_id']) : null;
            if (!empty($validated['supersedes_sit_rep_id']) && (!$supersedes || (string) $supersedes->disaster_id !== (string) $event->event_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['supersedes_sit_rep_id'=>'Select a saved report from the same event.']);
            }
            $sitRepId = $this->query->nextId('situation_reports', 'sit_rep_id');
            $reportNumber = $this->query->nextReportNumber($event->event_id);
            $summary = $this->presenter->buildSummary($event, $this->query->summarySources($event->event_id), $this->barangayProfile->current(), [
                ...array_intersect_key($validated, array_flip(\App\Support\SituationReportSections::INPUTS)),
                'supersedes' => $supersedes ? ['sit_rep_id'=>$supersedes->sit_rep_id,'report_number'=>$supersedes->report_number] : null,
                'sitrep_type' => $validated['sitrep_type'] ?? 'progress', 'approved_by' => $validated['approved_by'] ?? null,
                'report_number' => $reportNumber, 'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null, 'prepared_by' => $validated['prepared_by'] ?? $request->user()?->name,
                'reviewed_by' => $validated['reviewed_by'] ?? null, 'actions_text' => $validated['actions_text'] ?? '',
                'included_sections' => $validated['included_sections'] ?? array_keys(\App\Support\SituationReportSections::LABELS),
                'generated_at' => $now->toDateTimeString(),
            ]);
            $summary=app(SituationReportSync::class)->apply($summary,$validated['_external_sync'] ?? null);
            $scope = app(SituationHouseholdScope::class)->apply($summary, $validated);
            $summary = $scope;
            DB::table('situation_reports')->insert([
                ...$this->metadataColumns($summary),
                'sit_rep_id' => $sitRepId, 'report_number' => $reportNumber, 'disaster_id' => $event->event_id,
                'created_by_admin_id' => $request->user()?->user_id, 'household_id' => null,
                'summary' => json_encode($summary, JSON_UNESCAPED_SLASHES), 'report_status' => $validated['report_status'] ?? 'generated',
                'reviewed_by_user_id' => null, 'reviewed_at' => null,
                'escalated_to' => null, 'is_archived' => 0,
                'generated_at' => $now, 'archived_at' => null, 'updated_at' => $now,
            ]);
            $this->writeAuditLog($request, 'generate', (string) $sitRepId, $summary);
            return $this->presenter->formatSavedReport($this->query->findReport($sitRepId), true);
        });

        return response()->json(['message' => 'SitRep snapshot generated and locked.', 'data' => ['report' => $report]], 201);
    }

    public function createClosureSnapshot(string $eventId, ?string $adminId, Carbon $endedAt, string $closureNote): ?int
    {
        if (! Schema::hasTable('situation_reports')) return null;
        $event = $this->query->findEvent($eventId);
        if (! $event) return null;
        $sitRepId = $this->query->nextId('situation_reports', 'sit_rep_id');
        $reportNumber = $this->query->nextReportNumber($eventId);
        $summary = $this->presenter->buildSummary($event, $this->query->summarySources($eventId), $this->barangayProfile->current(), [
            'report_number' => $reportNumber, 'period_start' => $event->started_at, 'period_end' => $endedAt->toDateTimeString(),
            'prepared_by' => \App\Models\User::query()->where('user_id', $adminId)->first()?->name, 'reviewed_by' => null, 'actions_text' => $closureNote,
            'included_sections' => array_keys(\App\Support\SituationReportSections::LABELS), 'generated_at' => $endedAt->toDateTimeString(),
        ]);
        DB::table('situation_reports')->insert([
            ...$this->metadataColumns($summary),
                'sit_rep_id' => $sitRepId, 'report_number' => $reportNumber, 'disaster_id' => $event->event_id,
            'created_by_admin_id' => $adminId, 'household_id' => null, 'summary' => json_encode($summary, JSON_UNESCAPED_SLASHES),
            'report_status' => 'generated', 'reviewed_by_user_id' => null, 'reviewed_at' => null,
            'escalated_to' => null, 'is_archived' => 0, 'generated_at' => $endedAt,
            'archived_at' => null, 'updated_at' => $endedAt,
        ]);
        return $sitRepId;
    }

    private function metadataColumns(array $summary): array
    {
        // Supports staged deploys and older installations; the JSON snapshot remains authoritative.
        if (!Schema::hasColumn('situation_reports', 'schema_version')) return [];
        return ['schema_version' => \App\Support\SituationReportSections::VERSION, 'sitrep_type' => $summary['report']['report_type'],
            'approved_by' => $summary['report']['approved_by'], 'operator_inputs' => json_encode($summary['operator_inputs'])];
    }

    private function writeAuditLog(Request $request, string $action, string $reportId, array $summary): void
    {
        if (! Schema::hasTable('audit_logs')) return;
        AuditLog::query()->create([
            'user_id' => $request->user()?->user_id, 'role_key' => $request->user()?->role?->role_key,
            'module' => 'situation_reporting', 'action' => $action, 'reference_table' => 'situation_reports',
            'reference_id' => $reportId, 'old_values' => null,
            'new_values' => json_encode(['report_number' => $summary['report']['report_number'] ?? null, 'event_id' => $summary['event']['event_id'] ?? null], JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255), 'created_at' => now(),
        ]);
    }
}







