<?php

namespace Tests\Feature;

use App\Events\NotificationFeedChanged;
use App\Events\OperationsChanged;
use App\Http\Middleware\AtomicRealtimeWrite;
use App\Models\User;
use App\Services\Shared\RealtimeOutbox;
use App\Services\Shared\RealtimeReadCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RealtimeDurabilityTest extends TestCase
{
    public function test_shared_read_cache_reuses_sources_and_invalidates_before_broadcast(): void
    {
        config(['realtime.read_cache' => true, 'realtime.cache_store' => 'array']);
        $cache = app(RealtimeReadCache::class);
        $reads = 0;
        $read = function () use (&$reads) {
            return ++$reads;
        };
        $this->assertSame(1, $cache->remember('source', $read));
        $this->assertSame(1, $cache->remember('source', $read));
        DB::transaction(fn () => DB::table('household_status_logs')->insert(['status' => 'safe']));
        Event::listen(OperationsChanged::class, function () use ($cache, $read): void {
            $this->assertSame(2, $cache->remember('source', $read));
        });
        $outbox = app(RealtimeOutbox::class);
        $outbox->deliver($outbox->claim());
        $this->assertSame(2, $reads);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['realtime.durable' => true, 'realtime.connection' => 'sqlite', 'realtime.batch_size' => 500]);
        (require database_path('migrations/2026_10_09_000004_create_realtime_outbox.php'))->up();
        (require database_path('migrations/2026_10_09_000005_create_report_idempotency.php'))->up();
        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });
    }

    public function test_source_and_outbox_commit_and_rollback_together_without_broadcasting(): void
    {
        Event::fake([OperationsChanged::class, NotificationFeedChanged::class]);
        DB::beginTransaction();
        DB::table('household_status_logs')->insert(['status' => 'safe']);
        $this->assertDatabaseCount('realtime_outbox', 1);
        DB::rollBack();
        $this->assertDatabaseCount('household_status_logs', 0);
        $this->assertDatabaseCount('realtime_outbox', 0);
        DB::transaction(fn () => DB::table('household_status_logs')->insert(['status' => 'unsafe']));
        $this->assertDatabaseCount('realtime_outbox', 1);
        Event::assertNotDispatched(OperationsChanged::class);
        Event::assertNotDispatched(NotificationFeedChanged::class);
    }

    public function test_burst_is_combined_into_one_topic_broadcast_and_one_feed_broadcast(): void
    {
        Event::fake([OperationsChanged::class, NotificationFeedChanged::class]);
        DB::transaction(function (): void {
            for ($i = 0; $i < 100; $i++) {
                DB::table('household_status_logs')->insert(['status' => 'safe']);
            }
        });
        $outbox = app(RealtimeOutbox::class);
        $token = $outbox->claim();
        $this->assertNotNull($token);
        $this->assertNull($outbox->claim());
        $outbox->deliver($token);
        $outbox->deliver($token);
        Event::assertDispatchedTimes(OperationsChanged::class, 1);
        Event::assertDispatchedTimes(NotificationFeedChanged::class, 1);
        Event::assertDispatched(OperationsChanged::class, fn ($event) => $event->topics === ['households', 'mapping', 'dispatch', 'field-reports']);
        $this->assertSame(0, DB::table('realtime_outbox')->whereNull('delivered_at')->count());
    }

    public function test_socket_failure_leaves_changes_pending_for_retry(): void
    {
        DB::transaction(fn () => DB::table('household_status_logs')->insert(['status' => 'safe']));
        $outbox = app(RealtimeOutbox::class);
        $token = $outbox->claim();
        $fail = true;
        Event::listen(NotificationFeedChanged::class, function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('socket unavailable');
            }
        });
        try {
            $outbox->deliver($token);
            $this->fail('Expected a failed broadcast');
        } catch (RuntimeException $exception) {
            $this->assertSame('socket unavailable', $exception->getMessage());
        }
        $this->assertDatabaseHas('realtime_outbox', ['claim_token' => $token, 'delivered_at' => null]);
        $fail = false;
        $outbox->deliver($token);
        $this->assertSame(0, DB::table('realtime_outbox')->whereNull('delivered_at')->count());
    }

    public function test_expired_worker_lease_can_be_reclaimed_and_old_job_cannot_acknowledge_new_claim(): void
    {
        Event::fake([OperationsChanged::class, NotificationFeedChanged::class]);
        DB::transaction(fn () => DB::table('household_status_logs')->insert(['status' => 'safe']));
        $outbox = app(RealtimeOutbox::class);
        $old = $outbox->claim();
        DB::table('realtime_outbox')->update(['claimed_until' => time() - 1]);
        $new = $outbox->claim();
        $this->assertNotSame($old, $new);
        $outbox->deliver($old);
        Event::assertNotDispatched(OperationsChanged::class);
        $outbox->deliver($new);
        Event::assertDispatchedTimes(OperationsChanged::class, 1);
    }

    public function test_queue_failure_releases_the_lease_without_losing_the_outbox(): void
    {
        config(['realtime.queue_connection' => 'missing-test-queue']);
        DB::transaction(fn () => DB::table('household_status_logs')->insert(['status' => 'safe']));
        try {
            app(RealtimeOutbox::class)->dispatchBatch();
            $this->fail('Expected queue failure');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseHas('realtime_outbox', ['claim_token' => null, 'delivered_at' => null]);
        }
        $this->assertDatabaseCount('household_status_logs', 1);
    }

    public function test_same_report_key_replays_response_and_different_payload_is_rejected(): void
    {
        $middleware = app(AtomicRealtimeWrite::class);
        $calls = 0;
        $save = function () use (&$calls) {
            $calls++;
            DB::table('household_status_logs')->insert(['status' => 'safe']);

            return response()->json(['report_id' => 123], 201);
        };
        $first = $middleware->handle($this->reportRequest('USER-1'), $save);
        $retry = $middleware->handle($this->reportRequest('USER-1'), $save);
        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame($first->getContent(), $retry->getContent());
        $this->assertSame('true', $retry->headers->get('Idempotency-Replayed'));
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('household_status_logs', 1);
        $this->assertDatabaseCount('realtime_outbox', 1);
        $conflict = $middleware->handle($this->reportRequest('USER-1', 'unsafe'), $save);
        $this->assertSame(409, $conflict->getStatusCode());
        $middleware->handle($this->reportRequest('USER-2'), $save);
        $this->assertSame(2, $calls);
    }

    public function test_failed_report_rolls_back_the_response_key_source_and_outbox(): void
    {
        $middleware = app(AtomicRealtimeWrite::class);
        $response = $middleware->handle($this->reportRequest('USER-1'), function () {
            DB::table('household_status_logs')->insert(['status' => 'safe']);

            return response()->json(['message' => 'rejected'], 422);
        });
        $this->assertSame(422, $response->getStatusCode());
        foreach (['report_idempotency', 'household_status_logs', 'realtime_outbox'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function reportRequest(string $userId, string $status = 'safe'): Request
    {
        $request = Request::create('/api/v1/household/status', 'POST', ['status_key' => $status]);
        $request->headers->set('Idempotency-Key', 'test-report-key-00000001');
        $request->setUserResolver(fn () => (new User(['user_id' => $userId]))->forceFill(['id' => $userId]));

        return $request;
    }
}
