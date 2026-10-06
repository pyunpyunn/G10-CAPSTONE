<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\EvacuationCenter;
use App\Models\HouseholdDisaster;
use App\Models\HouseholdStatusLog;
use App\Models\ResponderAssignment;
use App\Models\ResourceRequest;
use App\Models\SituationReport;
use App\Models\WeatherLog;
use App\Queries\DashboardQuery;
use App\Services\Shared\OperationalSequence;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class DashboardEventClosureWorkflow
{
    public function __construct(private DashboardQuery $query, private SituationReportService $situationReports, private OperationalSequence $sequence) {}

    public function releaseEndedEventReferences(): int
    {
        if (! Schema::hasTable('evacuation_centers') || ! Schema::hasColumn('evacuation_centers', 'current_event_id') || ! Schema::hasTable('disaster_events')) return 0;
        $updates = ['current_event_id' => null];
        if (Schema::hasColumn('evacuation_centers', 'updated_at')) $updates['updated_at'] = now();
        return DB::table('evacuation_centers')
            ->whereIn('current_event_id', function ($query): void {
                $query->select('event_id')->from('disaster_events')
                    ->where(fn ($events) => $events->whereNotNull('ended_at')->orWhereNotNull('deleted_at'));
            })->update($updates);
    }

    public function close(Request $request): ?array
    {
        return DB::transaction(function () use ($request): ?array {
            $event = DisasterEvent::query()->whereNull('deleted_at')->whereNull('ended_at')
                ->orderByDesc('started_at')->lockForUpdate()->first();
            if (! $event) return null;

            $endedAt = now();
            $closureNote = $this->buildClosureNote($event);
            $user = $request->user();
            DisasterEvent::query()->where('event_id', $event->event_id)->update(['ended_at' => $endedAt, 'updated_at' => $endedAt]);
            $released = $this->clearCurrentEventReferences($event->event_id, $endedAt);
            $this->archiveSituationReports($event->event_id, $endedAt);
            $sitRepId = $this->situationReports->createClosureSnapshot($event->event_id, $user?->user_id, $endedAt, $closureNote);
            $this->writeIncidentArchive($event, $user?->user_id, $closureNote, $endedAt);
            $this->closeHouseholdReporting($event->event_id, $endedAt);
            $closedAssignments = $this->closeOpenAssignments($event->event_id, $endedAt);
            $this->writeAuditLog($event, $user, $endedAt, $closureNote, $request, $released, $sitRepId, $closedAssignments);

            return [
                'event_id' => $event->event_id,
                'name' => $event->name,
                'ended_at' => $endedAt->format('M d, Y g:i A'),
                'released_evacuation_centers' => $released,
                'closure_sitrep_id' => $sitRepId,
                'closed_assignments' => $closedAssignments,
            ];
        });
    }

    private function buildClosureNote(object $event): string
    {
        $summary = $this->query->getHouseholdSummary($event->event_id);
        $counts = [
            HouseholdStatusLog::query()->where('disaster_id', $event->event_id)->count(),
            ResponderAssignment::query()->where('disaster_id', $event->event_id)->count(),
            ResourceRequest::query()->where('source_reference', $event->event_id)->count(),
            WeatherLog::query()->where('disaster_id', $event->event_id)->count(),
            DisasterBroadcast::query()->where('disaster_id', $event->event_id)->count(),
            SituationReport::query()->where('disaster_id', $event->event_id)->count(),
        ];
        return sprintf('Closed active event "%s". Summary: %s/%s households reported; safe total %s; evacuated %s; unsafe %s; status logs %s; dispatch assignments %s; resource requests %s; weather snapshots %s; broadcasts %s; situation reports %s.',
            $event->name, $summary['reported'], $summary['total'], $summary['safe_total'], $summary['evacuated'], $summary['unsafe'], ...$counts);
    }

    private function writeIncidentArchive(object $event, ?string $userId, string $note, Carbon $endedAt): void
    {
        DB::table('incident_archives')->insert([
            'archive_id' => $this->sequence->nextIncidentArchiveId(),
            'archive_type' => 'disaster_event_log', 'disaster_id' => $event->event_id,
            'reference_table' => 'disaster_events', 'reference_id' => $event->event_id,
            'archived_by_admin_id' => $userId, 'archive_note' => $note, 'archived_at' => $endedAt, 'created_at' => $endedAt,
        ]);
    }

    private function archiveSituationReports(string $eventId, Carbon $endedAt): void
    {
        SituationReport::query()->where('disaster_id', $eventId)->where(fn ($q) => $q->whereNull('is_archived')->orWhere('is_archived', 0))
            ->update(['is_archived' => 1, 'archived_at' => $endedAt, 'updated_at' => $endedAt]);
    }

    private function clearCurrentEventReferences(string $eventId, Carbon $endedAt): int
    {
        if (! Schema::hasTable('evacuation_centers') || ! Schema::hasColumn('evacuation_centers', 'current_event_id')) return 0;
        $updates = ['current_event_id' => null];
        if (Schema::hasColumn('evacuation_centers', 'updated_at')) $updates['updated_at'] = $endedAt;
        return EvacuationCenter::query()->where('current_event_id', $eventId)->update($updates);
    }

    private function closeHouseholdReporting(string $eventId, Carbon $endedAt): void
    {
        if (! Schema::hasTable('household_disasters') || ! Schema::hasColumn('household_disasters', 'needs_dispatch')) return;
        $updates = ['needs_dispatch' => 0];
        if (Schema::hasColumn('household_disasters', 'updated_at')) $updates['updated_at'] = $endedAt;
        HouseholdDisaster::query()->where('disaster_id', $eventId)->update($updates);
    }

    private function closeOpenAssignments(string $eventId, Carbon $endedAt): int
    {
        if (! Schema::hasTable('responder_assignments')) return 0;
        $query = ResponderAssignment::query()->where('disaster_id', $eventId);
        if (Schema::hasColumn('responder_assignments', 'status')) $query->whereNotIn('status', ['completed', 'cancelled']);
        $updates = [];
        if (Schema::hasColumn('responder_assignments', 'status')) $updates['status'] = 'cancelled';
        if (Schema::hasColumn('responder_assignments', 'outcome_notes')) $updates['outcome_notes'] = 'Automatically closed because the disaster event ended.';
        if (Schema::hasColumn('responder_assignments', 'updated_at')) $updates['updated_at'] = $endedAt;
        return $updates ? $query->update($updates) : 0;
    }

    private function writeAuditLog(object $event, mixed $user, Carbon $endedAt, string $note, Request $request, int $released, ?int $sitRepId, int $closedAssignments): void
    {
        AuditLog::query()->insert([
            'user_id' => $user?->user_id, 'role_key' => $user?->role?->role_key,
            'module' => 'disaster_broadcasting', 'action' => 'close_active_event',
            'reference_table' => 'disaster_events', 'reference_id' => $event->event_id,
            'old_values' => json_encode(['ended_at' => $event->ended_at]),
            'new_values' => json_encode(['ended_at' => $endedAt->toDateTimeString(), 'archive_note' => $note,
                'released_evacuation_centers' => $released, 'closure_sitrep_id' => $sitRepId, 'closed_assignments' => $closedAssignments]),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => $endedAt,
        ]);
    }
}







