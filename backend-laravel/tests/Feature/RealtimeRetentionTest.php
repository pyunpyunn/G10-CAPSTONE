<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RealtimeRetentionTest extends TestCase
{
    public function test_pruning_retains_pending_and_recent_messages(): void
    {
        config(['realtime.connection' => 'sqlite']);
        Schema::create('realtime_outbox', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('created_at');
            $table->timestamp('delivered_at')->nullable();
            $table->integer('attempts')->default(0);
        });
        Schema::create('report_idempotency', function (Blueprint $table): void {
            $table->string('scope')->primary();
            $table->timestamp('expires_at');
        });
        DB::table('realtime_outbox')->insert([
            ['id' => 1, 'created_at' => now()->subDays(10), 'delivered_at' => null],
            ['id' => 2, 'created_at' => now()->subDays(10), 'delivered_at' => now()->subDays(8)],
            ['id' => 3, 'created_at' => now()->subDays(2), 'delivered_at' => now()->subDay()],
        ]);
        DB::table('report_idempotency')->insert([
            ['scope' => 'expired', 'expires_at' => now()->subMinute()],
            ['scope' => 'active', 'expires_at' => now()->addHour()],
        ]);
        $this->artisan('realtime:status', ['--prune' => true, '--retention-days' => 7])->assertSuccessful();
        $this->assertSame([1, 3], DB::table('realtime_outbox')->orderBy('id')->pluck('id')->all());
        $this->assertSame(['active'], DB::table('report_idempotency')->pluck('scope')->all());
    }
}
