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
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.onesignal.app_id' => 'test-app', 'services.onesignal.api_key' => 'test-key']);
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->string('player_id');
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

    public function test_missing_credentials_skip_provider(): void
    {
        config(['services.onesignal.api_key' => '']);
        Http::fake();
        $result = app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate');
        $this->assertSame('not_configured', $result['status']);
        Http::assertNothingSent();
    }

    public function test_permanent_http_errors_are_not_retried_inline(): void
    {
        Http::fake(['*' => Http::response(['errors' => ['Invalid credentials']], 401)]);
        $this->assertSame('failed', app(OneSignalNotificationService::class)->sendToMobileDevices('Flood', 'Evacuate')['status']);
        Http::assertSentCount(1);
    }
}
