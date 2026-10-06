<?php

namespace Tests\Feature;

use App\Support\RequestSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequestSchemaTest extends TestCase
{
    public function test_schema_capabilities_are_cached_for_only_one_request(): void
    {
        Schema::create('schema_cache_fixture', fn (Blueprint $table) => $table->id());
        $this->app->instance('request', Request::create('/api/v1/test'));

        $queries = 0;
        DB::listen(function () use (&$queries): void { $queries++; });

        $this->assertTrue(RequestSchema::hasTable('schema_cache_fixture'));
        $afterFirst = $queries;
        $this->assertTrue(RequestSchema::hasTable('schema_cache_fixture'));
        $this->assertSame($afterFirst, $queries);

        $this->app->instance('request', Request::create('/api/v1/test-again'));
        $this->assertTrue(RequestSchema::hasTable('schema_cache_fixture'));
        $this->assertGreaterThan($afterFirst, $queries);
    }
}
