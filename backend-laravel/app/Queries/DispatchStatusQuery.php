<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

class DispatchStatusQuery
{
    private ?array $labels = null;

    public function label(string $key): ?string
    {
        $this->labels ??= DB::table('dispatch_statuses')->pluck('label', 'status_key')->all();
        return $this->labels[$key] ?? null;
    }

    public function options(): array
    {
        return DB::table('dispatch_statuses')->where('is_active', true)->where('is_alias', false)
            ->orderBy('sort_order')->get()->map(fn ($row) => [
                'key' => $row->status_key, 'status_code' => $row->status_code, 'label' => $row->label,
                'is_open' => (bool) $row->is_open, 'allow_create' => (bool) $row->allow_create,
                'allow_mobile' => (bool) $row->allow_mobile,
            ])->all();
    }

    public function keys(?bool $open = null): array
    {
        return DB::table('dispatch_statuses')->when($open !== null, fn ($q) => $q->where('is_open', $open))
            ->pluck('status_key')->all();
    }
}
