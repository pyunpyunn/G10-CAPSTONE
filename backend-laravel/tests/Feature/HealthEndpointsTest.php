<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    public function test_liveness_does_not_depend_on_the_database(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'live']);
    }

    public function test_readiness_checks_only_the_operational_database_connection(): void
    {
        config(['database.connections.resq_local' => array_merge(
            config('database.connections.sqlite'),
            ['database' => ':memory:'],
        )]);
        DB::purge('resq_local');

        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready'])
            ->assertHeader('X-Request-ID');
    }

    public function test_readiness_returns_safe_503_when_local_database_connection_fails(): void
    {
        config(['database.connections.resq_local' => array_merge(
            config('database.connections.mysql'),
            [
                'host' => '127.0.0.1',
                'port' => 1,
                'database' => 'readiness_test',
                'username' => 'test',
                'password' => 'test',
                'options' => [\PDO::ATTR_TIMEOUT => 1],
            ],
        )]);
        DB::purge('resq_local');

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(503)
            ->assertJsonStructure(['status', 'message', 'request_id'])
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('message', 'Database unreachable.')
            ->assertHeader('X-Request-ID');
    }

    public function test_api_database_connection_exceptions_return_safe_503_json(): void
    {
        $this->app['router']->get('/api/v1/test-database-connection-failure', function () {
            throw new \PDOException('private connection details', 2002);
        });

        $response = $this->getJson('/api/v1/test-database-connection-failure');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Database unreachable.')
            ->assertJsonStructure(['request_id'])
            ->assertDontSee('private connection details')
            ->assertHeader('X-Request-ID');
    }
}
