<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
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
            ->assertJsonPath('data.summary.total', 1);
    }
}
