<?php

namespace App\Services\Archive;

use App\Presenters\ArchivePresenter;
use App\Services\Shared\OperationalSequence;
use App\Support\ArchiveGroupPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;

class ArchiveGroupManager
{
    private const CATEGORIES = ['disaster-events', 'household-status-logs', 'dispatch-logs', 'radio-communication-logs', 'resource-requests', 'situation-reports'];

    public function __construct(private ArchivePresenter $presenter, private OperationalSequence $sequence, private ArchiveGroupPayload $payload) {}

    public function savedRecordIds(string $category): array
    {
        if (! Schema::hasTable('incident_archives')) return [];
        return DB::table('incident_archives')->where('archive_type', 'saved_log_group')
            ->where('reference_table', $category)->pluck('archive_note')
            ->flatMap(fn (?string $note): array => collect($this->payload->records($note))
                ->map(fn (array $record): string => trim((string) $record['id']))->all())
            ->unique()->values()->all();
    }

    public function savedGroups(): JsonResponse
    {
        $unavailableMessage = $this->storageMessage();
        if ($unavailableMessage !== '') return response()->json(['success' => false, 'message' => $unavailableMessage, 'data' => ['groups' => []]], 503);
        $groups = DB::table('incident_archives')->where('archive_type', 'saved_log_group')->orderByDesc('archived_at')->orderByDesc('archive_id')->limit(50)->get()->map(fn (object $row): array => $this->presenter->savedGroup($row, self::CATEGORIES))->values()->all();
        return response()->json(['success' => true, 'message' => 'Saved archive groups loaded.', 'data' => ['groups' => $groups]]);
    }

    public function store(Request $request): JsonResponse
    {
        $unavailableMessage = $this->storageMessage();
        if ($unavailableMessage !== '') return response()->json(['success' => false, 'message' => $unavailableMessage], 503);
        $validated = $request->validate(['category' => ['required', 'string', Rule::in(self::CATEGORIES)], 'records' => ['required', 'array', 'min:1', 'max:100'], 'records.*.id' => ['required']]);
        $now = now();
        $groupId = 'AG-'.$now->format('Ymd-His').'-'.strtoupper(substr(md5((string) microtime(true)), 0, 6));
        $records = collect($validated['records'])->map(fn (mixed $record): array => is_array($record) ? $record : [])->filter(fn (array $record): bool => trim((string) ($record['id'] ?? '')) !== '')->values()->all();
        if (empty($records)) return response()->json(['success' => false, 'message' => 'Select at least one valid archive record before saving a group.'], 422);
        $archiveNote = $this->payload->encode($validated['category'], $records);
        $group = DB::transaction(function () use ($request, $validated, $groupId, $archiveNote, $now): array {
            $archiveId = $this->sequence->nextIncidentArchiveId();
            $insertData = ['archive_id' => $archiveId, 'archive_type' => 'saved_log_group', 'reference_table' => $validated['category'], 'reference_id' => $groupId, 'archive_note' => $archiveNote, 'archived_at' => $now];
            if (Schema::hasColumn('incident_archives', 'archived_by_admin_id')) $insertData['archived_by_admin_id'] = $request->user()?->user_id;
            if (Schema::hasColumn('incident_archives', 'created_at')) $insertData['created_at'] = $now;
            if (Schema::hasColumn('incident_archives', 'disaster_id')) $insertData['disaster_id'] = null;
            DB::table('incident_archives')->insert($insertData);
            return $this->presenter->savedGroup(DB::table('incident_archives')->where('archive_id', $archiveId)->first(), self::CATEGORIES);
        }, 3);
        return response()->json(['success' => true, 'message' => 'Selected archive logs saved to a database group.', 'data' => ['group' => $group]], 201);
    }

    public function delete(string $groupId): JsonResponse
    {
        $unavailableMessage = $this->storageMessage();
        if ($unavailableMessage !== '') return response()->json(['success' => false, 'message' => $unavailableMessage], 503);
        DB::table('incident_archives')->where('archive_type', 'saved_log_group')->where('reference_id', $groupId)->delete();
        return response()->json(['success' => true, 'message' => 'Saved archive group deleted.', 'data' => ['group_id' => $groupId]]);
    }

    public function deleteRecord(string $groupId, string $recordId): JsonResponse
    {
        $unavailableMessage = $this->storageMessage();
        if ($unavailableMessage !== '') return response()->json(['success' => false, 'message' => $unavailableMessage], 503);
        $row = DB::table('incident_archives')->where('archive_type', 'saved_log_group')->where('reference_id', $groupId)->first();
        if (! $row) return response()->json(['success' => false, 'message' => 'Saved archive group was not found.'], 404);
        $group = $this->presenter->savedGroup($row, self::CATEGORIES);
        $remainingRecords = collect($group['records'])->filter(fn (array $record): bool => (string) ($record['id'] ?? '') !== (string) $recordId)->values()->all();
        $query = DB::table('incident_archives')->where('archive_type', 'saved_log_group')->where('reference_id', $groupId);
        if (empty($remainingRecords)) $query->delete();
        else $query->update(['archive_note' => $this->payload->encode($group['category'], $remainingRecords)]);
        return response()->json(['success' => true, 'message' => 'Saved archive log removed from the group.', 'data' => ['group_id' => $groupId, 'record_id' => $recordId]]);
    }

    private function storageMessage(): string
    {
        if (! Schema::hasTable('incident_archives')) return 'Saved archive groups cannot be used because the incident_archives table is not available.';
        foreach (['archive_id', 'archive_type', 'reference_table', 'reference_id', 'archive_note', 'archived_at'] as $column) {
            if (! Schema::hasColumn('incident_archives', $column)) return 'Saved archive groups need the approved incident_archives archive_type/reference columns before they can save to the shared database.';
        }
        return '';
    }
}







