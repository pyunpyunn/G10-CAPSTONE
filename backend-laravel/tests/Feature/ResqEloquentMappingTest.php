<?php

namespace Tests\Feature;

use App\Models\DispatchTrip;
use App\Models\ResponderFieldReport;
use App\Models\ResponderFieldReportMember;
use App\Models\WebPushSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResqEloquentMappingTest extends TestCase
{
    public function test_field_report_eager_loading_and_updates_use_legacy_keys_without_missing_timestamps(): void
    {
        Schema::create('responder_field_reports', function (Blueprint $table): void {
            $table->integer('report_id')->primary();
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('responder_field_report_members', function (Blueprint $table): void {
            $table->id('detail_id');
            $table->integer('report_id');
            $table->string('member_id');
            $table->timestamps();
        });
        $report = ResponderFieldReport::create(['report_id' => 42, 'notes' => 'Initial', 'created_at' => now()]);
        $report->members()->create(['member_id' => 'HM-8560-1']);
        $loaded = ResponderFieldReport::with('members.report')->findOrFail(42);
        $this->assertSame('HM-8560-1', $loaded->members->sole()->member_id);
        $this->assertSame(42, $loaded->members->sole()->report->getKey());
        $loaded->update(['notes' => 'Verified']);
        $this->assertDatabaseHas('responder_field_reports', ['report_id' => 42, 'notes' => 'Verified']);
        $this->assertSame(1, ResponderFieldReportMember::count());
    }

    public function test_trip_points_use_trip_id_and_cast_recording_time(): void
    {
        Schema::create('dispatch_trips', function (Blueprint $table): void {
            $table->id(); $table->timestamps();
        });
        Schema::create('trip_points', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('trip_id'); $table->timestamp('recorded_at');
        });
        $trip = DispatchTrip::create([]);
        $trip->points()->create(['recorded_at' => now()]);
        $this->assertSame($trip->id, $trip->points()->sole()->trip->id);
        $this->assertInstanceOf(\DateTimeInterface::class, $trip->points()->sole()->recorded_at);
    }

    public function test_web_push_subscription_secrets_are_hidden_from_serialization(): void
    {
        $subscription = new WebPushSubscription(['user_id' => 'U1', 'subscription' => ['endpoint' => 'private', 'keys' => ['auth' => 'secret']]]);
        $this->assertIsArray($subscription->subscription);
        $this->assertArrayNotHasKey('subscription', $subscription->toArray());
    }
}
