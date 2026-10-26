<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite' && ! \Illuminate\Support\Facades\Schema::hasTable('operational_sequences')) {
            \Illuminate\Support\Facades\Schema::create('operational_sequences', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->string('sequence_key')->primary();
                $table->unsignedBigInteger('next_value');
                $table->string('sequence_prefix')->nullable();
            });
        }
    }
}


