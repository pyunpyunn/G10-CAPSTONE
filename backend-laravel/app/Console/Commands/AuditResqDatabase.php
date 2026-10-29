<?php

namespace App\Console\Commands;

use App\Services\Shared\ResqDatabaseIntegrity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDOException;

class AuditResqDatabase extends Command
{
    protected $signature = 'resq:database-audit {--database= : Named configured connection; defaults to the active database}';

    protected $description = 'Read-only schema and Eloquent compatibility checks for Resqperation';

    public function handle(ResqDatabaseIntegrity $integrity): int
    {
        $name = $this->option('database') ?: config('database.default');
        if (! config("database.connections.$name")) {
            $this->error('Unknown configured database connection.');

            return self::FAILURE;
        }
        $db = DB::connection($name);
        if ($db->getDriverName() !== 'mysql') {
            $this->error('This audit requires MySQL information_schema; no changes were made.');

            return self::FAILURE;
        }
        try {
            $plan = $integrity->plan($db->getPdo());
            $sharedWarnings = $integrity->sharedReferenceWarnings($db->getPdo());
            $schema = $db->getSchemaBuilder();
            $missing = [];
            $warnings = [];
            $mapped = [];
            foreach (config('resq_database.modules', []) as $module => $classes) {
                foreach ($classes as $class) {
                    if (! class_exists('App\\Models\\'.$class)) {
                        $missing[] = "$module: Eloquent model $class is missing";

                        continue;
                    }
                    $model = new ('App\\Models\\'.$class);
                    $table = $model->getTable();
                    if (! $schema->hasTable($table)) {
                        $missing[] = "$module: $table";

                        continue;
                    }
                    $columns = $schema->getColumnListing($table);
                    $aliases = method_exists($model, 'schemaColumnAliases') ? $model->schemaColumnAliases() : [];
                    $writeColumns = array_map(fn ($column) => $aliases[$column] ?? $column, $model->getFillable());
                    foreach (array_diff($writeColumns, $columns) as $column) {
                        $warnings[] = "$class: $table.$column is absent; do not write it without a schema check";
                    }
                    if (! in_array($model->getKeyName(), $columns, true)) {
                        $missing[] = "$class: primary key column is absent";
                    }
                    if ($model->usesTimestamps()) {
                        foreach ([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()] as $timestamp) {
                            if ($timestamp && ! in_array($timestamp, $columns, true)) {
                                $missing[] = "$class: timestamp $timestamp is absent";
                            }
                        }
                    }
                    $mapped[$module][$table] = $class;
                }
            }
            foreach (config('resq_database.query_builder_tables', []) as $table => $keys) {
                if (! $schema->hasTable($table)) {
                    $missing[] = "Query builder: $table is missing";
                    continue;
                }
                foreach (array_diff($keys, $schema->getColumnListing($table)) as $key) {
                    $missing[] = "$table.$key: composite key column is missing";
                }
            }
            $this->line(json_encode([
                'connection' => $name, 'database' => $db->getDatabaseName(),
                'mapped' => $mapped, 'missing' => $missing, 'warnings' => $warnings,
                'query_builder_tables' => config('resq_database.query_builder_tables'),
                'shared_reference_warnings' => $sharedWarnings,
                'pending_schema_statements' => count($plan['sql']), 'blocked' => $plan['blocked'],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $missing || $plan['sql'] || $plan['blocked'] || $warnings || $sharedWarnings ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) {
            if ($error instanceof \Error) {
                $this->error('Audit implementation error: '.$error->getMessage());

                return self::FAILURE;
            }
            $previous = $error instanceof PDOException ? $error : $error->getPrevious();
            $this->error('Database audit could not complete; no changes were made. Error type: '.$error::class.'; code: '.($previous?->getCode() ?? $error->getCode()));

            return self::FAILURE;
        }
    }
}
