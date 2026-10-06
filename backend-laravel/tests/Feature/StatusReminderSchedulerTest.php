<?php

namespace Tests\Feature;

use App\Jobs\SendStatusReminder;
use App\Services\Shared\StatusReminderScheduler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatusReminderSchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('member_disaster_statuses', function (Blueprint $table): void {
            $table->string('member_id');
            $table->string('disaster_id');
        });
        Schema::create('status_reminders', function (Blueprint $table): void {
            $table->bigIncrements('reminder_id');
            $table->string('event_id');
            $table->string('household_id');
            $table->string('member_id');
            $table->integer('attempt');
            $table->string('status');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('member_delivery')->nullable();
            $table->string('household_delivery')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'member_id', 'attempt']);
        });
        DB::table('disaster_events')->insert(['event_id' => 'EVT-1', 'started_at' => now()->subHours(4)]);
        DB::table('household_members')->insert(['member_id' => 'M-1', 'household_id' => 'HH-1']);
    }

    public function test_it_queues_one_reminder_per_member_event_and_attempt(): void
    {
        Queue::fake();

        $this->assertSame(1, app(StatusReminderScheduler::class)->scheduleDue());
        $this->assertSame(0, app(StatusReminderScheduler::class)->scheduleDue());
        $this->assertDatabaseCount('status_reminders', 1);
        Queue::assertPushed(SendStatusReminder::class, 1);
    }

    public function test_reported_members_are_not_scheduled(): void
    {
        Queue::fake();
        DB::table('member_disaster_statuses')->insert(['member_id' => 'M-1', 'disaster_id' => 'EVT-1']);

        $this->assertSame(0, app(StatusReminderScheduler::class)->scheduleDue());
        Queue::assertNotPushed(SendStatusReminder::class);
    }

    public function test_stale_sending_attempt_is_requeued_without_creating_another_attempt(): void
    {
        Queue::fake();
        DB::table('status_reminders')->insert([
            'event_id' => 'EVT-1', 'household_id' => 'HH-1', 'member_id' => 'M-1',
            'attempt' => 1, 'status' => 'sending', 'scheduled_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30),
        ]);

        $this->assertSame(0, app(StatusReminderScheduler::class)->scheduleDue());
        $this->assertDatabaseCount('status_reminders', 1);
        $this->assertDatabaseHas('status_reminders', ['member_id' => 'M-1', 'status' => 'pending']);
        Queue::assertPushed(SendStatusReminder::class, 1);
    }
}
