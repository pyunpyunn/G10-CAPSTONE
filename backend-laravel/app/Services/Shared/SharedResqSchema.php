<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Additive compatibility upgrade for the existing shared database. */
class SharedResqSchema
{
    public function run(): array
    {
        if (DB::getDriverName() !== 'mysql') throw new RuntimeException('The shared schema upgrade requires MySQL.');
        $spec = json_decode(file_get_contents(database_path('shared-resq-schema.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (array_keys($spec['columns']) as $table) {
            if (! Schema::hasTable($table)) throw new RuntimeException('Required shared table is missing: '.$table);
        }
        foreach (['rescue_teams' => 'team_id', 'responder_assignments' => 'assignment_id'] as $table => $key) {
            if (DB::table($table)->select($key)->groupBy($key)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Duplicate parent IDs require review before the shared upgrade: '.$table);
            }
        }
        (require database_path('migrations/2026_10_18_000030_add_navigation_parent_indexes.php'))->up();
        $changes = [];
        foreach ($spec['columns'] as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                    $changes[] = $table.'.'.$column;
                }
            }
        }
        $method = collect(Schema::getColumns('password_reset_requests'))->firstWhere('name', 'verification_method');
        if (! str_contains($method['type'], "'sms_otp'")) {
            if (! str_starts_with($method['type'], 'enum(')) throw new RuntimeException('Unexpected recovery verification_method type.');
            $type = substr($method['type'], 0, -1).",'sms_otp')";
            DB::statement("ALTER TABLE `password_reset_requests` MODIFY COLUMN `verification_method` {$type} NOT NULL");
            $changes[] = 'password_reset_requests.verification_method:sms_otp';
        }
        foreach ($spec['create_tables'] as $table => $ddl) {
            if (! Schema::hasTable($table)) {
                DB::statement($ddl);
                $changes[] = $table;
            }
        }
        foreach ([
            ['password_reset_requests', 'resq_otp_subject_unique', ['otp_subject_key'], true],
            ['notifications', 'resq_advisory_fingerprint_unique', ['advisory_fingerprint'], true],
            ['notifications', 'resq_notification_source_unique', ['source_key'], true],
            ['device_tokens', 'resq_device_uuid_lookup', ['device_uuid'], false],
            ['device_tokens', 'resq_household_device_lookup', ['household_id', 'device_uuid'], false],
            ['device_tokens', 'resq_team_user_lookup', ['team_id', 'user_id'], false],
        ] as [$table, $name, $columns, $unique]) {
            $exists = collect(Schema::getIndexes($table))->contains(fn ($index) => $index['columns'] === $columns && (! $unique || $index['unique']));
            if (! $exists) {
                $keys = implode('`,`', $columns);
                $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
                DB::statement("ALTER TABLE `{$table}` ADD {$kind} `{$name}` (`{$keys}`)");
                $changes[] = $name;
            }
        }
        return $changes;
    }
}
