<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;

class RequestQueryProfile
{
    public int $count = 0;
    public float $totalMs = 0;

    /** @var list<array{ms: float, sql: string}> */
    public array $slowest = [];

    public function record(QueryExecuted $query): void
    {
        $this->count++;
        $this->totalMs += $query->time;

        // Keep placeholders intact: interpolated bindings may contain personal data.
        $this->slowest[] = ['ms' => round($query->time, 2), 'sql' => substr($query->sql, 0, 500)];
        usort($this->slowest, fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);
        if (count($this->slowest) > 3) {
            array_pop($this->slowest);
        }
    }
}
