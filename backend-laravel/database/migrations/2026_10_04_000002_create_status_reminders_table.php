<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('status_reminders', function (Blueprint $table): void {
            $table->bigIncrements('reminder_id');
            $table->string('event_id');
            $table->string('household_id');
            $table->string('member_id');
            $table->unsignedTinyInteger('attempt');
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('member_delivery', 30)->nullable();
            $table->string('household_delivery', 30)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'member_id', 'attempt'], 'status_reminders_event_member_attempt_unique');
            $table->index(['status', 'scheduled_at'], 'status_reminders_due_index');
            $table->index(['event_id', 'household_id', 'status'], 'status_reminders_household_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_reminders');
    }
};
