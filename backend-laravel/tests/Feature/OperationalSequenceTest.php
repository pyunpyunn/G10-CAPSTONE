<?php

namespace Tests\Feature;

use App\Services\Shared\OperationalSequence;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class OperationalSequenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('operational_sequences')->insert(['sequence_key' => 'incident_archives', 'next_value' => 8]);
        Schema::create('resource_requests', function (Blueprint $table): void {
            $table->string('request_id')->primary();
            $table->timestamp('released_for_tracking_at')->nullable();
        });
    }

    public function test_sequence_increments_within_a_transaction(): void
    {
        $sequence = app(OperationalSequence::class);
        $ids = DB::transaction(fn (): array => [$sequence->nextIncidentArchiveId(), $sequence->nextIncidentArchiveId()]);

        $this->assertSame([8, 9], $ids);
        $this->assertSame(10, (int) DB::table('operational_sequences')->value('next_value'));
    }

    public function test_failure_rolls_back_sequence_increment(): void
    {
        $sequence = app(OperationalSequence::class);

        try {
            DB::transaction(function () use ($sequence): void {
                $sequence->nextIncidentArchiveId();
                throw new RuntimeException('Simulated archive insert failure');
            });
            $this->fail('The simulated failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated archive insert failure', $exception->getMessage());
        }

        $this->assertSame(8, (int) DB::table('operational_sequences')->value('next_value'));
    }

    public function test_sequence_rejects_allocation_outside_transaction(): void
    {
        $this->expectException(LogicException::class);
        app(OperationalSequence::class)->nextIncidentArchiveId();
    }

    public function test_tracking_references_advance_on_a_locked_daily_row(): void
    {
        $sequence = app(OperationalSequence::class);
        $references = DB::transaction(fn (): array => [
            $sequence->nextTrackingReference(),
            $sequence->nextTrackingReference(),
        ]);

        $this->assertSame([
            'TA-'.now()->format('Ymd').'-001',
            'TA-'.now()->format('Ymd').'-002',
        ], $references);
    }

    public function test_numeric_id_uses_existing_max_once_and_rolls_back_on_failure(): void
    {
        Schema::create('device_tokens', fn (Blueprint $table) => $table->unsignedBigInteger('id')->primary());
        DB::table('device_tokens')->insert(['id' => 13]);
        $sequence = app(OperationalSequence::class);

        try {
            DB::transaction(function () use ($sequence): void {
                $this->assertSame(14, $sequence->nextNumericId('device_tokens', 'id'));
                throw new RuntimeException('Simulated device write failure');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated device write failure', $exception->getMessage());
        }

        $this->assertSame(14, DB::transaction(fn (): int => $sequence->nextNumericId('device_tokens', 'id')));
        $this->assertSame(15, DB::transaction(fn (): int => $sequence->nextNumericId('device_tokens', 'id')));
    }

    public function test_formatted_evacuation_id_keeps_prefix_and_rolls_back(): void
    {
        Schema::create('evacuation_records', fn (Blueprint $table) => $table->string('evacuation_id')->primary());
        DB::table('evacuation_records')->insert(['evacuation_id' => 'EV-9']);
        $sequence = app(OperationalSequence::class);

        try {
            DB::transaction(function () use ($sequence): void {
                $this->assertSame('EV-10', $sequence->nextEvacuationId());
                throw new RuntimeException('Simulated check-in failure');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated check-in failure', $exception->getMessage());
        }

        $this->assertSame('EV-10', DB::transaction(fn (): string => $sequence->nextEvacuationId()));
        $this->assertSame('EV-11', DB::transaction(fn (): string => $sequence->nextEvacuationId()));
    }
}
