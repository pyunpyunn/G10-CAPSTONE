<?php

namespace App\Queries;

use App\Presenters\RescuerHouseholdStatusPresenter;
use App\Services\Mobile\RescuerMobileSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class RescuerFieldReportQuery
{
    public function __construct(private RescuerMobileSupport $support, private RescuerHouseholdStatusPresenter $presenter) {}

    public function fieldReports(?int $responderId, ?string $userId, array $filters = []): Collection
    {
        if (! Schema::hasTable('household_status_logs')) return collect();
        return $this->reportQuery($responderId, $userId, $filters)->limit(50)->get()->values();
    }

    public function paginateAdmin(array $filters, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        abort_unless(Schema::hasTable('household_status_logs'), 503, 'Field report records are currently unavailable.');
        $assignmentFilter = true;
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if (! empty($filters['status_id'])) {
            $assignmentFilter = false;
        } elseif ($status !== 'all' && Schema::hasTable('household_statuses')
            && ! in_array($status, app(DispatchStatusQuery::class)->keys(), true)
            && DB::table('household_statuses')->where('status_key', $status)->exists()) {
            $assignmentFilter = false;
        }
        return $this->reportQuery(null, null, array_merge($filters, ['assignment_status_filter' => $assignmentFilter]))->paginate($perPage);
    }

    private function reportQuery(?int $responderId, ?string $userId, array $filters): \Illuminate\Database\Query\Builder
    {
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $statusId = trim((string) ($filters['status_id'] ?? ''));
        $eventId = trim((string) ($filters['event_id'] ?? ''));
        $query = DB::table('household_status_logs as hsl');
        $hasAssignmentStatus = false;
        $assignmentStatusExpression = 'assignment.status';
        $columns = [
            $this->optional('household_status_logs', 'status_log_id', 'status_log_id', 'hsl'),
            $this->optional('household_status_logs', 'household_id', 'household_id', 'hsl'),
            $this->optional('household_status_logs', 'status_id', 'status_id', 'hsl'),
            $this->optional('household_status_logs', 'disaster_id', 'disaster_id', 'hsl'),
            $this->optional('household_status_logs', 'latitude', 'latitude', 'hsl'),
            $this->optional('household_status_logs', 'longitude', 'longitude', 'hsl'),
            $this->optional('household_status_logs', 'battery_level', 'battery_level', 'hsl'),
            $this->optional('household_status_logs', 'notes', 'notes', 'hsl'),
            $this->optional('household_status_logs', 'submitted_at', 'submitted_at', 'hsl'),
        ];
        if (Schema::hasColumn('household_status_logs', 'source')) $query->where('hsl.source', 'responder_field_report');
        if (Schema::hasColumn('household_status_logs', 'responder_id') && Schema::hasTable('responders')) {
            $query->leftJoin('responders as reporter', 'reporter.responder_id', '=', 'hsl.responder_id');
            $columns[] = $this->optional('responders', 'full_name', 'reporter_name', 'reporter');
            if (Schema::hasColumn('responders', 'team_id') && Schema::hasTable('rescue_teams')) {
                $query->leftJoin('rescue_teams as reporter_team', 'reporter_team.team_id', '=', 'reporter.team_id');
                $columns[] = $this->optional('rescue_teams', 'team_name', 'reporter_team_name', 'reporter_team');
            }
        }
        if (Schema::hasTable('household_statuses')) {
            $query->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hsl.status_id');
            $columns[] = 'hs.status_key';
            $columns[] = $this->statusLabel('hs');
        } else {
            array_push($columns, DB::raw('NULL as status_key'), DB::raw('NULL as status_label'));
        }
        if (Schema::hasTable('households')) {
            $query->leftJoin('households as h', 'h.household_id', '=', 'hsl.household_id');
            $columns[] = $this->optional('households', 'household_code', 'household_code', 'h');
            $columns[] = $this->firstExisting('households', ['household_head_name', 'head_name', 'household_name'], 'household_head_name', 'h');
        } else {
            array_push($columns, DB::raw('NULL as household_code'), DB::raw('NULL as household_head_name'));
        }
        if (Schema::hasTable('responder_field_reports')
            && Schema::hasColumn('responder_field_reports', 'status_log_id')
            && Schema::hasColumn('responder_field_reports', 'assignment_id')) {
            $query->leftJoin('responder_field_reports as rfr', 'rfr.status_log_id', '=', 'hsl.status_log_id');
            $columns[] = 'rfr.assignment_id';
            if (Schema::hasTable('responder_assignments')) {
                $query->leftJoin('responder_assignments as assignment', 'assignment.assignment_id', '=', 'rfr.assignment_id');
                if (Schema::hasColumn('responder_assignments', 'route_notes') && Schema::hasColumn('household_status_logs', 'responder_id')) {
                    $assignmentStatusExpression = $this->reportTaskStatusExpression();
                }
                $columns[] = DB::raw($assignmentStatusExpression.' as assignment_status');
                $hasAssignmentStatus = true;
            }
            $columns[] = $this->optional('responder_field_reports', 'incident_type', 'incident_type', 'rfr');
            $columns[] = $this->optional('responder_field_reports', 'incident_type_other', 'incident_type_other', 'rfr');
            $columns[] = $this->optional('responder_field_reports', 'property_damage', 'property_damage', 'rfr');
            $columns[] = $this->optional('responder_field_reports', 'death_count', 'death_count', 'rfr');
            $columns[] = $this->optional('responder_field_reports', 'injured_count', 'injured_count', 'rfr');
        } else {
            array_push($columns, DB::raw('NULL as assignment_id'), DB::raw('NULL as incident_type'),
                DB::raw('NULL as incident_type_other'), DB::raw('NULL as property_damage'),
                DB::raw('NULL as death_count'), DB::raw('NULL as injured_count'));
        }
        if (! $hasAssignmentStatus) $columns[] = DB::raw('NULL as assignment_status');
        if ($responderId && Schema::hasColumn('household_status_logs', 'responder_id')) $query->where('hsl.responder_id', $responderId);
        elseif ($userId && Schema::hasColumn('household_status_logs', 'submitted_by_user_id')) $query->where('hsl.submitted_by_user_id', $userId);
        if ($eventId !== '' && $eventId !== 'all' && Schema::hasColumn('household_status_logs', 'disaster_id')) $query->where('hsl.disaster_id', $eventId);
        if (! empty($filters['assignment_status_filter'])) {
            if ($status !== '' && $status !== 'all') {
                if ($hasAssignmentStatus) {
                    if ($status === 'unlinked') $query->whereRaw('('.$assignmentStatusExpression.') IS NULL');
                    else {
                        $values = match ($status) {
                        'en_route' => ['en_route', 'en-route'],
                        'on_scene' => ['on_scene', 'on-scene', 'onscene'],
                        default => [$status],
                        };
                        $query->whereRaw('('.$assignmentStatusExpression.') IN ('.implode(',', array_fill(0, count($values), '?')).')', $values);
                    }
                } elseif ($status !== 'unlinked') $query->whereRaw('1 = 0');
            }
        }
        elseif ($statusId !== '' && Schema::hasColumn('household_status_logs', 'status_id')) $query->where('hsl.status_id', $statusId);
        elseif ($status !== '' && $status !== 'all' && Schema::hasTable('household_statuses')) $query->where('hs.status_key', $status);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $searchColumns = [];
            foreach (['household_id', 'notes'] as $column) {
                if (Schema::hasColumn('household_status_logs', $column)) $searchColumns[] = 'hsl.'.$column;
            }
            foreach (['household_code', 'household_head_name', 'head_name', 'household_name'] as $column) {
                if (Schema::hasColumn('households', $column)) $searchColumns[] = 'h.'.$column;
            }
            if ($searchColumns) $query->where(function ($inner) use ($searchColumns, $search) {
                foreach ($searchColumns as $column) $inner->orWhere($column, 'like', "%{$search}%");
            });
        }
        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at') ? 'hsl.submitted_at' : 'hsl.status_log_id';
        return $query->select($columns)->orderByDesc($orderColumn)->orderByDesc('hsl.status_log_id');
    }

    public function summary(?string $eventId = null): array
    {
        if (! Schema::hasTable('household_status_logs')) return ['rows' => []];
        $options = collect($this->statusOptions());
        $counts = [];
        foreach ($options as $option) {
            $query = DB::table('household_status_logs as hsl');
            if (Schema::hasColumn('household_status_logs', 'source')) $query->where('hsl.source', 'responder_field_report');
            if ($eventId && $eventId !== 'all' && Schema::hasColumn('household_status_logs', 'disaster_id')) $query->where('hsl.disaster_id', $eventId);
            if (! empty($option['status_id']) && Schema::hasColumn('household_status_logs', 'status_id')) $query->where('hsl.status_id', $option['status_id']);
            $counts[$option['key']] = (int) $query->count();
        }
        return $this->presenter->summaryRows($options, $counts);
    }

    public function statusOptions(): array
    {
        if (! Schema::hasTable('household_statuses')) return $this->presenter->defaultOptions();
        return $this->presenter->options(DB::table('household_statuses')->orderBy('status_id')->get([
            'status_id', 'status_key', $this->statusLabel('household_statuses'),
        ]));
    }

    public function assignmentStatusOptions(): array
    {
        return array_merge(app(DispatchStatusQuery::class)->options(), [['key' => 'unlinked', 'label' => 'No linked assignment']]);
    }

    private function reportTaskStatusExpression(): string
    {
        // A dispatch can contain multiple rescuers and queued households. The
        // report belongs to its author and household, not the shared HQ status.
        $notes = "CASE WHEN JSON_VALID(assignment.route_notes) THEN assignment.route_notes ELSE '{}' END";
        if (DB::connection()->getDriverName() === 'sqlite') {
            $completedPath = "'$.completed_households.\"' || hsl.responder_id || '\"'";
            $statusPath = "'$.responder_statuses.\"' || hsl.responder_id || '\"'";
            $completed = "EXISTS (SELECT 1 FROM json_each($notes, $completedPath) done WHERE done.value = hsl.household_id)";
            $personal = "json_extract($notes, $statusPath)";
        } else {
            $completedPath = "CONCAT('$.completed_households.\"', hsl.responder_id, '\"')";
            $statusPath = "CONCAT('$.responder_statuses.\"', hsl.responder_id, '\"')";
            $completed = "JSON_CONTAINS(JSON_EXTRACT($notes, $completedPath), JSON_QUOTE(hsl.household_id)) = 1";
            $personal = "NULLIF(JSON_UNQUOTE(JSON_EXTRACT($notes, $statusPath)), 'null')";
        }
        return "CASE WHEN $completed THEN 'completed' ELSE COALESCE($personal, assignment.status) END";
    }

    public function assignmentSummary(?string $eventId): array
    {
        $rows = [];
        foreach ($this->assignmentStatusOptions() as $option) {
            $count = $this->reportQuery(null, null, ['event_id' => $eventId,
                'status' => $option['key'], 'assignment_status_filter' => true])->count();
            $rows[] = array_merge($option, ['count' => $count]);
        }
        return ['rows' => $rows];
    }

    private function optional(string $table, string $column, string $alias, ?string $prefix = null)
    {
        if (Schema::hasColumn($table, $column)) return ($prefix ?: $table).".{$column} as {$alias}";
        return DB::raw("NULL as {$alias}");
    }

    private function firstExisting(string $table, array $candidates, string $alias, ?string $prefix = null)
    {
        foreach ($candidates as $column) if (Schema::hasColumn($table, $column)) return ($prefix ?: $table).".{$column} as {$alias}";
        return DB::raw("NULL as {$alias}");
    }

    private function statusLabel(string $prefix = 'household_statuses')
    {
        if (Schema::hasColumn('household_statuses', 'status_label')) return "{$prefix}.status_label as status_label";
        if (Schema::hasColumn('household_statuses', 'status_name')) return "{$prefix}.status_name as status_label";
        return DB::raw('NULL as status_label');
    }
}



