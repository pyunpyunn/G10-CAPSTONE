<?php

namespace Tests\Feature;

use App\Services\Navigation\MobileSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sync_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_event_id')->unique();
            $table->unsignedBigInteger('device_id');
            $table->string('type');
            $table->string('status');
            $table->string('error_message')->nullable();
            $table->json('result_json')->nullable();
            $table->timestamp('received_at');
        });
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id');
            $table->integer('team_id');
            $table->string('device_uuid')->unique();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
        Schema::create('dispatch_trips', function (Blueprint $table): void {
            $table->id();
            $table->integer('dispatch_id');
            $table->integer('team_id');
            $table->unsignedBigInteger('device_id');
            $table->string('profile');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->integer('team_id');
            $table->string('status');
        });
        Schema::create('trip_points', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 3)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->dateTime('recorded_at', 6);
        });

        DB::table('devices')->insert([
            'id' => 1, 'user_id' => 'USER-1', 'team_id' => 4, 'device_uuid' => 'device-4',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('responder_assignments')->insert(['assignment_id' => 10, 'team_id' => 4, 'status' => 'en_route']);
        DB::table('dispatch_trips')->insert([
            'id' => 20, 'dispatch_id' => 10, 'team_id' => 4, 'device_id' => 1, 'profile' => 'pedestrian',
            'started_at' => now()->subMinute(), 'status' => 'recording', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_duplicate_point_event_in_a_batch_is_acknowledged_without_duplicate_rows(): void
    {
        $event = [
            'client_event_id' => '3f1f2b2e-46c7-4fa6-87fb-e2245d2ea861',
            'type' => 'trip.points',
            'occurred_at' => now()->toISOString(),
            'payload' => [
                'trip_id' => 20,
                'points' => [[
                    'lat' => 10.291,
                    'lng' => 123.877,
                    'accuracy' => 8.5,
                    'speed' => 1.1,
                    'heading' => 90,
                    'recorded_at' => now()->toISOString(),
                ]],
            ],
        ];
        $request = Request::create('/api/v1/mobile/sync', 'POST', ['events' => [$event, $event]]);
        $request->attributes->set('mobile_device', (object) ['id' => 1, 'team_id' => 4]);

        $result = app(MobileSyncService::class)->sync($request);

        $this->assertSame(['processed', 'processed'], array_column($result['acks'], 'status'));
        $this->assertTrue($result['acks'][1]['duplicate']);
        $this->assertSame($result['acks'][0]['result'], $result['acks'][1]['result']);
        $this->assertSame(1, DB::table('sync_events')->count());
        $this->assertSame(1, DB::table('trip_points')->count());
    }
}
