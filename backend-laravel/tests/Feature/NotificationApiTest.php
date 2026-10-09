<?php

namespace Tests\Feature;

use App\Events\NotificationFeedChanged;
use App\Models\Notification;
use App\Models\User;
use App\Models\WeatherLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    public function test_feed_and_preview_survive_cache_hits_with_class_deserialization_disabled(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('audit_log_id');
            foreach (['user_id', 'role_key', 'module', 'action', 'reference_table', 'reference_id', 'ip_address', 'user_agent'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        config([
            'realtime.read_cache' => true,
            'realtime.cache_store' => 'notification-test',
            'cache.serializable_classes' => false,
            'cache.stores.notification-test' => ['driver' => 'array', 'serialize' => true],
        ]);
        $cache = \Illuminate\Support\Facades\Cache::store('notification-test');
        $revision = 'realtime:'.config('app.env').':'.config('realtime.connection').':revision';
        $cache->put($revision.':initial:notification-sources', ['outgoing' => Notification::all()], 60);
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $cache->get($revision.':initial:notification-sources')['outgoing']);

        $user = User::query()->where('user_id', 'USR-ADMIN-001')->firstOrFail();
        $this->actingAs($user, 'sanctum');
        $first = $this->getJson('/api/v1/notifications')->assertOk();
        $second = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertNotEmpty($second->json('data.preview'));
        $this->assertIsArray($cache->get($revision.':initial:notification-feed:v2'));

        $this->postJson('/api/v1/notifications/mark-read', ['notification_ids' => ['notif-1']])->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.summary.unread', 0);
    }

    public function test_mobile_member_status_and_inquiry_writes_broadcast_view_updates(): void
    {
        Event::fake([NotificationFeedChanged::class]);
        foreach (['member_disaster_statuses', 'landing_inquiries', 'responder_location_logs'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('status');
            });
            \DB::transaction(fn () => \DB::table($tableName)->insert(['status' => 'updated']));
        }
        Event::assertDispatchedTimes(NotificationFeedChanged::class, 3);
    }

    public function test_private_channels_require_auth_and_reject_another_users_channel(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        // Register callbacks on this test driver, rather than the null driver used at boot.
        require base_path('routes/channels.php');
        $payload = ['socket_id' => '123.456', 'channel_name' => 'private-notifications.admin'];
        $this->postJson('/api/v1/broadcasting/auth', $payload)->assertUnauthorized();
        $user = User::query()->where('user_id', 'USR-ADMIN-001')->firstOrFail();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/api/v1/broadcasting/auth', array_replace($payload, ['channel_name' => 'private-users.someone-else']))->assertForbidden();
        $this->postJson('/api/v1/broadcasting/auth', array_replace($payload, ['channel_name' => 'private-users.USR-ADMIN-001']))->assertOk();
        $this->postJson('/api/v1/broadcasting/auth', array_replace($payload, ['channel_name' => 'private-operations.households']))->assertOk();
        $this->postJson('/api/v1/broadcasting/auth', array_replace($payload, ['channel_name' => 'private-operations.accounts']))->assertForbidden();
        $this->postJson('/api/v1/broadcasting/auth', array_replace($payload, ['channel_name' => 'private-operations.invalid-topic']))->assertForbidden();
        $user->role_id = 6;
        $user->unsetRelation('role');
        $this->postJson('/api/v1/broadcasting/auth', $payload)->assertForbidden();
    }

    public function test_query_builder_changes_broadcast_only_after_commit_and_never_after_rollback(): void
    {
        Event::fake([NotificationFeedChanged::class]);
        \DB::beginTransaction();
        \DB::table('notifications')->where('notif_id', 1)->update(['message' => 'Committed alert']);
        Event::assertNotDispatched(NotificationFeedChanged::class);
        \DB::commit();
        Event::assertDispatchedTimes(NotificationFeedChanged::class, 1);
        \DB::beginTransaction();
        \DB::table('notifications')->where('notif_id', 1)->update(['message' => 'Rolled back alert']);
        \DB::rollBack();
        Event::assertDispatchedTimes(NotificationFeedChanged::class, 1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('role_id');
                $table->string('role_key');
                $table->string('role_name');
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->string('user_id')->primary();
                $table->string('username')->nullable();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->integer('role_id')->nullable();
                $table->boolean('is_active')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->increments('notif_id');
                $table->text('message')->nullable();
                $table->string('sent_by')->nullable();
                $table->string('evacuation_event_id')->nullable();
                $table->string('evacuation_center_id')->nullable();
                $table->integer('urgency_level_id')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->boolean('is_recurring')->nullable();
                $table->integer('recurrence_type_id')->nullable();
                $table->timestamp('recurrence_end_at')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('channel')->nullable();
                $table->string('status')->nullable();
                $table->string('target_filter')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        $role = \DB::table('roles')->where('role_key', 'admin')->first();
        if (! $role) {
            \DB::table('roles')->insert(['role_id' => 2, 'role_key' => 'admin', 'role_name' => 'Admin']);
        }

        $user = User::query()->where('user_id', 'USR-ADMIN-001')->first();
        if (! $user) {
            User::query()->create([
                'user_id' => 'USR-ADMIN-001',
                'username' => 'admin001',
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role_id' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Notification::query()->updateOrCreate(
            ['notif_id' => 1],
            [
                'message' => 'Resource request needs review.',
                'sent_by' => 'USR-ADMIN-001',
                'evacuation_event_id' => 'EVT-001',
                'evacuation_center_id' => 'CENTER-01',
                'urgency_level_id' => 3,
                'scheduled_at' => now(),
                'is_recurring' => false,
                'recurrence_type_id' => null,
                'recurrence_end_at' => null,
                'last_sent_at' => null,
                'created_at' => now(),
                'channel' => 'web',
                'status' => 'sent',
                'target_filter' => 'admin',
                'updated_at' => now(),
            ]
        );
    }

    public function test_admin_can_fetch_notifications_feed(): void
    {
        $user = User::query()->where('user_id', 'USR-ADMIN-001')->firstOrFail();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('data.notifications.data.0.type', 'Broadcast')
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.notifications.current_page', 1)
            ->assertJsonPath('data.notifications.per_page', 5)
            ->assertJsonPath('data.notifications.page_count', 1)
            ->assertJsonPath('data.status_filter', 'all')
            ->assertJsonStructure(['data' => ['summary' => ['total', 'unread', 'critical', 'selected'],
                'notifications' => ['data', 'current_page', 'per_page', 'total', 'page_count'],
                'preview', 'status_filter', 'scope_note']]);
    }

    public function test_repeated_feed_requests_include_new_weather_and_preserve_user_actions(): void
    {
        Schema::create('weather_logs', function (Blueprint $table) {
            $table->increments('weather_log_id');
            $table->string('disaster_id')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('condition_name')->nullable();
            foreach (['temperature', 'rainfall_mm', 'wind_speed', 'humidity'] as $column) {
                $table->float($column)->nullable();
            }
            $table->string('wind_direction')->nullable();
            $table->string('advisory_title')->nullable();
            $table->text('advisory_text')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('audit_log_id');
            foreach (['user_id', 'role_key', 'module', 'action', 'reference_table', 'reference_id', 'ip_address', 'user_agent'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        $user = User::query()->where('user_id', 'USR-ADMIN-001')->firstOrFail();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.summary.unread', 1)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->postJson('/api/v1/notifications/mark-read', ['notification_ids' => ['notif-1']])->assertOk();
        $weather = WeatherLog::query()->create([
            'source_name' => 'External weather source', 'condition_name' => 'Clear',
            'observed_at' => now()->subHours(3),
        ]);
        $weatherId = 'weather-'.$weather->weather_log_id;
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.summary.unread', 1)
            ->assertJsonFragment(['id' => $weatherId, 'read' => false]);
        $this->postJson('/api/v1/notifications/delete-selected', ['notification_ids' => [$weatherId]])->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.summary.unread', 0);
        $this->assertDatabaseHas('weather_logs', ['weather_log_id' => $weather->weather_log_id]);

        $next = WeatherLog::query()->create(['source_name' => 'External weather source', 'condition_name' => 'Clear']);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.summary.unread', 1)
            ->assertJsonFragment(['id' => 'weather-'.$next->weather_log_id, 'read' => false]);
        $this->postJson('/api/v1/notifications/clear-all')->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.summary.total', 0);
        $this->assertDatabaseCount('weather_logs', 2);
        $this->assertDatabaseCount('notifications', 1);

        $otherUser = $user->replicate();
        $otherUser->user_id = 'USR-ADMIN-002';
        $otherUser->username = 'admin002';
        $otherUser->email = 'other-admin@example.com';
        $otherUser->save();
        $this->actingAs($otherUser, 'sanctum');
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.unread', 3);
    }
}
