<?php

namespace Tests\Feature;

use App\Services\Web\BroadcastProviderHistory;
use App\Services\Web\NotificationArchive;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationArchiveTest extends TestCase
{
    private const PROVIDER_ID = '335fd3f1-b00e-427c-b72c-7c574cf8a4d5';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('notif_id');
            foreach (NotificationArchive::REQUIRED as $column) $table->text($column)->nullable();
        });
        Schema::create('recurrence_types', function (Blueprint $table): void {
            $table->integer('type_id')->primary();
        });
        DB::table('recurrence_types')->insert(['type_id' => 1]);
        Schema::create('disaster_broadcasts', function (Blueprint $table): void {
            $table->increments('broadcast_id');
            $table->string('disaster_id');
        });
        (require database_path('migrations/2026_10_28_000001_add_notification_archive_fields.php'))->up();
    }

    private function completeRecord(): array
    {
        return ['source_key' => 'broadcast-1001', 'message' => 'A real saved recurring notice.', 'sent_by' => 'USR-1',
            'evacuation_event_id' => 'EVAC-1', 'evacuation_center_id' => 'CENTER-1',
            'scheduled_at' => '2026-10-27 08:00:00', 'is_recurring' => true, 'recurrence_type_id' => 1,
            'recurrence_end_at' => '2026-10-29 08:00:00', 'last_sent_at' => '2026-10-27 08:00:00',
            'created_at' => '2026-10-27 07:00:00', 'channel' => 'onesignal', 'status' => 'sent', 'target_filter' => 'barangay',
            'onesignal_id' => self::PROVIDER_ID, 'onesignal_ids_json' => json_encode([self::PROVIDER_ID]),
            'feed_payload_json' => json_encode(['id' => 'broadcast-1001', 'type' => 'Broadcast'])];
    }

    public function test_incomplete_records_are_paused_without_placeholder_rows(): void
    {
        $record = $this->completeRecord();
        $record['evacuation_center_id'] = '';
        $record['recurrence_end_at'] = null;
        $record['is_recurring'] = false;
        $errors = app(NotificationArchive::class)->persist($record);
        $this->assertArrayHasKey('evacuation_center_id', $errors);
        $this->assertArrayHasKey('recurrence_end_at', $errors);
        $this->assertArrayNotHasKey('is_recurring', $errors);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    public function test_complete_records_are_idempotent_and_keep_every_provider_id(): void
    {
        Event::fake([\App\Events\NotificationFeedChanged::class]);
        $archive = app(NotificationArchive::class);
        $record = $this->completeRecord();
        $ids = [self::PROVIDER_ID, 'ff13f7ba-b2f5-4f3d-93cc-e0093e96a654'];
        $record['onesignal_ids_json'] = json_encode($ids);
        $this->assertSame([], $archive->persist($record));
        Event::assertDispatchedTimes(\App\Events\NotificationFeedChanged::class, 1);
        $this->assertSame([], $archive->persist($record));
        Event::assertDispatchedTimes(\App\Events\NotificationFeedChanged::class, 1);
        $this->assertDatabaseCount('notifications', 1);
        $row = DB::table('notifications')->first();
        $this->assertSame($ids, json_decode($row->onesignal_ids_json, true));
        foreach (NotificationArchive::REQUIRED as $column) $this->assertNotNull($row->$column);
        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_write_and_invalid_ids_and_recurrence_are_rejected(): void
    {
        $archive = app(NotificationArchive::class);
        $record = $this->completeRecord();
        $this->assertSame([], $archive->persist($record, false));
        $this->assertDatabaseCount('notifications', 0);
        $record['onesignal_id'] = 'provider-1';
        $record['recurrence_type_id'] = 999;
        $errors = $archive->persist($record);
        $this->assertArrayHasKey('onesignal_id', $errors);
        $this->assertArrayHasKey('recurrence_type_id', $errors);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_api_history_matches_explicit_broadcast_and_event_not_just_title(): void
    {
        config(['services.onesignal.app_id' => 'test-app', 'services.onesignal.api_key' => 'test-key',
            'services.onesignal.base_url' => 'https://api.onesignal.com']);
        DB::table('disaster_broadcasts')->insert(['broadcast_id' => 1001, 'disaster_id' => 'EVENT-1']);
        Http::fake(['api.onesignal.com/notifications*' => Http::response(['total_count' => 3, 'notifications' => [
            ['id' => self::PROVIDER_ID, 'data' => ['broadcast_id' => '1001', 'event_id' => 'EVENT-1', 'type' => 'disaster_broadcast']],
            ['id' => 'ff13f7ba-b2f5-4f3d-93cc-e0093e96a654', 'data' => ['broadcast_id' => '1001', 'event_id' => 'WRONG', 'type' => 'disaster_broadcast']],
            ['id' => 'provider-1', 'data' => ['broadcast_id' => '1001', 'event_id' => 'EVENT-1', 'type' => 'disaster_broadcast']],
        ]])]);
        $history = new class extends BroadcastProviderHistory {
            protected function logPaths(): array { return []; }
        };
        $dry = $history->recover(false);
        $this->assertTrue($dry['api_complete']);
        $this->assertNull(DB::table('disaster_broadcasts')->value('push_provider_ids_json'));
        $written = $history->recover(true);
        $this->assertSame([self::PROVIDER_ID], $written['broadcasts'][0]['onesignal_ids']);
        $this->assertSame([self::PROVIDER_ID], json_decode(DB::table('disaster_broadcasts')->value('push_provider_ids_json'), true));
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        $this->assertDatabaseCount('notifications', 0);
    }
}
