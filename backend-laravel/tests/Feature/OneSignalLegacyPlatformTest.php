<?php

namespace Tests\Feature;

use App\Services\Shared\OneSignalNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OneSignalLegacyPlatformTest extends TestCase
{
    private const SUBSCRIPTION = '9ce9c5e7-4de7-4a41-853c-e6176a9f0e48';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.onesignal.app_id' => 'platform-test-app', 'services.onesignal.api_key' => 'platform-test-key']);
        Schema::create('device_tokens', function (Blueprint $t): void {
            $t->string('user_id'); $t->string('player_id'); $t->string('platform'); $t->string('app_role');
            $t->string('push_provider'); $t->string('notification_permission_status'); $t->boolean('is_active');
        });
        DB::table('device_tokens')->insert(['user_id' => 'USER-PLATFORM', 'player_id' => self::SUBSCRIPTION, 'platform' => 'mobile', 'app_role' => 'household', 'push_provider' => 'onesignal', 'notification_permission_status' => 'granted', 'is_active' => true]);
        Http::preventStrayRequests();
    }

    private function provider(string $type = 'AndroidPush', string $owner = 'USER-PLATFORM', bool $enabled = true): void
    {
        Http::fake(['*/users/by/external_id/*' => Http::response(['identity' => ['external_id' => $owner], 'subscriptions' => [['id' => self::SUBSCRIPTION, 'type' => $type, 'enabled' => $enabled, 'notification_types' => $enabled ? 1 : 0]]])]);
    }

    private function audience(): array
    {
        return app(OneSignalNotificationService::class)->prepareDelivery(['platforms' => ['android'], 'roles' => ['household']])['recipient_ids'];
    }

    public function test_provider_verified_android_registration_is_eligible_without_changing_the_database(): void
    {
        $this->provider();
        $this->assertSame([self::SUBSCRIPTION], $this->audience());
        $this->assertSame([self::SUBSCRIPTION], $this->audience());
        $this->assertDatabaseHas('device_tokens', ['platform' => 'mobile', 'player_id' => self::SUBSCRIPTION]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_ios_subscription_is_not_treated_as_android(): void
    {
        $this->provider('iOSPush');
        $this->assertSame([], $this->audience());
    }

    public function test_different_provider_account_is_not_targeted(): void
    {
        $this->provider('AndroidPush', 'OTHER-USER');
        $this->assertSame([], $this->audience());
    }

    public function test_unsubscribed_legacy_registration_is_not_targeted(): void
    {
        $this->provider('AndroidPush', 'USER-PLATFORM', false);
        $this->assertSame([], $this->audience());
    }

    public function test_denied_permission_still_excludes_a_valid_provider_subscription(): void
    {
        DB::table('device_tokens')->update(['notification_permission_status' => 'denied']);
        Http::fake();
        $this->assertSame([], $this->audience());
        Http::assertNothingSent();
    }
}
