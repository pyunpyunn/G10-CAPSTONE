<?php

namespace App\Services\Mobile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerMobileSupport
{
    public function responderForUser($user): ?object
    {
        if (! $user || ! Schema::hasTable('responders')) {
            return null;
        }

        $query = DB::table('responders as r')->where('r.user_id', $user->user_id);
        $columns = ['r.*'];
        if (Schema::hasTable('rescue_teams')) {
            $query->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id');
            array_push($columns, 'rt.team_name', 'rt.team_code', 'rt.team_type');
        } else {
            array_push($columns, DB::raw('NULL as team_name'), DB::raw('NULL as team_code'), DB::raw('NULL as team_type'));
        }

        return $query->select($columns)->first();
    }

    public function activeEvent(): ?object
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DB::table('disaster_events as de');
        $columns = ['de.event_id', 'de.name', 'de.started_at'];
        if (Schema::hasColumn('disaster_events', 'ended_at')) {
            $query->whereNull('de.ended_at');
        }
        if (Schema::hasTable('disaster_types')) {
            $query->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id');
            $columns[] = 'dt.type_name';
        } else {
            $columns[] = DB::raw('NULL as type_name');
        }
        if (Schema::hasTable('severity_levels')) {
            $query->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id');
            array_push($columns, 'sl.severity_label', 'sl.severity_key');
        } else {
            array_push($columns, DB::raw('NULL as severity_label'), DB::raw('NULL as severity_key'));
        }

        return $query->orderByDesc('de.started_at')->first($columns);
    }

    public function resolveHouseholdStatusId(string $statusKey): ?int
    {
        if (! Schema::hasTable('household_statuses')) {
            return null;
        }

        $query = DB::table('household_statuses')->where('status_key', $statusKey);
        if (Schema::hasColumn('household_statuses', 'status_label')) {
            $query->orWhere('status_label', $statusKey);
        }
        if (Schema::hasColumn('household_statuses', 'status_name')) {
            $query->orWhere('status_name', $statusKey);
        }

        return $query->value('status_id');
    }

    public function nextId(string $table, string $column): int
    {
        return app(\App\Services\Shared\OperationalSequence::class)->nextNumericId($table, $column);
    }

    public function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);
        return collect($data)->filter(fn ($value, string $key): bool => in_array($key, $columns, true))->all();
    }

    public function writeAuditLog(Request $request, string $action, string $table, string $referenceId, array $values): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert($this->filterColumns('audit_logs', [
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'rescuer_mobile',
            'action' => $action,
            'reference_table' => $table,
            'reference_id' => $referenceId,
            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]));
    }
}







