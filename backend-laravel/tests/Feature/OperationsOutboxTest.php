<?php

namespace Tests\Feature;

use App\Jobs\RefreshWeatherSnapshot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OperationsOutboxTest extends TestCase
{
    public function test_operations_job_is_rolled_back_with_local_write_and_persists_after_commit(): void
    {
        Schema::create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        try {
            DB::transaction(function (): void {
                RefreshWeatherSnapshot::dispatch(null)->onConnection('operations_outbox')->onQueue('operations');
                throw new RuntimeException('cancel local write');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('cancel local write', $exception->getMessage());
        }
        $this->assertDatabaseCount('jobs', 0);

        DB::transaction(function (): void {
            RefreshWeatherSnapshot::dispatch(null)->onConnection('operations_outbox')->onQueue('operations');
        });
        $this->assertDatabaseHas('jobs', ['queue' => 'operations']);
    }
}
