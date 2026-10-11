<?php

namespace App\Services\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class HouseholdMobileSupport
{
    public function householdId($user): ?string
    {
        return $user?->household_id ? (string) $user->household_id : null;
    }

    public function nextId(string $table, string $column): int
    {
        return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table, $column);
    }

    public function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) return $data;
        $columns = Schema::getColumnListing($table);
        return collect($data)->filter(fn ($value, string $key): bool => in_array($key, $columns, true))->all();
    }

    public function withGeneratedKey(string $table, string $column, array $data, int $generatedId): array
    {
        $filtered = $this->filterColumns($table, $data);
        $filtered[$column] = $generatedId;

        return $filtered;
    }

    public function writeAuditLog(Request $request, string $action, string $table, string $referenceId, array $values): void
    {
        if (! Schema::hasTable('audit_logs')) return;
        DB::table('audit_logs')->insert($this->filterColumns('audit_logs', [
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'household_mobile',
            'action' => $action,
            'reference_table' => $table,
            'reference_id' => $referenceId,
            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]));
    }

    public function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => "The {$table} table is not available yet. Ask the DB member to apply the approved shared schema before this mobile action can save data.",
        ], 503);
    }
}







