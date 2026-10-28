<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'device_tokens' => [
            'mobile_device_household_seen' => ['household_id', 'last_seen_at'],
        ],
        'trusted_households' => [
            'mobile_trusted_request_created' => ['requesting_household_id', 'created_at'],
            'mobile_trusted_pending_created' => ['trusted_household_id', 'validation_status', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $existingIndexes = Schema::getIndexes($table);

            foreach ($indexes as $name => $fields) {
                if (array_diff($fields, $columns)) {
                    continue;
                }

                $covered = collect($existingIndexes)->contains(
                    fn (array $index): bool => array_slice($index['columns'], 0, count($fields)) === $fields
                );

                if (! $covered && ! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($fields, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
