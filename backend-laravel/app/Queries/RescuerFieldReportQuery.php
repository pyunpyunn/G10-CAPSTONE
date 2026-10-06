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

        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $statusId = trim((string) ($filters['status_id'] ?? ''));
        $eventId = trim((string) ($filters['event_id'] ?? ''));
        $query = DB::table('household_status_logs as hsl');
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
        if ($responderId && Schema::hasColumn('household_status_logs', 'responder_id')) $query->where('hsl.responder_id', $responderId);
        elseif ($userId && Schema::hasColumn('household_status_logs', 'submitted_by_user_id')) $query->where('hsl.submitted_by_user_id', $userId);
        if ($eventId !== '' && Schema::hasColumn('household_status_logs', 'disaster_id')) $query->where('hsl.disaster_id', $eventId);
        if ($statusId !== '' && Schema::hasColumn('household_status_logs', 'status_id')) $query->where('hsl.status_id', $statusId);
        elseif ($status !== '' && $status !== 'all' && Schema::hasTable('household_statuses')) $query->where('hs.status_key', $status);

        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at') ? 'hsl.submitted_at' : 'hsl.status_log_id';
        return $query->orderByDesc($orderColumn)->limit(50)->get($columns)->values();
    }

    public function summary(?string $eventId = null): array
    {
        if (! Schema::hasTable('household_status_logs')) return ['rows' => []];
        $options = collect($this->statusOptions());
        $counts = [];
        foreach ($options as $option) {
            $query = DB::table('household_status_logs as hsl');
            if (Schema::hasColumn('household_status_logs', 'source')) $query->where('hsl.source', 'responder_field_report');
            if ($eventId !== '' && Schema::hasColumn('household_status_logs', 'disaster_id')) $query->where('hsl.disaster_id', $eventId);
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



