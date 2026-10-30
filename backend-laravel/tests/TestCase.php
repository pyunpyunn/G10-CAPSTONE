<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require SQLite :memory:. Bypass cached application config with APP_CONFIG_CACHE pointing to a nonexistent temporary file; never run fixtures against a persistent database.');
        }

        if (config('database.default') === 'sqlite' && ! \Illuminate\Support\Facades\Schema::hasTable('dispatch_statuses')) {
            (require database_path('migrations/2026_10_27_000001_create_dispatch_statuses.php'))->up();
        }
        if (config('database.default') === 'sqlite' && ! \Illuminate\Support\Facades\Schema::hasColumn('dispatch_statuses', 'status_code')) {
            (require database_path('migrations/2026_10_27_000002_add_dispatch_status_codes.php'))->up();
        }

        if (config('database.default') === 'sqlite' && ! \Illuminate\Support\Facades\Schema::hasTable('operational_sequences')) {
            \Illuminate\Support\Facades\Schema::create('operational_sequences', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->string('sequence_key')->primary();
                $table->unsignedBigInteger('next_value');
                $table->string('sequence_prefix')->nullable();
            });
        }
    }
}


