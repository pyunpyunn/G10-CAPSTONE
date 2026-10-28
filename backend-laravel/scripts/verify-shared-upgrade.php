<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('shared_resq');
$db->statement("SET SESSION time_zone = '+00:00'");
$schema = $db->getSchemaBuilder();
$path = $argv[2] ?? null;
if (! in_array($argv[1] ?? '', ['capture','verify'], true) || ! $path) throw new RuntimeException('Use capture|verify with a private snapshot path.');
if ($argv[1] === 'capture') {
    $manifest = ['database' => $db->getDatabaseName(), 'tables' => []];
    foreach ($schema->getTables($db->getDatabaseName()) as $table) {
        if (isset($table['type']) && ! in_array($table['type'], ['BASE TABLE', 'table'], true)) continue;
        $name = $table['name'];
        $columns = $schema->getColumnListing($name);
        $hashColumns = $name === 'operational_sequences' ? array_values(array_diff($columns, ['next_value'])) : $columns;
        $hashes = $db->table($name)->select($hashColumns)->get()->map(fn ($row) => hash('sha256', json_encode((array) $row, JSON_THROW_ON_ERROR)))->sort()->values()->all();
        $manifest['tables'][$name] = ['columns' => $columns, 'hash_columns' => $hashColumns, 'rows' => $hashes];
    }
    $manifest['sequence_minimums'] = $db->table('operational_sequences')->pluck('next_value', 'sequence_key')->all();
    file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
    echo json_encode(['captured_tables' => count($manifest['tables']), 'database' => $manifest['database']]).PHP_EOL;
} else {
    $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if ($manifest['database'] !== $db->getDatabaseName()) throw new RuntimeException('Snapshot database mismatch.');
    $errors = [];
    foreach ($manifest['tables'] as $name => $record) {
        if (! $schema->hasTable($name)) { $errors[] = $name.':missing_table'; continue; }
        if (array_diff($record['columns'], $schema->getColumnListing($name))) { $errors[] = $name.':missing_columns'; continue; }
        $current = $db->table($name)->select($record['hash_columns'])->get()->map(fn ($row) => hash('sha256', json_encode((array) $row, JSON_THROW_ON_ERROR)))->countBy()->all();
        foreach (array_count_values($record['rows']) as $hash => $count) {
            if (($current[$hash] ?? 0) < $count) { $errors[] = $name.':original_row_changed_or_missing'; break; }
        }
    }
    foreach ($manifest['sequence_minimums'] as $key => $minimum) {
        if ((int) $db->table('operational_sequences')->where('sequence_key', $key)->value('next_value') < (int) $minimum) $errors[] = 'operational_sequences:counter_regressed';
    }
    echo json_encode(['verified_tables' => count($manifest['tables']), 'errors' => $errors], JSON_THROW_ON_ERROR).PHP_EOL;
    exit($errors ? 1 : 0);
}
