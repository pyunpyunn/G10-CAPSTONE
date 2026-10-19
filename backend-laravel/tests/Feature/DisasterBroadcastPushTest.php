<?php

namespace Tests\Feature;

use App\Services\Shared\OneSignalNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DisasterBroadcastPushTest extends TestCase
{
    public function test_missing_android_setup_returns_a_clear_provider_error(): void
    {
        Http::fake(['*' => Http::response([
            'id' => '', 'warnings' => ['You must configure Android notifications in your OneSignal settings if you wish to send messages to Android users.'],
        ], 200)]);
        $result = app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate');
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Firebase Cloud Messaging', $result['errors'][0]);
    }

    public function test_provider_message_ids_and_errors_are_persisted_for_the_broadcast_log(): void
    {
        Schema::create('disaster_broadcasts', function (Blueprint $table): void {
            $table->integer('broadcast_id')->primary();
            $table->string('push_status')->nullable();
            $table->string('channel')->nullable();
            $table->unsignedInteger('push_accepted_count')->nullable();
            $table->text('push_provider_ids_json')->nullable();
            $table->text('push_errors_json')->nullable();
            $table->timestamps();
        });
        DB::table('disaster_broadcasts')->insert(['broadcast_id' => 42]);
        $workflow = app(\App\Services\Web\DisasterBroadcastWorkflow::class);
        $workflow->updateBroadcastPushStatus(42, [
            'status' => 'partial', 'recipient_count' => 2, 'sent_count' => 1,
            'provider_ids' => ['provider-1'], 'errors' => ['One subscription is invalid.'],
        ]);
        $this->assertDatabaseHas('disaster_broadcasts', [
            'broadcast_id' => 42, 'channel' => 'onesignal', 'push_status' => 'onesignal_partial',
            'push_accepted_count' => 1, 'push_errors_json' => '["One subscription is invalid."]',
        ]);
        $workflow->updateBroadcastPushStatus(42, [
            'status' => 'sent', 'recipient_count' => 2, 'sent_count' => 2,
            'provider_ids' => ['provider-1', 'provider-2'], 'errors' => [],
        ]);
        $this->assertDatabaseHas('disaster_broadcasts', [
            'broadcast_id' => 42, 'push_status' => 'onesignal_sent',
            'push_provider_ids_json' => '["provider-1","provider-2"]', 'push_errors_json' => '[]',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.onesignal.app_id' => 'test-app', 'services.onesignal.api_key' => 'test-key']);
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->string('player_id');
            $table->string('platform')->default('android');
            $table->string('app_role');
            $table->string('push_provider');
            $table->string('notification_permission_status');
            $table->boolean('is_active');
        });
        foreach ([['household', 'granted', true], ['rescuer', 'granted', true], ['household', 'denied', true], ['household', 'granted', false]] as $index => $device) {
            DB::table('device_tokens')->insert([
                'player_id' => 'subscription-'.$index, 'app_role' => $device[0],
                'push_provider' => 'onesignal', 'notification_permission_status' => $device[1], 'is_active' => $device[2],
            ]);
        }
    }

    public function test_queue_replays_reuse_audience_and_idempotency_key(): void
    {
        Http::fake(['*' => Http::response(['id' => 'notification-id'], 200)]);
        $service = app(OneSignalNotificationService::class);
        $plan = $service->prepareDelivery(['roles' => ['household']]);
        DB::table('device_tokens')->delete();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $service->sendToMobileDevices('Flood', 'Evacuate', ['delivery_plan' => $plan]);
            $this->assertSame('sent', $result['status']);
            $this->assertSame(1, $result['sent_count']);
        }
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['idempotency_key'] === $plan['keys'][0]
            && $request['priority'] === 10
            && $request['include_subscription_ids'] === ['subscription-0']
            && $request->header('Authorization') === ['Key test-key']);
    }

    public function test_http_success_without_message_id_is_not_sent(): void
    {
        Http::fake(['*' => Http::response(['id' => '', 'errors' => ['No subscribed users']], 200)]);
        $result = app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate');
        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['sent_count']);
    }

    public function test_invalid_subscriptions_are_reported_as_partial(): void
    {
        Http::fake(['*' => Http::response(['id' => 'notification-id', 'errors' => ['invalid_player_ids' => ['subscription-1']]], 200)]);
        $result = app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate');
        $this->assertSame('partial', $result['status']);
        $this->assertSame(1, $result['sent_count']);
    }

    public function test_rejected_subscriptions_are_retired_without_disabling_other_devices(): void
    {
        Http::fake(['*' => Http::response(['id' => '', 'errors' => ['invalid_player_ids' => ['subscription-0', 'not-targeted']]], 200)]);
        $service = app(OneSignalNotificationService::class);
        $result = $service->sendToMobileDevices('Flood', 'Evacuate', ['roles' => ['household']]);
        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['sent_count']);
        $this->assertStringContainsString('outdated device subscriptions', $result['errors'][0]);
        $this->assertSame(0, (int) DB::table('device_tokens')->where('player_id', 'subscription-0')->value('is_active'));
        $this->assertSame(1, (int) DB::table('device_tokens')->where('player_id', 'subscription-1')->value('is_active'));
        $this->assertSame([], $service->prepareDelivery(['roles' => ['household']])['recipient_ids']);
    }

    public function test_missing_credentials_skip_provider(): void
    {
        config(['services.onesignal.api_key' => '']);
        Http::fake();
        $result = app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate');
        $this->assertSame('not_configured', $result['status']);
        Http::assertNothingSent();
    }

    public function test_android_broadcast_excludes_other_platforms_and_includes_event_metadata(): void
    {
        DB::table('device_tokens')->where('player_id', 'subscription-1')->update(['platform' => 'ios']);
        Http::fake(['*' => Http::response(['id' => 'notification-id'], 200)]);
        $workflow = app(\App\Services\Web\DisasterBroadcastWorkflow::class);
        $result = $workflow->sendBroadcastPush(42, [
            'broadcast_title' => 'Flood warning', 'message' => 'Prepare for rising water.',
            'scope_type' => 'barangay_wide', 'allowed_statuses' => ['safe', 'unsafe'],
        ], ['puroks' => []], (object) ['event_id' => 'EVT-TEST']);
        $this->assertSame('sent', $result['status']);
        Http::assertSent(fn ($request) => $request['include_subscription_ids'] === ['subscription-0']
            && $request['target_channel'] === 'push'
            && $request['data']['type'] === 'disaster_broadcast'
            && $request['data']['event_id'] === 'EVT-TEST'
            && $request['data']['broadcast_id'] === '42');
        Http::assertSent(fn ($request) => array_column($request['data']['status_options'], 'key') === ['safe', 'unsafe']
            && array_column($request['data']['status_options'], 'safety_basis') === ['safe', 'unsafe']);
    }

    public function test_visible_push_contains_report_prompt_and_all_four_exact_configured_status_labels_in_order(): void
    {
        Http::fake(['*' => Http::response(['id' => 'notification-id'], 200)]);
        $options = [
            ['key' => 'all_clear', 'label' => 'All Clear Here', 'safety_basis' => 'safe'],
            ['key' => 'needs_supplies', 'label' => 'Need Food & Water', 'safety_basis' => 'unsafe'],
            ['key' => 'evacuated', 'label' => 'At Evacuation Site', 'safety_basis' => 'safe'],
            ['key' => 'need_help', 'label' => 'Rescue Us Now', 'safety_basis' => 'unsafe'],
        ];
        $result = app(\App\Services\Web\DisasterBroadcastWorkflow::class)->sendBroadcastPush(43, [
            'broadcast_title' => 'Signal No. 4', 'message' => 'Please evacuate to nearest site',
            'scope_type' => 'barangay_wide', 'allowed_statuses' => array_column($options, 'key'),
        ], ['puroks' => [], 'status_options' => $options], (object) ['event_id' => 'EVT-TEST']);
        $this->assertSame('sent', $result['status']);
        Http::assertSent(fn ($request) => $request['headings']['en'] === 'Signal No. 4'
            && $request['contents']['en'] === "Please evacuate to nearest site\n\nPlease report status now\n1. All Clear Here\n2. Need Food & Water\n3. At Evacuation Site\n4. Rescue Us Now"
            && $request['data']['status_options'] === $options
            && $request['data']['allowed_statuses'] === array_column($options, 'key')
            && $request['data']['report_status_prompt'] === 'Please report status now');
    }

    public function test_permanent_http_errors_are_not_retried_inline(): void
    {
        Http::fake(['*' => Http::response(['errors' => ['Invalid credentials']], 401)]);
        $this->assertSame('failed', app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate')['status']);
        Http::assertSentCount(1);
    }
}
