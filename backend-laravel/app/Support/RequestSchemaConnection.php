<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class RequestSchemaConnection
{
    public function __construct(private string $name) {}

    public function hasTable(string $table): bool
    {
        return $this->check('table', $table, null);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->check('column', $table, $column);
    }

    /** @return list<string> */
    public function getColumnListing(string $table): array
    {
        return $this->columns($table);
    }

    public function check(string $kind, string $table, ?string $column): bool
    {
        $key = json_encode([$this->name, $kind, $table, $column]);

        return RequestSchema::remember($key, fn (): bool => $kind === 'table'
            ? Schema::connection($this->name)->hasTable($table)
            : Schema::connection($this->name)->hasColumn($table, $column));
    }

    /** @return list<string> */
    public function columns(string $table): array
    {
        $key = json_encode([$this->name, 'columns', $table]);

        return RequestSchema::remember($key, fn (): array => Schema::connection($this->name)->getColumnListing($table));
    }
}
