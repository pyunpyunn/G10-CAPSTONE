<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_outbox', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->text('topics');
            $table->unsignedInteger('available_at');
            $table->uuid('claim_token')->nullable()->index();
            $table->unsignedInteger('claimed_until')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->index(['delivered_at', 'available_at', 'id'], 'realtime_outbox_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_outbox');
    }
};
