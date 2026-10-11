<?php

namespace App\Services\Web;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class ArchiveDeletionWorkflow
{
    private const DELETE_MAP = [
        'disaster-events' => ['table' => 'disaster_events', 'key' => 'event_id'],
        'household-status-logs' => ['table' => 'household_status_logs', 'key' => 'status_log_id'],
        'dispatch-logs' => ['table' => 'responder_assignments', 'key' => 'assignment_id'],
        'radio-communication-logs' => ['table' => 'responder_communication_logs', 'key' => 'communication_id'],
        'resource-requests' => ['table' => 'resource_requests', 'key' => 'request_id'],
        'situation-reports' => ['table' => 'situation_reports', 'key' => 'sit_rep_id'],
    ];

    public function deleteSelected(Request $request): JsonResponse
    {
        $validated = $request->validate(['category' => ['required', 'string'], 'ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required']]);
        $category = (string) $validated['category'];
        if (! array_key_exists($category, self::DELETE_MAP)) return response()->json(['message' => 'Select a valid archive log category before deleting.'], 422);
        ['table' => $table, 'key' => $key] = self::DELETE_MAP[$category];
        if (! Schema::hasTable($table)) return response()->json(['message' => "The {$table} table is not available right now."], 503);
        $ids = collect($validated['ids'])->map(fn (mixed $id): string => trim((string) $id))->filter()->unique()->values()->all();
        if (empty($ids)) return response()->json(['message' => 'Select at least one archive log before deleting.'], 422);
        try {
            $deletedCount = $category === 'disaster-events' && Schema::hasColumn($table, 'deleted_at')
                ? DB::table($table)->whereIn($key, $ids)->update(['deleted_at' => now()])
                : DB::table($table)->whereIn($key, $ids)->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Selected logs cannot be deleted because another table is still linked to them. Remove the related child records first or keep them in a saved archive group.'], 409);
        }
        return response()->json(['message' => 'Selected archive logs deleted forever.', 'data' => ['category' => $category, 'deleted_count' => $deletedCount]]);
    }
}







