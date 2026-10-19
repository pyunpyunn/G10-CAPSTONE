<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARENT_KEYS = [
        'rescue_teams' => ['team_id', 'navigation_rescue_teams_team_id_unique'],
        'responder_assignments' => ['assignment_id', 'navigation_assignments_assignment_id_unique'],
    ];

    public function up(): void
    {
        foreach (self::PARENT_KEYS as $table => [$column, $index]) {
            if (! Schema::hasTable($table) || ! in_array($column, Schema::getColumnListing($table), true)) {
                throw new RuntimeException("Navigation requires the {$table}.{$column} column.");
            }

            $hasUniqueKey = collect(Schema::getIndexes($table))->contains(
                fn (array $existing): bool => ($existing['unique'] ?? false) && ($existing['columns'] ?? []) === [$column],
            );
            if ($hasUniqueKey) {
                continue;
            }

            if (DB::table($table)->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException("Cannot add navigation foreign keys: {$table}.{$column} contains duplicate values.");
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($column, $index));
        }
    }

    public function down(): void
    {
        foreach (self::PARENT_KEYS as $table => [, $index]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index));
            }
        }
    }
};