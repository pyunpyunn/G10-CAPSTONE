<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DatabaseConsistencyResponseTest extends TestCase
{
    public function test_missing_schema_is_reported_without_exposing_sql_or_bindings(): void
    {
        Route::get('/api/consistency-test', function (): void {
            $previous = new \PDOException('private database details');
            $previous->errorInfo = ['42S02', 1146, 'private database details'];
            throw new QueryException('sqlite', 'select * from private_table where token = ?', ['private-token'], $previous);
        });
        $response = $this->getJson('/api/consistency-test')->assertStatus(503)
            ->assertJsonPath('code', 'database_schema_mismatch')->assertJsonStructure(['request_id']);
        $this->assertStringNotContainsString('private', $response->getContent());
    }

    public function test_foreign_key_conflicts_are_reported_as_recoverable_conflicts(): void
    {
        Route::get('/api/consistency-test', function (): void {
            $previous = new \PDOException('private database details');
            $previous->errorInfo = ['23000', 1452, 'private database details'];
            throw new QueryException('sqlite', 'insert into private_table values (?)', ['private-value'], $previous);
        });
        $response = $this->getJson('/api/consistency-test')->assertStatus(409)
            ->assertJsonPath('code', 'database_integrity_conflict');
        $this->assertStringNotContainsString('private', $response->getContent());
    }

    public function test_audit_rejects_an_unknown_connection_without_changing_the_active_connection(): void
    {
        $connection = config('database.default');
        $this->artisan('resq:database-audit', ['--database' => 'unconfigured_test_connection'])->assertFailed();
        $this->assertSame($connection, config('database.default'));
    }
}
